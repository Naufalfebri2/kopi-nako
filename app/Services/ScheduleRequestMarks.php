<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use Illuminate\Support\Collection;

class ScheduleRequestMarks
{
    /**
     * Penanda status pengajuan per sel grid, dengan satu query pengajuan untuk seluruh minggu.
     * Tanggal sel diambil dari detail jadwal yang sudah dimuat (karyawan + schedule_id), jadi tanpa N+1.
     * Pengajuan yang lebih baru menimpa yang lebih lama pada sel yang sama.
     *
     * @param  Collection<int, ShiftScheduleDetail>  $details  Penugasan pada minggu yang tampil
     * @return array<int, array<string, array{kind: string, label: string}|null>>  [id_karyawan][Y-m-d]
     */
    public function forDetails(Collection $details): array
    {
        if ($details->isEmpty()) {
            return [];
        }

        $dates = $details->mapWithKeys(fn (ShiftScheduleDetail $detail) => [
            $detail->id_employee.'|'.$detail->id_schedule => $detail->date->toDateString(),
        ]);

        $requests = ShiftRequest::whereIn('employee_id', $details->pluck('id_employee')->unique()->all())
            ->whereIn('schedule_id', $details->pluck('id_schedule')->unique()->all())
            ->orderBy('id')
            ->get();

        $marks = [];

        foreach ($requests as $request) {
            $date = $dates->get($request->employee_id.'|'.$request->schedule_id);

            if ($date !== null) {
                $marks[$request->employee_id][$date] = $this->markFor($request);
            }
        }

        return $marks;
    }

    private function markFor(ShiftRequest $request): ?array
    {
        $isSwap = $request->partner_id !== null;

        return match ($request->status) {
            RequestStatus::Menunggu => [
                'kind' => 'menunggu',
                'label' => $isSwap ? 'Menunggu tukar' : 'Menunggu izin',
            ],
            RequestStatus::Ditolak => ['kind' => 'ditolak', 'label' => 'Ditolak'],
            // Izin disetujui menggantikan shift. Tukar disetujui tanpa penanda karena jadwal sudah berpindah.
            default => $isSwap ? null : ['kind' => 'izin', 'label' => 'Izin'],
        };
    }
}
