<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Mail\ScheduleMail;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\ShiftScheduleDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ScheduleDelivery
{
    // Mingguan: Senin sampai Minggu pada minggu yang memuat $date. Bulanan: seluruh bulan $date.
    public function range(string $periode, Carbon $date): array
    {
        return $periode === 'bulanan'
            ? [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()]
            : [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
    }

    public function label(Carbon $from, Carbon $to): string
    {
        return $from->copy()->locale('id')->translatedFormat('j M Y')
            .' – '.$to->copy()->locale('id')->translatedFormat('j M Y');
    }

    /**
     * Mengirim jadwal pribadi (PDF) ke setiap karyawan aktif yang punya jadwal pada periode itu,
     * lewat notifikasi di aplikasi dan email.
     *
     * @return array{sent: int, failed: int}
     */
    public function send(string $periode, Carbon $date): array
    {
        [$from, $to] = $this->range($periode, $date);
        $label = $this->label($from, $to);

        $details = ShiftScheduleDetail::with('schedule.masterShift')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->groupBy('id_employee');

        $employees = Employee::with('user')
            ->where('status', EmployeeStatus::Aktif)
            ->whereIn('id', $details->keys())
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($employees as $employee) {
            $email = $employee->user?->email;

            // Notifikasi di aplikasi. Tanpa event, supaya email teks biasa tidak ikut terkirim.
            Notification::withoutEvents(fn () => Notification::create([
                'id_employee' => $employee->id,
                'schedule_id' => null,
                'request_id' => null,
                'status' => 'belum_dibaca',
                'message' => "Jadwal shift Anda untuk {$label} sudah terbit. PDF dikirim ke email Anda.",
            ]));

            if (! $email) {
                $failed++;
                continue;
            }

            try {
                $pdf = Pdf::loadView('jadwal.pdf', [
                    'name' => $employee->user->name,
                    'periodLabel' => $label,
                    'rows' => $details->get($employee->id),
                ])->setPaper('a4', 'portrait')->output();

                Mail::to($email)->send(new ScheduleMail($employee->user->name, $label, $pdf));
                $sent++;
            } catch (Throwable $e) {
                // Hanya nama kelas yang dicatat.
                Log::warning('Pengiriman PDF jadwal gagal.', ['exception' => $e::class]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
