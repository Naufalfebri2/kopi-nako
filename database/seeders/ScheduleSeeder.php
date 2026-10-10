<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Data contoh jadwal dua minggu. Hapus dari DatabaseSeeder sebelum dipakai sungguhan.
class ScheduleSeeder extends Seeder
{
    // Leader tetap per shift (bagian depan email => nama shift)
    private const LEADERS = ['alfi' => 'Pagi', 'hendri' => 'Siang', 'fatah' => 'Malam'];

    // Pola berputar untuk karyawan lain (null = libur)
    private const PATTERN = ['Pagi', 'Siang', 'Malam', null];

    public function run(): void
    {
        $supervisor = User::where('email', 'juandi@kopinako.test')->firstOrFail();
        $shifts = MasterShift::all()->keyBy('name');

        $staff = Employee::with('user.role')->orderBy('id')->get()
            ->reject(fn (Employee $employee) => $employee->user->hasRole(Role::SUPERVISOR))
            ->values();

        $start = now()->startOfWeek();

        // Satu baris jadwal = satu shift pada satu tanggal
        $rosters = [];
        $roster = function (string $shift, string $date) use (&$rosters, $shifts, $supervisor): ShiftSchedule {
            return $rosters[$date][$shift] ??= ShiftSchedule::create([
                'shift_id' => $shifts[$shift]->id,
                'status' => 'terbit',
                'publish_by' => $supervisor->id,
            ]);
        };

        foreach ($staff as $i => $employee) {
            $leaderShift = self::LEADERS[Str::before($employee->user->email, '@')] ?? null;

            for ($day = 0; $day < 14; $day++) {
                $shift = $leaderShift ?? self::PATTERN[($i + $day) % 4];

                if ($shift === null) {
                    continue;
                }

                $date = $start->copy()->addDays($day)->toDateString();

                ShiftScheduleDetail::create([
                    'id_schedule' => $roster($shift, $date)->id,
                    'id_employee' => $employee->id,
                    'status_lead' => $leaderShift !== null,
                    'date' => $date,
                ]);
            }
        }
    }
}
