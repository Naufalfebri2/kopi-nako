<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LogRequest;
use App\Models\MasterShift;
use App\Models\Notification;
use App\Models\ShiftRequest;
use App\Models\ShiftSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

// Data contoh untuk menguji Dashboard. Hapus dari DatabaseSeeder sebelum dipakai sungguhan.
class SampleActivitySeeder extends Seeder
{
    private array $shifts;
    private array $leaves;
    private User $supervisor;

    public function run(): void
    {
        $this->shifts = MasterShift::pluck('id', 'name')->all();
        $this->leaves = LeaveType::pluck('id', 'name')->all();
        $this->supervisor = $this->user('juandi');

        $alfi = $this->user('alfi');
        $fatah = $this->user('fatah');
        $hendri = $this->user('hendri');

        // Menunggu: Artie tukar shift dengan Inge
        $this->swapRequest('artie', 'Pagi', 'inge', 'Siang', RequestStatus::Menunggu,
            'Ada keperluan keluarga di pagi hari.');

        // Menunggu: Keanu izin sakit
        $this->leaveRequest('keanu', 'Pagi', 'Sakit', RequestStatus::Menunggu,
            'Kurang enak badan, butuh istirahat.');

        // Disetujui: Adam tukar shift dengan Nayla
        $approved = $this->swapRequest('adam', 'Siang', 'nayla', 'Malam', RequestStatus::Disetujui,
            'Bertukar shift atas kesepakatan bersama.');
        $this->log($approved, $hendri, RequestStatus::Disetujui, $this->day(0));
        $this->log($approved, $this->supervisor, RequestStatus::Disetujui, $this->day(1));

        // Disetujui: M. Haikal izin sakit
        $sick = $this->leaveRequest('haikal', 'Pagi', 'Sakit', RequestStatus::Disetujui,
            'Demam dan perlu ke dokter.');
        $this->log($sick, $alfi, RequestStatus::Disetujui, $this->day(1));
        $this->log($sick, $this->supervisor, RequestStatus::Disetujui, $this->day(2));

        // Ditolak: Rahma izin pribadi
        $rejected = $this->leaveRequest('rahma', 'Siang', 'Izin Pribadi', RequestStatus::Ditolak,
            'Menghadiri acara pribadi.');
        $this->log($rejected, $fatah, RequestStatus::Disetujui, $this->day(2));
        $this->log($rejected, $this->supervisor, RequestStatus::Ditolak, $this->day(3));

        // Notifikasi untuk Supervisor
        $juandi = $this->supervisor->employee;
        $messages = [
            'Artie mengajukan tukar shift dengan Inge',
            'Keanu mengajukan izin sakit',
            'Pengajuan tukar shift Adam disetujui',
            'Pengajuan izin Rahma ditolak',
        ];
        foreach ($messages as $message) {
            Notification::create([
                'id_employee' => $juandi->id,
                'status' => 'belum_dibaca',
                'message' => $message,
            ]);
        }
    }

    private function user(string $emailName): User
    {
        return User::where('email', $emailName.'@kopinako.test')->firstOrFail();
    }

    private function employee(string $emailName): Employee
    {
        return $this->user($emailName)->employee;
    }

    // Hari ke-$n pada minggu berjalan, tidak melewati hari ini
    private function day(int $n): Carbon
    {
        return now()->startOfWeek()
            ->addDays(min($n, now()->dayOfWeekIso - 1))
            ->setTime(9 + $n, 15);
    }

    private function schedule(Employee $employee, string $shift): ShiftSchedule
    {
        return ShiftSchedule::create([
            'id_employee' => $employee->id,
            'shift_id' => $this->shifts[$shift],
            'status' => 'terbit',
            'publish_by' => $this->supervisor->id,
        ]);
    }

    private function swapRequest(
        string $who, string $shift, string $partner, string $partnerShift,
        RequestStatus $status, string $reason,
    ): ShiftRequest {
        $employee = $this->employee($who);
        $partnerEmployee = $this->employee($partner);

        return ShiftRequest::create([
            'employee_id' => $employee->id,
            'schedule_id' => $this->schedule($employee, $shift)->id,
            'partner_id' => $partnerEmployee->id,
            'partner_schedule_id' => $this->schedule($partnerEmployee, $partnerShift)->id,
            'status' => $status,
            'reason' => $reason,
            'created_by' => $employee->id_user,
        ]);
    }

    private function leaveRequest(
        string $who, string $shift, string $leave, RequestStatus $status, string $reason,
    ): ShiftRequest {
        $employee = $this->employee($who);

        return ShiftRequest::create([
            'employee_id' => $employee->id,
            'schedule_id' => $this->schedule($employee, $shift)->id,
            'leave_id' => $this->leaves[$leave],
            'status' => $status,
            'reason' => $reason,
            'created_by' => $employee->id_user,
        ]);
    }

    private function log(ShiftRequest $request, User $by, RequestStatus $status, Carbon $at): void
    {
        (new LogRequest)->forceFill([
            'id_shiftrequest' => $request->id,
            'approve_by' => $by->id,
            'status' => $status,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }
}