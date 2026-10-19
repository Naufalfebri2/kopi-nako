<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Memeriksa bentuk alasan pengajuan. Sistem tidak bisa menilai makna kalimat,
// jadi kalimat asal yang berbentuk kata tetap lolos dan diperiksa Leader dan Supervisor.
class ClearReason implements ValidationRule
{
    private const MIN_CHARS = 20;
    private const MIN_WORDS = 4;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
        $words = $text === '' ? [] : explode(' ', $text);

        if (mb_strlen($text) < self::MIN_CHARS) {
            $fail('Alasan minimal 20 karakter. Jelaskan dengan kalimat yang lengkap.');

            return;
        }

        if (count($words) < self::MIN_WORDS) {
            $fail('Alasan minimal 4 kata. Jelaskan dengan kalimat yang lengkap.');

            return;
        }

        if (preg_match('/(\S)\1{3,}/iu', $text)) {
            $fail('Alasan tidak boleh memuat karakter yang sama berulang 4 kali berturut-turut.');

            return;
        }

        foreach ($words as $word) {
            $letters = preg_replace('/[^\p{L}]/u', '', $word);

            if ($letters !== '' && ! preg_match('/[aiueo]/iu', $letters)) {
                $fail("Alasan tidak jelas: kata \"{$word}\" tidak mengandung huruf vokal. Tulis dengan kata yang utuh.");

                return;
            }
        }
    }
}
