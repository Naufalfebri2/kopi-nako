<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function build(Carbon $from, Carbon $to, ?int $jobId): array
    {
        $shifts = MasterShift::orderBy('start_date')->get();
        $employees = $this->employees($jobId);

        $shiftRows = $this->shiftRows($employees, $shifts, $from, $to);
        $requestRows = $this->requestRows($from, $to, $jobId);

        return [
            'shifts' => $shifts,
            'shiftRows' => $shiftRows,
            'shiftTotals' => $this->shiftTotals($shiftRows, $shifts),
            'requestRows' => $requestRows,
            'summary' => $this->summary($shiftRows, $requestRows),
            'jobName' => $jobId ? Job::find($jobId)?->name : null,
        ];
    }

    // Karyawan aktif, urut abjad, dengan filter jabatan bila ada
    private function employees(?int $jobId): Collection
    {
        return Employee::with(['user', 'job'])
            ->where('status', EmployeeStatus::Aktif)
            ->when($jobId, fn ($query) => $query->where('job_id', $jobId))
            ->get()
            ->sortBy(fn (Employee $employee) => mb_strtolower($employee->user->name))
            ->values();
    }

    // Jumlah penugasan per karyawan untuk tiap shift pada rentang tanggal
    private function shiftRows(Collection $employees, Collection $shifts, Carbon $from, Carbon $to): Collection
    {
        $details = ShiftScheduleDetail::with('schedule')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('id_employee', $employees->pluck('id'))
            ->get()
            ->groupBy('id_employee');

        return $employees->map(function (Employee $employee) use ($details, $shifts) {
            $mine = $details->get($employee->id, collect());
            $byShift = $mine->countBy(fn (ShiftScheduleDetail $detail) => $detail->schedule->shift_id);

            return [
                'employee' => $employee,
                'counts' => $shifts
                    ->mapWithKeys(fn (MasterShift $shift) => [$shift->id => $byShift->get($shift->id, 0)])
                    ->all(),
                'total' => $mine->count(),
            ];
        });
    }

    private function shiftTotals(Collection $rows, Collection $shifts): array
    {
        return [
            'counts' => $shifts
                ->mapWithKeys(fn (MasterShift $shift) => [
                    $shift->id => $rows->sum(fn (array $row) => $row['counts'][$shift->id]),
                ])
                ->all(),
            'total' => $rows->sum('total'),
        ];
    }

    // Rekap pengajuan per karyawan, berdasarkan tanggal pengajuan dibuat
    private function requestRows(Carbon $from, Carbon $to, ?int $jobId): Collection
    {
        $requests = ShiftRequest::with(['employee.user', 'employee.job'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($jobId, fn ($query) => $query->whereHas(
                'employee', fn ($employee) => $employee->where('job_id', $jobId)
            ))
            ->get();

        return $requests
            ->groupBy('employee_id')
            ->map(function (Collection $items) {
                $withStatus = fn (RequestStatus $status) => $items
                    ->filter(fn (ShiftRequest $request) => $request->status === $status)
                    ->count();

                return [
                    'employee' => $items->first()->employee,
                    'izin' => $items->whereNotNull('leave_id')->count(),
                    'tukar' => $items->whereNotNull('partner_id')->count(),
                    'menunggu' => $withStatus(RequestStatus::Menunggu),
                    'disetujui' => $withStatus(RequestStatus::Disetujui),
                    'ditolak' => $withStatus(RequestStatus::Ditolak),
                    'total' => $items->count(),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower($row['employee']->user->name))
            ->values();
    }

    private function summary(Collection $shiftRows, Collection $requestRows): array
    {
        return [
            'penugasan' => $shiftRows->sum('total'),
            'pengajuan' => $requestRows->sum('total'),
            'izin' => $requestRows->sum('izin'),
            'tukar' => $requestRows->sum('tukar'),
            'menunggu' => $requestRows->sum('menunggu'),
            'disetujui' => $requestRows->sum('disetujui'),
            'ditolak' => $requestRows->sum('ditolak'),
        ];
    }
}
