<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppGateway
{
    // Mengembalikan true bila pesan diterima gateway. Kegagalan tidak pernah dilempar ke pemanggil.
    public function send(?string $phone, string $message): bool
    {
        $to = $this->normalize($phone);

        if ($to === null) {
            return false;
        }

        try {
            if (config('whatsapp.driver') === 'fonnte') {
                $response = Http::withHeaders(['Authorization' => (string) config('whatsapp.token')])
                    ->timeout(10)
                    ->asForm()
                    ->post(config('whatsapp.url'), ['target' => $to, 'message' => $message]);

                return $response->successful() && $response->json('status') !== false;
            }

            Log::info('WhatsApp (driver log).', ['to' => substr($to, 0, 5).'****', 'message' => $message]);

            return true;
        } catch (Throwable $e) {
            // Hanya nama kelas yang dicatat, tanpa nomor atau isi pesan.
            Log::warning('Pengiriman WhatsApp gagal.', ['exception' => $e::class]);

            return false;
        }
    }

    // 0812..., +62812..., 62812... menjadi 62812...; null bila bukan nomor seluler Indonesia.
    public function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return preg_match('/^628\d{8,12}$/', $digits) ? $digits : null;
    }
}