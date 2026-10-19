<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
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

        // Karyawan nonaktif tidak boleh login. Pesan sengaja sama dengan salah kata sandi.
        $credentials['id'] = fn ($query) => $query->whereDoesntHave(
            'employee',
            fn ($employee) => $employee->where('status', EmployeeStatus::Nonaktif)
        );

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['password' => 'Email atau kata sandi salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('beranda'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
