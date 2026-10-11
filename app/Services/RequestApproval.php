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
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequestApproval
{
    public const STEP_PARTNER = 'partner';
    public const STEP_LEADER = 'leader';
    public const STEP_SUPERVISOR = 'supervisor';

    private const RELATIONS = ['employee.user.role', 'partner.user', 'logs.approver.role'];

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

        if ($this->partnerPending($request)) {
            return self::STEP_PARTNER;
        }

        return $this->needsLeader($request) && $this->leaderLog($request) === null
            ? self::STEP_LEADER
            : self::STEP_SUPERVISOR;
    }

    // Karyawan boleh melihat pengajuan miliknya, atau yang menunjuk dirinya sebagai rekan tukar
    public function canView(User $user, ShiftRequest $request): bool
    {
        return ! $user->hasRole(Role::KARYAWAN)
            || $request->employee_id === $user->employee?->id
            || ($request->partner_id !== null && $request->partner_id === $user->employee?->id);
    }

    public function canDecide(User $user, ShiftRequest $request): bool
    {
        $request->loadMissing(self::RELATIONS);

        if ($request->employee->id_user === $user->id) {
            return false;
        }

        $isPartner = $request->partner_id !== null
            && $user->employee !== null
            && (int) $user->employee->id === (int) $request->partner_id;

        // Rekan tukar hanya memutuskan di tahapnya sendiri, tidak di tahap Leader atau Supervisor.
        return match ($this->awaitingStep($request)) {
            self::STEP_PARTNER => $isPartner,
            self::STEP_LEADER => ! $isPartner
                && $user->hasRole(Role::LEADER)
                && $user->employee !== null
                && $this->leaderEmployeeIds($request)->contains($user->employee->id),
            self::STEP_SUPERVISOR => ! $isPartner && $user->hasRole(Role::SUPERVISOR),
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

            if ($step === null) {
                throw new DomainException('Pengajuan ini sudah diputuskan.');
            }

            $date = $request->shift_date; // dibaca sebelum jadwal ditukar

            // Jadwal baru ditukar saat Supervisor menyetujui, setelah rekan dan Leader setuju.
            if ($approve && $step === self::STEP_SUPERVISOR && $request->partner_id !== null) {
                $this->schedules->swapAssignments($request);
            }

            LogRequest::create([
                'id_shiftrequest' => $request->id,
                'approve_by' => $user->id,
                'status' => $approve ? RequestStatus::Disetujui : RequestStatus::Ditolak,
            ]);
            $request->load('logs.approver.role');

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

        $awaiting = $this->awaitingStep($request);
        $rejected = $request->status === RequestStatus::Ditolak;
        $partnerLog = $this->partnerLog($request);
        $leaderLog = $this->leaderLog($request);
        $supervisorLog = $this->supervisorLog($request);

        $steps = [[
            'title' => 'Diajukan',
            'state' => 'done',
            'meta' => $request->employee->user->name
                .($request->created_at ? ' · '.$request->created_at->translatedFormat('j M, H.i') : ''),
        ]];

        if ($request->partner_id !== null) {
            $partnerName = $request->partner?->user?->name ?? 'rekan tukar';

            [$state, $meta] = match (true) {
                $partnerLog !== null => [$this->logState($partnerLog), $this->logMeta($partnerLog)],
                $awaiting === self::STEP_PARTNER => ['active', "Menunggu persetujuan {$partnerName}"],
                default => ['skipped', 'Tidak tercatat'], // pengajuan lama, sebelum ada tahap rekan
            };

            $steps[] = ['title' => 'Persetujuan Rekan Tukar', 'state' => $state, 'meta' => $meta];
        }

        [$state, $meta] = match (true) {
            ! $this->needsLeader($request) => ['skipped', $request->employee->user->hasRole(Role::KARYAWAN)
                ? 'Tidak ada Leader pada jadwal ini'
                : 'Tidak diperlukan'],
            $leaderLog !== null => [$this->logState($leaderLog), $this->logMeta($leaderLog)],
            $awaiting === self::STEP_LEADER => ['active', 'Menunggu verifikasi'],
            $rejected => ['waiting', 'Tidak dilanjutkan'],
            default => ['waiting', 'Belum dimulai'],
        };
        $steps[] = ['title' => 'Verifikasi Leader/Head', 'state' => $state, 'meta' => $meta];

        [$state, $meta] = match (true) {
            $supervisorLog !== null => [$this->logState($supervisorLog), $this->logMeta($supervisorLog)],
            $awaiting === self::STEP_SUPERVISOR => ['active', 'Menunggu keputusan'],
            $rejected => ['waiting', 'Tidak dilanjutkan'],
            $awaiting === self::STEP_PARTNER => ['waiting', 'Menunggu persetujuan rekan tukar'],
            default => ['waiting', 'Menunggu verifikasi Leader/Head'],
        };
        $steps[] = ['title' => 'Keputusan Supervisor', 'state' => $state, 'meta' => $meta];

        return $steps;
    }

    // Tahap rekan masih ditunggu bila ada rekan dan belum ada satu pun keputusan.
    // Pengajuan lama yang sudah diproses tanpa tahap rekan tidak ditarik mundur.
    private function partnerPending(ShiftRequest $request): bool
    {
        return $request->partner_id !== null && $request->logs->isEmpty();
    }

    // Log milik rekan dikenali dari akunnya, bukan dari role, karena rekan bisa berrole apa pun.
    private function partnerLog(ShiftRequest $request): ?LogRequest
    {
        $partnerUserId = $request->partner?->id_user;

        if ($request->partner_id === null || $partnerUserId === null) {
            return null;
        }

        return $request->logs->first(fn (LogRequest $log) => (int) $log->approve_by === (int) $partnerUserId);
    }

    private function leaderLog(ShiftRequest $request): ?LogRequest
    {
        return $this->nonPartnerLogs($request)
            ->first(fn (LogRequest $log) => $log->approver?->hasRole(Role::LEADER));
    }

    private function supervisorLog(ShiftRequest $request): ?LogRequest
    {
        return $this->nonPartnerLogs($request)
            ->first(fn (LogRequest $log) => $log->approver?->hasRole(Role::SUPERVISOR));
    }

    private function nonPartnerLogs(ShiftRequest $request): Collection
    {
        $partnerLog = $this->partnerLog($request);

        return $request->logs->reject(fn (LogRequest $log) => $partnerLog !== null && $log->id === $partnerLog->id);
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
            $by = $step === self::STEP_PARTNER ? "{$actor->name} (rekan tukar)" : $actor->name;
            $this->notice($requester, $request, "Pengajuan {$type} Anda{$when} ditolak oleh {$by}");

            return;
        }

        if ($step === self::STEP_SUPERVISOR) {
            $this->notice($requester, $request, "Pengajuan {$type} Anda{$when} disetujui oleh {$actor->name}");

            if ($request->partner_id !== null) {
                $this->notice($request->partner, $request,
                    "Jadwal Anda{$when} ditukar dengan {$requester->user->name}");
            }

            return;
        }

        $next = $this->needsLeader($request) && $this->leaderLog($request) === null
            ? 'verifikasi Leader/Head'
            : 'keputusan Supervisor';
        $who = $step === self::STEP_PARTNER ? "disetujui rekan tukar {$actor->name}" : "sudah diverifikasi {$actor->name}";

        $this->notice($requester, $request, "Pengajuan {$type} Anda{$when} {$who}, menunggu {$next}");

        $recipients = $next === 'verifikasi Leader/Head'
            ? Employee::whereIn('id', $this->leaderEmployeeIds($request))->get()
            : Employee::whereHas('user.role', fn ($q) => $q->where('name', Role::SUPERVISOR))->get();

        $recipients->each(fn (Employee $to) => $this->notice($to, $request,
            "Pengajuan {$type} dari {$requester->user->name}{$when} menunggu ".($next === 'verifikasi Leader/Head'
                ? 'verifikasi Anda'
                : 'keputusan Anda')));
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
