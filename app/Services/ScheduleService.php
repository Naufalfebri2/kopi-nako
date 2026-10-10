<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\MasterShift;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    private const DETAIL_RELATIONS = ['schedule.masterShift', 'employee.user', 'employee.job'];

    // Tujuh hari (Senin sampai Minggu) pada minggu yang memuat $date
    public function weekDays(Carbon $date): Collection
    {
        $start = $date->copy()->startOfWeek();

        return collect(range(0, 6))->map(fn (int $i) => $start->copy()->addDays($i));
    }

    // Satu baris per karyawan aktif, berisi penugasan untuk tiap hari (null = libur)
    public function weeklyRows(Collection $days, ?int $jobId): Collection
    {
        $details = ShiftScheduleDetail::with('schedule.masterShift')
            ->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->get()
            ->groupBy('id_employee');

        return Employee::with(['user.role', 'job'])
            ->where('status', EmployeeStatus::Aktif)
            ->when($jobId, fn ($query) => $query->where('job_id', $jobId))
            ->get()
            ->sortBy(fn (Employee $employee) => mb_strtolower($employee->user->name))
            ->values()
            ->map(function (Employee $employee) use ($details, $days) {
                $mine = ($details->get($employee->id) ?? collect())
                    ->keyBy(fn (ShiftScheduleDetail $detail) => $detail->date->toDateString());

                return [
                    'employee' => $employee,
                    'cells' => $days->map(fn (Carbon $day) => $mine->get($day->toDateString()))->all(),
                ];
            });
    }

    // Satu kolom per shift, berisi penugasan pada tanggal tersebut (Leader di urutan atas)
    public function dayBoard(Carbon $date, ?int $jobId): Collection
    {
        $details = ShiftScheduleDetail::with(self::DETAIL_RELATIONS)
            ->where('date', $date->toDateString())
            ->when($jobId, fn ($query) => $query->whereHas(
                'employee', fn ($employee) => $employee->where('job_id', $jobId)
            ))
            ->get();

        return MasterShift::orderBy('start_date')->get()->map(fn (MasterShift $shift) => [
            'shift' => $shift,
            'details' => $details
                ->filter(fn (ShiftScheduleDetail $detail) => $detail->schedule->shift_id == $shift->id)
                ->sortBy(fn (ShiftScheduleDetail $detail) => ($detail->status_lead ? '0' : '1')
                    .mb_strtolower($detail->employee->user->name))
                ->values(),
        ]);
    }

    public function activeEmployees(): Collection
    {
        return Employee::with(['user', 'job'])
            ->where('status', EmployeeStatus::Aktif)
            ->get()
            ->sortBy(fn (Employee $employee) => mb_strtolower($employee->user->name))
            ->values();
    }

    // Pesan kesalahan bila penugasan tidak boleh dibuat, atau null bila boleh
    public function assignmentError(array $data): ?string
    {
        $employee = Employee::with('user.role')->findOrFail($data['employee_id']);

        if ($employee->status !== EmployeeStatus::Aktif) {
            return "{$employee->user->name} tidak aktif.";
        }

        $alreadyScheduled = ShiftScheduleDetail::where('id_employee', $employee->id)
            ->where('date', $data['date'])
            ->exists();

        if ($alreadyScheduled) {
            return "{$employee->user->name} sudah punya shift pada tanggal ini.";
        }

        if ($data['status_lead'] && ! $employee->user->hasRole(Role::LEADER)) {
            return 'Hanya karyawan dengan role Leader/Head yang bisa dijadikan Leader shift.';
        }

        return null;
    }

    public function assign(User $by, array $data): ShiftScheduleDetail
    {
        return DB::transaction(function () use ($by, $data) {
            $schedule = $this->scheduleFor((int) $data['shift_id'], $data['date'], $by);

            $detail = ShiftScheduleDetail::create([
                'id_schedule' => $schedule->id,
                'id_employee' => $data['employee_id'],
                'status_lead' => $data['status_lead'],
                'date' => $data['date'],
            ]);

            $shiftName = MasterShift::find($schedule->shift_id)->name;
            $this->notify(
                $detail->id_employee,
                $schedule->id,
                "Anda dijadwalkan shift {$shiftName} pada {$this->dateLabel($detail->date)}"
            );

            return $detail;
        });
    }

    // Pesan kesalahan bila penugasan tidak boleh diubah, atau null bila boleh
    public function updateError(ShiftScheduleDetail $detail, array $data): ?string
    {
        $detail->loadMissing('employee.user.role', 'schedule');

        if ($data['status_lead'] && ! $detail->employee->user->hasRole(Role::LEADER)) {
            return 'Hanya karyawan dengan role Leader/Head yang bisa dijadikan Leader shift.';
        }

        $shiftChanged = (int) $detail->schedule->shift_id !== (int) $data['shift_id'];

        if ($shiftChanged && $this->hasRequests($detail)) {
            return 'Penugasan ini punya pengajuan izin/tukar shift, jadi shift-nya tidak bisa diubah.';
        }

        return null;
    }

    // Mengubah shift dan status Leader. Karyawan dan tanggal tidak berubah
    public function update(User $by, ShiftScheduleDetail $detail, array $data): void
    {
        $detail->loadMissing('schedule');
        $oldShiftId = (int) $detail->schedule->shift_id;

        DB::transaction(function () use ($by, $detail, $data, $oldShiftId) {
            $schedule = $this->scheduleFor((int) $data['shift_id'], $detail->date->toDateString(), $by);

            $detail->update([
                'id_schedule' => $schedule->id,
                'status_lead' => $data['status_lead'],
            ]);

            if ($oldShiftId !== (int) $data['shift_id']) {
                $shiftName = MasterShift::find($data['shift_id'])->name;
                $this->notify(
                    $detail->id_employee,
                    $schedule->id,
                    "Shift Anda pada {$this->dateLabel($detail->date)} diubah menjadi {$shiftName}"
                );
            }
        });
    }

    public function removeError(ShiftScheduleDetail $detail): ?string
    {
        return $this->hasRequests($detail)
            ? 'Penugasan ini punya pengajuan izin/tukar shift, jadi tidak bisa dihapus.'
            : null;
    }

    // Menghapus penugasan, sehingga karyawan menjadi Libur pada tanggal itu
    public function remove(ShiftScheduleDetail $detail): void
    {
        $detail->loadMissing('schedule.masterShift');

        DB::transaction(function () use ($detail) {
            $this->notify(
                $detail->id_employee,
                $detail->id_schedule,
                "Jadwal shift {$detail->schedule->masterShift->name} Anda pada {$this->dateLabel($detail->date)} dibatalkan"
            );

            $detail->delete();
        });
    }

    // Apakah penugasan ini dirujuk pengajuan (sebagai pengaju atau sebagai rekan tukar)
    public function hasRequests(ShiftScheduleDetail $detail): bool
    {
        return ShiftRequest::where(fn ($query) => $query
                ->where('employee_id', $detail->id_employee)
                ->where('schedule_id', $detail->id_schedule))
            ->orWhere(fn ($query) => $query
                ->where('partner_id', $detail->id_employee)
                ->where('partner_schedule_id', $detail->id_schedule))
            ->exists();
    }

    // Menukar jadwal dua karyawan sesuai pengajuan tukar shift yang disetujui
    public function swapAssignments(ShiftRequest $request): void
    {
        $mine = ShiftScheduleDetail::where('id_schedule', $request->schedule_id)
            ->where('id_employee', $request->employee_id)
            ->first();

        $theirs = ShiftScheduleDetail::where('id_schedule', $request->partner_schedule_id)
            ->where('id_employee', $request->partner_id)
            ->first();

        if (! $mine || ! $theirs) {
            throw new DomainException('Jadwal sudah berubah, pengajuan ini tidak bisa disetujui.');
        }

        if (! $mine->date->isSameDay($theirs->date)) {
            throw new DomainException('Tanggal kedua jadwal tidak sama lagi, pengajuan ini tidak bisa disetujui.');
        }

        if ($mine->status_lead || $theirs->status_lead) {
            throw new DomainException('Leader shift tidak bisa tukar shift.');
        }

        DB::transaction(function () use ($mine, $theirs) {
            $mineSchedule = $mine->id_schedule;

            $mine->update(['id_schedule' => $theirs->id_schedule]);
            $theirs->update(['id_schedule' => $mineSchedule]);
        });
    }

    // Shift yang sedang berjalan pada $now (termasuk shift malam yang melewati tengah malam)
    public function running(Carbon $now): Collection
    {
        return $this->detailsOn([$now->copy()->subDay(), $now])
            ->filter(fn (ShiftScheduleDetail $detail) => $now->gte($this->startsAt($detail))
                && $now->lt($this->endsAt($detail)))
            ->sortBy(fn (ShiftScheduleDetail $detail) => $this->startsAt($detail)->timestamp)
            ->values();
    }

    // Shift hari ini yang belum dimulai
    public function upcomingToday(Carbon $now): Collection
    {
        return $this->detailsOn([$now])
            ->filter(fn (ShiftScheduleDetail $detail) => $this->startsAt($detail)->gt($now))
            ->sortBy(fn (ShiftScheduleDetail $detail) => $this->startsAt($detail)->timestamp)
            ->values();
    }

    // Satu baris jadwal adalah satu shift pada satu tanggal. Dicari lewat tanggal detailnya
    private function scheduleFor(int $shiftId, string $date, User $by): ShiftSchedule
    {
        return ShiftSchedule::where('shift_id', $shiftId)
            ->whereHas('details', fn ($query) => $query->where('date', $date))
            ->first()
            ?? ShiftSchedule::create([
                'shift_id' => $shiftId,
                'status' => 'terbit',
                'publish_by' => $by->id,
            ]);
    }

    private function detailsOn(array $dates): Collection
    {
        return ShiftScheduleDetail::with(self::DETAIL_RELATIONS)
            ->whereIn('date', array_map(fn (Carbon $date) => $date->toDateString(), $dates))
            ->get();
    }

    private function startsAt(ShiftScheduleDetail $detail): Carbon
    {
        return $detail->date->copy()->setTimeFromTimeString($detail->schedule->masterShift->start_date);
    }

    private function endsAt(ShiftScheduleDetail $detail): Carbon
    {
        $start = $this->startsAt($detail);
        $end = $detail->date->copy()->setTimeFromTimeString($detail->schedule->masterShift->end_date);

        return $end->lte($start) ? $end->addDay() : $end;
    }

    private function dateLabel(Carbon $date): string
    {
        return $date->copy()->locale('id')->translatedFormat('l, j M Y');
    }

    private function notify(int $employeeId, int $scheduleId, string $message): void
    {
        Notification::create([
            'id_employee' => $employeeId,
            'schedule_id' => $scheduleId,
            'status' => 'belum_dibaca',
            'message' => $message,
        ]);
    }
}
