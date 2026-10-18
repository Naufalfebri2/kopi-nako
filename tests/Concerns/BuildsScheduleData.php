<?php

namespace Tests\Concerns;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\LeaveType;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use App\Services\Division;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Pembuat data untuk feature test jadwal dan pengajuan
trait BuildsScheduleData
{
    private int $nikSeq = 0;

    // Pemetaan divisi dipalsukan agar test tidak bergantung pada daftar jabatan asli
    protected function mockDivisions(): void
    {
        $this->partialMock(Division::class, function ($mock) {
            $mock->shouldReceive('of')->andReturnUsing(fn (?string $job) => match ($job) {
                'Barista', 'Kasir' => 'Bar',
                'Koki' => 'Dapur',
                default => null,
            });
        });
    }

    protected function makeEmployee(string $roleName, string $jobName, string $name): Employee
    {
        return Model::unguarded(function () use ($roleName, $jobName, $name) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $job = Job::firstOrCreate(['name' => $jobName]);

            $user = User::create([
                'name' => $name,
                'email' => Str::slug($name).'@example.test',
                'password' => bcrypt('password'),
                'id_role' => $role->id, // sesuaikan bila nama kolom role di tabel user berbeda
            ]);

            $seq = ++$this->nikSeq;

            return Employee::create([
                'id_user' => $user->id,
                'job_id' => $job->id,
                'status' => EmployeeStatus::Aktif,
                'nik' => sprintf('%016d', $seq),
                'no_phone' => sprintf('0812%08d', $seq),
            ])->load('user.role', 'job');
        });
    }

    protected function makeShift(string $name = 'Pagi'): MasterShift
    {
        return Model::unguarded(fn () => MasterShift::create([
            'name' => $name,
            'start_date' => '07:00:00',
            'end_date' => '15:00:00',
        ]));
    }

    // Satu karyawan dijadwalkan pada satu tanggal, dengan baris jadwal sendiri
    protected function assign(Employee $employee, MasterShift $shift, string $date): ShiftScheduleDetail
    {
        return Model::unguarded(function () use ($employee, $shift, $date) {
            $schedule = ShiftSchedule::create([
                'shift_id' => $shift->id,
                'status' => 'terbit',
                'publish_by' => $employee->id_user,
            ]);

            return ShiftScheduleDetail::create([
                'id_schedule' => $schedule->id,
                'id_employee' => $employee->id,
                'status_lead' => false,
                'date' => $date,
            ]);
        });
    }

    // Pengajuan izin secara bawaan. Tukar: kirim partner_id dan partner_schedule_id lewat $overrides
    protected function makeRequest(ShiftScheduleDetail $detail, array $overrides = []): ShiftRequest
    {
        $isSwap = isset($overrides['partner_id']);

        return Model::unguarded(fn () => ShiftRequest::create($overrides + [
            'employee_id' => $detail->id_employee,
            'schedule_id' => $detail->id_schedule,
            'leave_id' => $isSwap ? null : $this->leaveTypeId(),
            'partner_id' => null,
            'partner_schedule_id' => null,
            'status' => RequestStatus::Menunggu,
            'reason' => 'Keperluan keluarga mendadak',
            'created_by' => Employee::find($detail->id_employee)->id_user,
        ]));
    }

    // Pengajuan tukar shift antara $detail dan $partnerDetail
    protected function makeSwapRequest(
        ShiftScheduleDetail $detail,
        ShiftScheduleDetail $partnerDetail,
        array $overrides = [],
    ): ShiftRequest {
        return $this->makeRequest($detail, $overrides + [
            'partner_id' => $partnerDetail->id_employee,
            'partner_schedule_id' => $partnerDetail->id_schedule,
        ]);
    }

    protected function leaveTypeId(): int
    {
        return Model::unguarded(fn () => LeaveType::firstOrCreate(['name' => 'Sakit']))->id;
    }
}
