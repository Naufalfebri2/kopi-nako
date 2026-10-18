<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use App\Services\Concerns\ManagesAssignments;
use App\Services\Division;
use App\Services\ScheduleRequestMarks;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleService
{
    use ManagesAssignments;

    private const DETAIL_RELATIONS = ['schedule.masterShift', 'employee.user', 'employee.job'];

    // Tujuh hari (Senin sampai Minggu) pada minggu yang memuat $date
    public function weekDays(Carbon $date): Collection
    {
        $start = $date->copy()->startOfWeek();

        return collect(range(0, 6))->map(fn (int $i) => $start->copy()->addDays($i));
    }

    // Id karyawan yang boleh dilihat $viewer, atau null bila boleh melihat semuanya (Supervisor).
    // Leader/Head: dirinya dan karyawan sedivisi. Karyawan: hanya dirinya.
    public function visibleEmployeeIds(User $viewer): ?Collection
    {
        if ($viewer->hasRole(Role::SUPERVISOR)) {
            return null;
        }

        $own = $viewer->employee;

        if (! $own) {
            return collect();
        }

        if (! $viewer->hasRole(Role::LEADER)) {
            return collect([$own->id]);
        }

        $own->loadMissing('job');
        $division = app(Division::class)->of($own->job?->name);

        if ($division === null) {
            return collect([$own->id]);
        }

        return Employee::with('job')
            ->get()
            ->filter(fn (Employee $employee) => $employee->id === $own->id
                || app(Division::class)->of($employee->job?->name) === $division)
            ->pluck('id')
            ->values();
    }

    // Satu baris per karyawan aktif, berisi penugasan untuk tiap hari (null = libur).
    // Untuk Supervisor, tiap baris juga membawa 'marks': penanda status pengajuan per hari.
    public function weeklyRows(Collection $days, ?int $jobId, ?User $viewer = null): Collection
    {
        $visible = $viewer ? $this->visibleEmployeeIds($viewer) : null;

        $all = ShiftScheduleDetail::with('schedule.masterShift')
            ->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->when($visible, fn ($query) => $query->whereIn('id_employee', $visible->all()))
            ->get();

        $details = $all->groupBy('id_employee');
        $marks = $viewer?->hasRole(Role::SUPERVISOR)
            ? app(ScheduleRequestMarks::class)->forDetails($all)
            : [];

        return Employee::with(['user.role', 'job'])
            ->where('status', EmployeeStatus::Aktif)
            ->when($visible !== null, fn ($query) => $query->whereIn('id', $visible->all()))
            ->when($jobId, fn ($query) => $query->where('job_id', $jobId))
            ->get()
            ->sortBy(fn (Employee $employee) => mb_strtolower($employee->user->name))
            ->values()
            ->map(function (Employee $employee) use ($details, $days, $marks) {
                $mine = ($details->get($employee->id) ?? collect())
                    ->keyBy(fn (ShiftScheduleDetail $detail) => $detail->date->toDateString());

                return [
                    'employee' => $employee,
                    'cells' => $days->map(fn (Carbon $day) => $mine->get($day->toDateString()))->all(),
                    'marks' => $days->map(fn (Carbon $day) => $marks[$employee->id][$day->toDateString()] ?? null)->all(),
                ];
            });
    }

    // Satu kolom per shift, berisi penugasan pada tanggal tersebut (Leader di urutan atas)
    public function dayBoard(Carbon $date, ?int $jobId, ?User $viewer = null): Collection
    {
        $visible = $viewer ? $this->visibleEmployeeIds($viewer) : null;

        $details = ShiftScheduleDetail::with(self::DETAIL_RELATIONS)
            ->where('date', $date->toDateString())
            ->when($visible !== null, fn ($query) => $query->whereIn('id_employee', $visible->all()))
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
}
