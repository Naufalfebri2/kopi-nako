<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\LogRequest;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequestApproval
{
    public const STEP_LEADER = 'leader';
    public const STEP_SUPERVISOR = 'supervisor';

    private const RELATIONS = ['employee.user.role', 'logs.approver.role'];

    // Pengajuan Leader/Head dan Supervisor tidak perlu verifikasi Leader/Head
    public function needsLeader(ShiftRequest $request): bool
    {
        $request->loadMissing(self::RELATIONS);

        return $request->employee->user->hasRole(Role::KARYAWAN);
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
            self::STEP_LEADER => $user->hasRole(Role::LEADER),
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

    public function decide(User $user, ShiftRequest $request, bool $approve): void
    {
        $request->loadMissing(self::RELATIONS);

        DB::transaction(function () use ($user, $request, $approve) {
            $step = $this->awaitingStep($request);

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

            $this->notify($user, $request, $step, $approve);
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
            ! $this->needsLeader($request) => ['skipped', 'Tidak diperlukan'],
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

    private function notify(User $actor, ShiftRequest $request, ?string $step, bool $approve): void
    {
        $type = mb_strtolower($request->type_label);
        $requester = $request->employee;

        if (! $approve) {
            $this->notice($requester, $request, "Pengajuan {$type} Anda ditolak oleh {$actor->name}");
        } elseif ($step === self::STEP_SUPERVISOR) {
            $this->notice($requester, $request, "Pengajuan {$type} Anda disetujui oleh {$actor->name}");
        } else {
            $this->notice($requester, $request,
                "Pengajuan {$type} Anda sudah diverifikasi {$actor->name}, menunggu keputusan Supervisor");

            Employee::whereHas('user.role', fn ($q) => $q->where('name', Role::SUPERVISOR))
                ->get()
                ->each(fn (Employee $supervisor) => $this->notice($supervisor, $request,
                    "Pengajuan {$type} dari {$requester->user->name} menunggu keputusan Anda"));
        }
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
