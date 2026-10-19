<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Rules\ClearReason;
use App\Services\RequestApproval;
use App\Services\RequestSubmission;
use App\Services\ScheduleService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ShiftRequestController extends Controller
{
    public function __construct(
        private RequestApproval $approval,
        private RequestSubmission $submission,
        private ScheduleService $schedule,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $scoped = $this->scopedQuery($user);

        $actionableIds = $this->approval->pendingFor($user)->pluck('id');
        $isSupervisor = $user->hasRole(Role::SUPERVISOR);

        return view('pengajuan.index', [
            'requests' => $this->listing($request, $scoped),
            'stats' => $this->stats($scoped),
            'actionableIds' => $actionableIds,
            'isSupervisor' => $isSupervisor,
            'readyCount' => $isSupervisor ? $actionableIds->count() : 0,
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ] + $this->submitData($user));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:izin,tukar'],
            'detail_id' => ['required', 'integer'],
            'leave_id' => ['required_if:jenis,izin', 'nullable', 'integer', 'exists:leave_type,id'],
            'partner_detail_id' => ['required_if:jenis,tukar', 'nullable', 'integer'],
            'reason' => ['required', 'string', 'max:500', new ClearReason],
        ], [
            'jenis.required' => 'Pilih jenis pengajuan.',
            'detail_id.required' => 'Pilih jadwal Anda.',
            'leave_id.required_if' => 'Pilih jenis izin.',
            'partner_detail_id.required_if' => 'Pilih rekan untuk tukar shift.',
            'reason.required' => 'Alasan wajib diisi.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ]);

        try {
            $this->submission->submit($request->user(), $data);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('pengajuan.index')->with('notice', 'Pengajuan berhasil dikirim.');
    }

    // Isi panel detail (dimuat lewat fetch). Stepper hanya untuk pengaju.
    public function show(Request $request, ShiftRequest $shiftRequest): View
    {
        $user = $request->user();
        abort_unless($this->approval->canView($user, $shiftRequest), 403);

        $shiftRequest->load([
            'employee.user.role', 'schedule.masterShift', 'schedule.firstDetail',
            'partner.user', 'partnerSchedule.masterShift', 'leaveType',
        ]);

        $showStepper = $user->employee?->id === $shiftRequest->employee_id;

        return view('pengajuan._detail', [
            'req' => $shiftRequest,
            'showStepper' => $showStepper,
            'steps' => $showStepper ? $this->approval->timeline($shiftRequest) : [],
            'canDecide' => $this->approval->canDecide($user, $shiftRequest),
            'awaiting' => $this->approval->awaitingStep($shiftRequest),
        ]);
    }

    public function decide(Request $request, ShiftRequest $shiftRequest): RedirectResponse
    {
        $data = $request->validate(['keputusan' => ['required', 'in:setujui,tolak']]);
        $user = $request->user();

        abort_unless($this->approval->canDecide($user, $shiftRequest), 403);

        $approve = $data['keputusan'] === 'setujui';
        $step = $this->approval->awaitingStep($shiftRequest);

        try {
            $this->approval->decide($user, $shiftRequest, $approve);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $notice = match (true) {
            ! $approve && $step === RequestApproval::STEP_PARTNER => 'Anda menolak tukar shift. Pengajuan ditolak.',
            ! $approve => 'Pengajuan ditolak.',
            $step === RequestApproval::STEP_PARTNER => 'Anda menyetujui tukar shift. Pengajuan diteruskan ke tahap berikutnya.',
            $step === RequestApproval::STEP_LEADER => 'Pengajuan diverifikasi dan diteruskan ke Supervisor.',
            $shiftRequest->partner_id !== null => 'Pengajuan disetujui dan jadwal kedua karyawan ditukar.',
            default => 'Pengajuan disetujui.',
        };

        return back()->with('notice', $notice);
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::SUPERVISOR), 403);

        $items = $this->approval->pendingFor($user);
        $approved = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $this->approval->decide($user, $item, true);
                $approved++;
            } catch (DomainException) {
                $failed++;
            }
        }

        $notice = $items->isEmpty()
            ? 'Tidak ada pengajuan yang siap disetujui.'
            : "{$approved} pengajuan disetujui.".($failed ? " {$failed} gagal karena jadwal sudah berubah." : '');

        return back()->with('notice', $notice);
    }

    // Daftar pengajuan dengan filter status, jenis, dan bulan, 10 per halaman
    private function listing(Request $request, Builder $scoped): LengthAwarePaginator
    {
        $range = $this->monthRange($request->query('bulan'));

        return (clone $scoped)
            ->with([
                'employee.user.role', 'schedule.masterShift', 'schedule.firstDetail',
                'partnerSchedule.masterShift', 'leaveType',
            ])
            ->when(
                RequestStatus::tryFrom((string) $request->query('status')),
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when($request->query('jenis') === 'izin', fn ($query) => $query->whereNotNull('leave_id'))
            ->when($request->query('jenis') === 'tukar', fn ($query) => $query->whereNotNull('partner_id'))
            ->when($range, fn ($query, $between) => $query->whereBetween('created_at', $between))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
    }

    // Jumlah pengajuan per status untuk kartu ringkasan
    private function stats(Builder $scoped): array
    {
        $counts = (clone $scoped)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => $counts->sum(),
            'disetujui' => $counts->get(RequestStatus::Disetujui->value, 0),
            'ditolak' => $counts->get(RequestStatus::Ditolak->value, 0),
            'menunggu' => $counts->get(RequestStatus::Menunggu->value, 0),
        ];
    }

    // Rentang tanggal untuk filter bulan, atau null bila tidak difilter
    private function monthRange(?string $month): ?array
    {
        return match ($month) {
            'ini' => [now()->startOfMonth(), now()->endOfMonth()],
            'lalu' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => null,
        };
    }

    // Data untuk form Ajukan Baru: jadwal milik pengguna dan rekan yang bisa diajak tukar
    private function submitData(User $user): array
    {
        $canSubmit = $user->employee !== null;
        $options = $canSubmit ? $this->submission->options($user->employee) : collect();

        return [
            'canSubmit' => $canSubmit,
            'options' => $options,
            'submitMap' => $options->mapWithKeys(fn (array $option) => [
                $option['id'] => ['canSwap' => $option['canSwap'], 'partners' => $option['partners']],
            ])->all(),
        ];
    }

    // Supervisor: semua. Leader: dirinya dan sedivisi. Karyawan: dirinya.
    // Ketiganya ditambah pengajuan yang menunjuk dirinya sebagai rekan tukar.
    private function scopedQuery(User $user): Builder
    {
        $visible = $this->schedule->visibleEmployeeIds($user);

        if ($visible === null) {
            return ShiftRequest::query();
        }

        $employeeId = $user->employee?->id ?? 0;

        return ShiftRequest::query()->where(fn ($q) => $q
            ->whereIn('employee_id', $visible)
            ->orWhere('partner_id', $employeeId));
    }
}
