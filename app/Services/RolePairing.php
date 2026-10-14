<?php

namespace App\Services;

use App\Models\Role;

class RolePairing
{
    // Jabatan yang boleh dipakai tiap role (berdasarkan nama di tabel roles dan job).
    private const MAP = [
        Role::SUPERVISOR => ['Supervisor'],
        Role::LEADER => ['Head Barista', 'Lead Cook', 'Lead Service'],
        Role::KARYAWAN => ['Accounting', 'Security', 'Senior Barista', 'Junior Barista', 'Service', 'Cook', 'Cook Helper'],
    ];

    public function allowedJobs(string $roleName): array
    {
        return self::MAP[$roleName] ?? [];
    }

    public function allows(string $roleName, string $jobName): bool
    {
        return in_array($jobName, $this->allowedJobs($roleName), true);
    }
}
