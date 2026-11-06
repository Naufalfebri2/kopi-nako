<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 60;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Alamat email tidak boleh kosong.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi tidak boleh kosong.',
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            Log::warning('Login diblokir sementara.', $this->context($request));

            return back()
                ->withErrors(['password' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .RateLimiter::availableIn($key).' detik.'])
                ->onlyInput('email');
        }

        // Karyawan nonaktif tidak boleh login. Pesan sengaja sama dengan salah kata sandi.
        $credentials['id'] = fn ($query) => $query->whereDoesntHave(
            'employee',
            fn ($employee) => $employee->where('status', EmployeeStatus::Nonaktif)
        );

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            Log::warning('Login gagal.', $this->context($request));

            return back()
                ->withErrors(['password' => 'Email atau kata sandi salah.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Log::info('Login berhasil.', ['user_id' => Auth::id(), 'ip' => $request->ip()]);

        return redirect()->intended(route('beranda'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Log::info('Logout.', ['user_id' => Auth::id()]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // Hitungan percobaan gagal per kombinasi email dan IP
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }

    // Konteks log tanpa kata sandi dan tanpa email asli (hanya potongan hash)
    private function context(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'email_hash' => substr(hash('sha256', Str::lower((string) $request->input('email'))), 0, 12),
        ];
    }
}
