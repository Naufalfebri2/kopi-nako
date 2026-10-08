<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    // [nama, role akses, bagian depan email]
    private const STAFF = [
        ['Juandi', 'Supervisor', 'juandi'],
        ['Alfi Fatan', 'Leader/Head', 'alfi'],
        ['Fatah', 'Leader/Head', 'fatah'],
        ['Hendriansyah', 'Leader/Head', 'hendri'],
        ['M. Fikri', 'Karyawan', 'fikri'],
        ['Samsul', 'Karyawan', 'samsul'],
        ['Fajar M.', 'Karyawan', 'fajar'],
        ['M. Haikal', 'Karyawan', 'haikal'],
        ['Artie', 'Karyawan', 'artie'],
        ['Inge', 'Karyawan', 'inge'],
        ['Keanu', 'Karyawan', 'keanu'],
        ['Adam', 'Karyawan', 'adam'],
        ['Nayla', 'Karyawan', 'nayla'],
        ['Eliya', 'Karyawan', 'eliya'],
        ['Kaisar', 'Karyawan', 'kaisar'],
        ['Rahma', 'Karyawan', 'rahma'],
        ['Anisa', 'Karyawan', 'anisa'],
        ['Ivone', 'Karyawan', 'ivone'],
        ['Wahyudi', 'Karyawan', 'wahyudi'],
    ];

    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        foreach (self::STAFF as $i => [$name, $role, $emailName]) {
            $number = $i + 1;

            $user = User::firstOrCreate(
                ['email' => $emailName.'@kopinako.test'],
                ['name' => $name, 'password' => 'password123', 'id_role' => $roles[$role]],
            );

            // NIK dan nomor telepon di bawah ini data contoh, bukan data asli
            Employee::firstOrCreate(
                ['id_user' => $user->id],
                [
                    'nik' => sprintf('%016d', $number),
                    'no_phone' => sprintf('08000000%04d', $number),
                    'status' => EmployeeStatus::Aktif,
                ],
            );
        }
    }
}