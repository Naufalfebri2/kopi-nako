<?php

namespace App\Services;

use App\Services\Division;
use App\Services\RequestApproval;
use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequestSubmission
{
    private const HORIZON_DAYS = 45;

    public function __construct(private RequestApproval $approval, private Division $divisions)
    {
    }

    // Jadwal mendatang milik karyawan, beserta rekan sedivisi yang bisa diajak tukar shift
    public function options(Employee $employee): Collection
    {
        $employee->loadMissing('job');
        $ownJob = $employee->job?->name;
        $busy = $this->busyKeys();

        $own = ShiftScheduleDetail::with('schedule.masterShift')
            ->where('id_employee', $employee->id)
            ->whereBetween('date', [today()->toDateString(), today()->addDays(self::HORIZON_DAYS)->toDateString()])
            ->orderBy('date')
            ->get()
            ->reject(fn (ShiftScheduleDetail $detail) => $busy->contains($this->key($detail->id_employee, $detail->id_schedule)))
            ->values();

        $candidates = ShiftScheduleDetail::with(['schedule.masterShift', 'employee.user', 'employee.job'])
            ->whereIn('date', $own->map(fn (ShiftScheduleDetail $d) => $d->date->toDateString())->unique()->all())
            ->where('status_lead', false)
            ->where('id_employee', '!=', $employee->id)
            ->whereHas('employee', fn ($query) => $query->where('status', EmployeeStatus::Aktif))
            ->get()
            ->reject(fn (ShiftScheduleDetail $detail) => $busy->contains($this->key($detail->id_employee, $detail->id_schedule)))
            ->filter(fn (ShiftScheduleDetail $detail) => $this->divisions->same($ownJob, $detail->employee->job?->name))
            ->groupBy(fn (ShiftScheduleDetail $detail) => $detail->date->toDateString());

        return $own->map(function (ShiftScheduleDetail $detail) use ($candidates) {
            $shift = $detail->schedule->masterShift;
            $date = $detail->date->copy()->locale('id');

            $partners = $detail->status_lead
                ? []
                : $candidates->get($detail->date->toDateString(), collect())
                    ->filter(fn (ShiftScheduleDetail $other) => (int) $other->schedule->shift_id !== (int) $detail->schedule->shift_id)
                    ->map(fn (ShiftScheduleDetail $other) => [
                        'id' => $other->id,
                        'label' => $other->employee->user->name.' — '.$other->schedule->masterShift->name
                            .' ('.$other->schedule->masterShift->time_range.')',
                    ])
                    ->values()
                    ->all();

            return [
                'id' => $detail->id,
                'label' => $date->translatedFormat('D, j M Y').' · '.$shift->name.' ('.$shift->time_range.')',
                // Dipisah untuk empat isian terpisah di form Ajukan
                'hari' => $date->translatedFormat('l'),
                'tanggal' => $date->translatedFormat('j M'),
                'tahun' => $date->format('Y'),
                'shift' => $shift->name.' ('.$shift->time_range.')',
                'canSwap' => ! $detail->status_lead,
                'partners' => $partners,
            ];
        });
    }

    // Melempar DomainException bila pengajuan tidak valid
    public function submit(User $user, array $data): ShiftRequest
    {
        $employee = $user->employee
            ?? throw new DomainException('Akun Anda belum terdaftar sebagai karyawan.');

        $own = ShiftScheduleDetail::with('schedule.masterShift')->find($data['detail_id']);

        if (! $own || (int) $own->id_employee !== (int) $employee->id) {
            throw new DomainException('Jadwal tidak ditemukan.');
        }

        if ($own->date->lt(today())) {
            throw new DomainException('Jadwal ini sudah lewat.');
        }

        $busy = $this->busyKeys();

        if ($busy->contains($this->key($own->id_employee, $own->id_schedule))) {
            throw new DomainException('Jadwal ini sudah punya pengajuan yang menunggu.');
        }

        $isSwap = $data['jenis'] === 'tukar';
        $partner = $isSwap ? $this->validPartner($own, $employee, $data['partner_detail_id'] ?? null, $busy) : null;

        return DB::transaction(function () use ($user, $employee, $own, $partner, $isSwap, $data) {
            $request = ShiftRequest::create([
                'employee_id' => $employee->id,
                'schedule_id' => $own->id_schedule,
                'leave_id' => $isSwap ? null : $data['leave_id'],
                'partner_id' => $partner?->id_employee,
                'partner_schedule_id' => $partner?->id_schedule,
                'status' => RequestStatus::Menunggu,
                'reason' => $data['reason'],
                'created_by' => $user->id,
            ]);

            $this->notifySubmitted($employee, $request, $own, $partner);

            return $request;
        });
    }

    private function validPartner(ShiftScheduleDetail $own, Employee $employee, $partnerDetailId, Collection $busy): ShiftScheduleDetail
    {
        if ($own->status_lead) {
            throw new DomainException('Leader pada shift ini tidak bisa tukar shift.');
        }

        $partner = ShiftScheduleDetail::with(['schedule.masterShift', 'employee.user', 'employee.job'])->find($partnerDetailId);

        if (! $partner) {
            throw new DomainException('Rekan tidak ditemukan.');
        }

        if ((int) $partner->id_employee === (int) $employee->id) {
            throw new DomainException('Tidak bisa memilih diri sendiri.');
        }

        if (! $partner->date->isSameDay($own->date)) {
            throw new DomainException('Rekan harus bertugas pada tanggal yang sama.');
        }

        if ((int) $partner->schedule->shift_id === (int) $own->schedule->shift_id) {
            throw new DomainException('Rekan berada di shift yang sama.');
        }

        if ($partner->status_lead) {
            throw new DomainException('Leader tidak bisa dipilih untuk tukar shift.');
        }

        if ($partner->employee->status !== EmployeeStatus::Aktif) {
            throw new DomainException('Rekan yang dipilih tidak aktif.');
        }

        $employee->loadMissing('job');

        if (! $this->divisions->same($employee->job?->name, $partner->employee->job?->name)) {
            throw new DomainException('Rekan harus berasal dari divisi yang sama.');
        }

        if ($busy->contains($this->key($partner->id_employee, $partner->id_schedule))) {
            throw new DomainException('Rekan yang dipilih sudah punya pengajuan yang menunggu.');
        }

        return $partner;
    }

    // Kunci "karyawan:jadwal" untuk jadwal yang sedang punya pengajuan menunggu
    private function busyKeys(): Collection
    {
        return ShiftRequest::where('status', RequestStatus::Menunggu)
            ->get(['employee_id', 'schedule_id', 'partner_id', 'partner_schedule_id'])
            ->flatMap(fn (ShiftRequest $request) => array_filter([
                $this->key($request->employee_id, $request->schedule_id),
                $request->partner_id ? $this->key($request->partner_id, $request->partner_schedule_id) : null,
            ]))
            ->values();
    }

    private function key(int|string $employeeId, int|string $scheduleId): string
    {
        return $employeeId.':'.$scheduleId;
    }

    private function notifySubmitted(Employee $employee, ShiftRequest $request, ShiftScheduleDetail $own, ?ShiftScheduleDetail $partner): void
    {
        $employee->loadMissing('user');
        $date = $own->date->copy()->locale('id')->translatedFormat('l, j M Y');

        // Tukar shift: tahap pertama adalah rekan. Leader dan Supervisor baru diberi tahu
        // oleh RequestApproval setelah rekan menyetujui.
        if ($partner) {
            $this->notice($partner->employee, $request,
                "{$employee->user->name} mengajukan tukar shift dengan Anda pada {$date}, menunggu persetujuan Anda");

            return;
        }

        $toLeader = $this->approval->needsLeader($request);

        $recipients = $toLeader
            ? Employee::whereIn('id', $this->approval->leaderEmployeeIds($request))->get()
            : Employee::whereHas('user.role', fn ($query) => $query->where('name', Role::SUPERVISOR))->get();

        $action = $toLeader ? 'verifikasi Anda' : 'keputusan Anda';

        $recipients
            ->reject(fn (Employee $recipient) => $recipient->id === $employee->id)
            ->each(fn (Employee $recipient) => $this->notice($recipient, $request,
                "{$employee->user->name} mengajukan izin untuk {$date}, menunggu {$action}"));
    }

    private function notice(Employee $to, ShiftRequest $request, string $message): void
    {
        Notification::create([
            'id_employee' => $to->id,
            'schedule_id' => $request->schedule_id,
            'request_id' => $request->id,
            'status' => 'belum_dibaca',
            'message' => $message,
        ]);
    }
}
