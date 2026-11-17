<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // <-- tambah blok ini
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        Paginator::useBootstrapFive();

        $this->configureRateLimiting();
    }

    // Semua batas dihitung per pengguna yang login (cadangan: IP), jadi staf yang memakai
    // Wi-Fi yang sama tidak berbagi jatah. Tiap limiter bernama punya keranjang sendiri.
    private function configureRateLimiting(): void
    {
        // Seluruh route di grup auth: membaca lebih longgar daripada menulis.
        RateLimiter::for('aplikasi', fn (Request $request) => $request->isMethodSafe()
            ? $this->limit(120, $request)
            : $this->limit(30, $request, 'tulis'));

        // Aksi berat atau berefek samping, dengan batas lebih ketat.
        RateLimiter::for('ekspor', fn (Request $request) => $this->limit(10, $request));
        RateLimiter::for('kirim-jadwal', fn (Request $request) => $this->limit(5, $request));
        RateLimiter::for('karyawan-baru', fn (Request $request) => $this->limit(20, $request));
    }

    private function limit(int $perMinute, Request $request, string $suffix = ''): Limit
    {
        $key = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        return Limit::perMinute($perMinute)
            ->by($suffix === '' ? $key : $key.'|'.$suffix)
            ->response(fn (Request $request, array $headers) => $this->tooManyRequests($request, $headers));
    }

    private function tooManyRequests(Request $request, array $headers): Response
    {
        $message = 'Terlalu banyak permintaan. Coba lagi dalam '.($headers['Retry-After'] ?? 60).' detik.';

        // Aksi dari formulir kembali ke halaman asal dengan pesan. Pembacaan (termasuk fetch) mendapat 429.
        return $request->isMethodSafe()
            ? response($message, 429, $headers)
            : back()->with('error', $message);
    }
}
