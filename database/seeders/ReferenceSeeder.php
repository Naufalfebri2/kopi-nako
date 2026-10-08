<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use App\Models\MasterShift;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Karyawan', 'Leader/Head', 'Supervisor'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $shifts = [
            ['Pagi', '09:00', '15:00'],
            ['Siang', '15:00', '21:00'],
            ['Malam', '21:00', '03:30'],
        ];
        foreach ($shifts as [$name, $start, $end]) {
            MasterShift::firstOrCreate(['name' => $name], ['start_date' => $start, 'end_date' => $end]);
        }

        foreach (['Sakit', 'Izin Pribadi'] as $name) {
            LeaveType::firstOrCreate(['name' => $name]);
        }
    }
}