<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // roles (urutan menentukan id: 1 Karyawan, 2 Leader/Head, 3 Supervisor)
        foreach (['Karyawan', 'Leader/Head', 'Supervisor'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        // master_shift (jam sesuai prototipe)
        $shifts = [
            ['name' => 'Pagi',  'start_date' => '09:00', 'end_date' => '15:00'],
            ['name' => 'Siang', 'start_date' => '15:00', 'end_date' => '21:00'],
            ['name' => 'Malam', 'start_date' => '21:00', 'end_date' => '03:30'],
        ];
        foreach ($shifts as $shift) {
            MasterShift::firstOrCreate(['name' => $shift['name']], $shift);
        }

        // leave_type (data awal, bisa diubah kapan saja)
        foreach (['Sakit', 'Izin Pribadi'] as $name) {
            LeaveType::firstOrCreate(['name' => $name]);
        }

        // akun Supervisor untuk uji login
        $supervisor = Role::where('name', 'Supervisor')->first();
        User::firstOrCreate(
            ['email' => 'supervisor@kopinako.test'],
            [
                'name' => 'Supervisor',
                'password' => 'password123',
                'id_role' => $supervisor->id,
            ]
        );
    }
}
