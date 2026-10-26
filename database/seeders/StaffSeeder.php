<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeCode;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    // [nama, role akses, bagian depan email, jabatan]
    private const STAFF = [
        ['Juandi', 'Supervisor', 'juandi', 'Supervisor'],
        ['Alfi Fatan', 'Leader/Head', 'alfi', 'Head Barista'],
        ['Fatah', 'Leader/Head', 'fatah', 'Lead Cook'],
        ['Hendriansyah', 'Leader/Head', 'hendri', 'Lead Service'],
        ['M. Fikri', 'Karyawan', 'fikri', 'Accounting'],
        ['Samsul', 'Karyawan', 'samsul', 'Security'],
        ['Fajar M.', 'Karyawan', 'fajar', 'Senior Barista'],
        ['M. Haikal', 'Karyawan', 'haikal', 'Senior Barista'],
        ['Artie', 'Karyawan', 'artie', 'Junior Barista'],
        ['Inge', 'Karyawan', 'inge', 'Junior Barista'],
        ['Keanu', 'Karyawan', 'keanu', 'Service'],
        ['Adam', 'Karyawan', 'adam', 'Service'],
        ['Nayla', 'Karyawan', 'nayla', 'Service'],
        ['Eliya', 'Karyawan', 'eliya', 'Service'],
        ['Kaisar', 'Karyawan', 'kaisar', 'Cook'],
        ['Rahma', 'Karyawan', 'rahma', 'Cook Helper'],
        ['Anisa', 'Karyawan', 'anisa', 'Cook Helper'],
        ['Ivone', 'Karyawan', 'ivone', 'Cook Helper'],
        ['Wahyudi', 'Karyawan', 'wahyudi', 'Cook Helper'],
    ];

    public function run(EmployeeCode $codes): void
    {
        $roles = Role::pluck('id', 'name');
        $jobs = Job::pluck('id', 'name');

        foreach (self::STAFF as $i => [$name, $role, $emailName, $job]) {
            $number = $i + 1;

            $user = User::firstOrCreate(
                ['email' => $emailName.'@kopinako.test'],
                ['name' => $name, 'password' => 'password123', 'id_role' => $roles[$role]],
            );

            // Kode karyawan (YYMM01B001) dibuat EmployeeCode, sama seperti form Tambah Karyawan.
            // Nomor telepon di bawah ini data contoh, bukan data asli.
            Employee::firstOrCreate(
                ['id_user' => $user->id],
                [
                    'nik' => $codes->next(),
                    'job_id' => $jobs[$job],
                    'no_phone' => sprintf('08000000%04d', $number),
                    'status' => EmployeeStatus::Aktif,
                ],
            );
        }
    }
}
