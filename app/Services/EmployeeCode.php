<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use DomainException;

// Kode karyawan format YYMM01B001: tahun-bulan, kode tetap 01 dan B, lalu nomor urut per bulan.
class EmployeeCode
{
    public const BRANCH = '01';
    public const LETTER = 'B';
    private const MAX_SEQUENCE = 999;

    // Kode berikutnya untuk bulan pada $date (bawaan: bulan berjalan)
    public function next(?Carbon $date = null): string
    {
        $prefix = $this->prefix(($date ?? now())->format('ym'));

        return $this->format($prefix, $this->lastSequence($prefix) + 1);
    }

    // Contoh: prefix("2610") menjadi "261001B"
    public function prefix(string $period): string
    {
        return $period.self::BRANCH.self::LETTER;
    }

    // Nomor urut terbesar yang sudah dipakai pada prefix itu, atau 0 bila belum ada
    public function lastSequence(string $prefix): int
    {
        $last = Employee::where('nik', 'like', $prefix.'%')->max('nik');

        return $last === null ? 0 : (int) substr($last, -3);
    }

    public function format(string $prefix, int $sequence): string
    {
        if ($sequence > self::MAX_SEQUENCE) {
            throw new DomainException('Nomor urut kode karyawan bulan ini sudah mencapai '.self::MAX_SEQUENCE.'.');
        }

        return sprintf('%s%03d', $prefix, $sequence);
    }

    public function isValid(?string $code): bool
    {
        return $code !== null
            && preg_match('/^\d{4}'.self::BRANCH.self::LETTER.'\d{3}$/', $code) === 1;
    }
}
