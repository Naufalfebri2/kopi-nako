<?php

namespace App\Services;

class Division
{
    // Pengelompokan jabatan ke divisi (aturan dari klien). Tukar shift hanya boleh sesama divisi.
    private const MAP = [
        'Cook' => ['Lead Cook', 'Cook', 'Cook Helper'],
        'Barista' => ['Head Barista', 'Senior Barista', 'Junior Barista'],
        'Service' => ['Lead Service', 'Service'],
        'Accounting' => ['Accounting'],
        'Security' => ['Security'],
    ];

    // Nama divisi untuk sebuah jabatan, atau null bila jabatan tidak dikenal.
    public function of(?string $jobName): ?string
    {
        foreach (self::MAP as $division => $jobs) {
            if (in_array($jobName, $jobs, true)) {
                return $division;
            }
        }

        return null;
    }

    public function same(?string $jobA, ?string $jobB): bool
    {
        $division = $this->of($jobA);

        return $division !== null && $division === $this->of($jobB);
    }
}
