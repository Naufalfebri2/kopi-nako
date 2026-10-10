<?php

namespace App\Services;

use App\Services\ScheduleService;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LogRequest;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class RequestApproval
{
    public const STEP_LEADER = 'leader';
    public const STEP_SUPERVISOR = 'supervisor';

    private const RELATIONS = ['employee.user.role', 'logs.approver.role'];

    public function __construct(private ScheduleService $schedules)
    {
    }

    // Karyawan yang menjadi Leader pada jadwal (shift dan tanggal) milik pengaju
    public function leaderEmployeeIds(ShiftRequest $request): Collection
    {
        return ShiftScheduleDetail::where('id_schedule', $request->schedule_id)
            ->where('status_lead', true)
            ->pluck('id_employee');
    }

    // Pengajuan Karyawan diverifikasi Leader dulu, selama jadwalnya punya Leader
    public function needsLeader(ShiftRequest $request): bool
    {
        $request->loadMissing(self::RELATIONS);

        return $request->employee->user->hasRole(Role::KARYAWAN)
            && $this->leaderEmployeeIds($request)->isNotEmpty();
    }

    // Tahap yang sedang ditunggu, atau null kalau pengajuan sudah selesai
    public function awaitingStep(ShiftRequest $request): ?string
    {
        $request->loadMissing(self::RELATIONS);

        if ($request->status !== RequestStatus::Menunggu) {
            return null;
        }

        $leaderDone = $request->logs->contains(
            fn (LogRequest $log) => $log->approver?->hasRole(Role::LEADER)
        );

        return $this->needsLeader($request) && ! $leaderDone
            ? self::STEP_LEADER
            : self::STEP_SUPERVISOR;
    }

    public function canView(User $user, ShiftRequest $request): bool
    {
        return ! $user->hasRole(Role::KARYAWAN)
            || $request->employee_id === $user->employee?->id;
    }

    public function canDecide(User $user, ShiftRequest $request): bool
    {
        $request->loadMissing(self::RELATIONS);

        if ($request->employee->id_user === $user->id) {
            return false;
        }

        return match ($this->awaitingStep($request)) {
            self::STEP_LEADER => $user->hasRole(Role::LEADER)
                && $user->employee !== null
                && $this->leaderEmployeeIds($request)->contains($user->employee->id),
            self::STEP_SUPERVISOR => $user->hasRole(Role::SUPERVISOR),
            default => false,
        };
    }

    // Pengajuan menunggu yang saat ini boleh diputuskan oleh $user
    public function pendingFor(User $user): Collection
    {
        return ShiftRequest::with(self::RELATIONS)
            ->where('status', RequestStatus::Menunggu)
            ->get()
            ->filter(fn (ShiftRequest $request) => $this->canDecide($user, $request))
            ->values();
    }

    // Melempar DomainException bila tukar shift tidak bisa dijalankan lagi
    public function decide(User $user, ShiftRequest $request, bool $approve): void
    {
        $request->loadMissing(self::RELATIONS);

        DB::transaction(function () use ($user, $request, $approve) {
            $step = $this->awaitingStep($request);
            $date = $request->shift_date; // dibaca sebelum jadwal ditukar

            if ($approve && $step === self::STEP_SUPERVISOR && $request->partner_id !== null) {
                $this->schedules->swapAssignments($request);
            }

            LogRequest::create([
                'id_shiftrequest' => $request->id,
                'approve_by' => $user->id,
                'status' => $approve ? RequestStatus::Disetujui : RequestStatus::Ditolak,
            ]);

            if (! $approve) {
                $request->status = RequestStatus::Ditolak;
            } elseif ($step === self::STEP_SUPERVISOR) {
                $request->status = RequestStatus::Disetujui;
            }
            $request->save();

            $this->notify($user, $request, $step, $approve, $date);
        });
    }

    // Susunan tahap untuk tampilan stepper
    public function timeline(ShiftRequest $request): array
    {
        $request->loadMissing(self::RELATIONS);

        $leaderLog = $request->logs->first(fn (LogRequest $l) => $l->approver?->hasRole(Role::LEADER));
        $supervisorLog = $request->logs->first(fn (LogRequest $l) => $l->approver?->hasRole(Role::SUPERVISOR));
        $awaiting = $this->awaitingStep($request);

        $leaderStep = match (true) {
            ! $this->needsLeader($request) => ['skipped', $request->employee->user->hasRole(Role::KARYAWAN)
                ? 'Tidak ada Leader pada jadwal ini'
                : 'Tidak diperlukan'],
            $leaderLog !== null => [$this->logState($leaderLog), $this->logMeta($leaderLog)],
            $awaiting === self::STEP_LEADER => ['active', 'Menunggu verifikasi'],
            default => ['waiting', 'Belum dimulai'],
        };

        $supervisorStep = match (true) {
            $supervisorLog !== null => [$this->logState($supervisorLog), $this->logMeta($supervisorLog)],
            $awaiting === self::STEP_SUPERVISOR => ['active', 'Menunggu keputusan'],
            $request->status === RequestStatus::Ditolak => ['waiting', 'Tidak dilanjutkan'],
            default => ['waiting', 'Menunggu verifikasi Leader/Head'],
        };

        return [
            ['title' => 'Diajukan', 'state' => 'done', 'meta' => $request->employee->user->name],
            ['title' => 'Verifikasi Leader/Head', 'state' => $leaderStep[0], 'meta' => $leaderStep[1]],
            ['title' => 'Keputusan Supervisor', 'state' => $supervisorStep[0], 'meta' => $supervisorStep[1]],
        ];
    }

    private function logState(LogRequest $log): string
    {
        return $log->status === RequestStatus::Disetujui ? 'done' : 'rejected';
    }

    private function logMeta(LogRequest $log): string
    {
        return $log->approver?->name.' · '.$log->created_at->translatedFormat('j M, H.i');
    }

    private function notify(User $actor, ShiftRequest $request, ?string $step, bool $approve, ?Carbon $date): void
    {
        $type = mb_strtolower($request->type_label);
        $requester = $request->employee;
        $when = $date ? ' pada '.$date->copy()->locale('id')->translatedFormat('l, j M Y') : '';

        if (! $approve) {
            $this->notice($requester, $request, "Pengajuan {$type} Anda{$when} ditolak oleh {$actor->name}");

            return;
        }

        if ($step === self::STEP_SUPERVISOR) {
            $this->notice($requester, $request, "Pengajuan {$type} Anda{$when} disetujui oleh {$actor->name}");

            if ($request->partner_id !== null) {
                $request->loadMissing('partner.user');
                $this->notice($request->partner, $request,
                    "Jadwal Anda{$when} ditukar dengan {$requester->user->name}");
            }

            return;
        }

        $this->notice($requester, $request,
            "Pengajuan {$type} Anda{$when} sudah diverifikasi {$actor->name}, menunggu keputusan Supervisor");

        Employee::whereHas('user.role', fn ($q) => $q->where('name', Role::SUPERVISOR))
            ->get()
            ->each(fn (Employee $supervisor) => $this->notice($supervisor, $request,
                "Pengajuan {$type} dari {$requester->user->name}{$when} menunggu keputusan Anda"));
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
