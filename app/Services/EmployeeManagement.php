<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleDetail;
use Illuminate\Support\Facades\DB;

class EmployeeManagement
{
    // NIK dan nomor telepon tidak diubah di sini; keduanya tetap seperti saat pendaftaran.
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->user->update([
                'name'    => $data['name'],
                'email'   => $data['email'],
                'id_role' => $data['id_role'],
            ]);

            $employee->update(['job_id' => $data['job_id']]);

            return $employee;
        });
    }

    // Alasan karyawan tidak boleh dihapus permanen, atau null bila boleh.
    public function deletionBlocker(Employee $employee): ?string
    {
        $hasSchedules = ShiftScheduleDetail::where('id_employee', $employee->id)->exists();

        $hasRequests = ShiftRequest::where('employee_id', $employee->id)
            ->orWhere('partner_id', $employee->id)
            ->exists();

        $hasPublished = ShiftSchedule::where('publish_by', $employee->id_user)->exists();

        if ($hasSchedules || $hasRequests || $hasPublished) {
            return 'Karyawan ini sudah punya riwayat jadwal atau pengajuan, jadi tidak bisa dihapus permanen. '
                .'Gunakan "Nonaktifkan" agar riwayatnya tetap utuh.';
        }

        return null;
    }

    // Hapus permanen untuk data yang salah masukkan. Panggil deletionBlocker() lebih dulu.
    public function delete(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $user = $employee->user;

            Notification::where('id_employee', $employee->id)->delete();
            $employee->delete();
            $user?->delete();
        });
    }

    // Riwayat jadwal dan pengajuan tetap utuh; karyawan hanya tidak lagi aktif.
    public function deactivate(Employee $employee): void
    {
        $employee->update(['status' => EmployeeStatus::Nonaktif]);
    }
}
