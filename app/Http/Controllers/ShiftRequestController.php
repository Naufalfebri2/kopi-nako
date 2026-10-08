<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Services\RequestApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftRequestController extends Controller
{
    public function __construct(private RequestApproval $approval)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $scoped = $this->scopedQuery($user);

        $counts = (clone $scoped)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $requests = (clone $scoped)
            ->with(['employee.user.role', 'schedule.masterShift', 'partnerSchedule.masterShift', 'leaveType'])
            ->when(
                RequestStatus::tryFrom((string) $request->query('status')),
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when($request->query('jenis') === 'izin', fn ($query) => $query->whereNotNull('leave_id'))
            ->when($request->query('jenis') === 'tukar', fn ($query) => $query->whereNotNull('partner_id'))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $actionableIds = $this->approval->pendingFor($user)->pluck('id');
        $isSupervisor = $user->hasRole(Role::SUPERVISOR);

        return view('pengajuan.index', [
            'requests' => $requests,
            'stats' => [
                'total' => $counts->sum(),
                'disetujui' => $counts->get(RequestStatus::Disetujui->value, 0),
                'ditolak' => $counts->get(RequestStatus::Ditolak->value, 0),
                'menunggu' => $counts->get(RequestStatus::Menunggu->value, 0),
            ],
            'actionableIds' => $actionableIds,
            'isSupervisor' => $isSupervisor,
            'readyCount' => $isSupervisor ? $actionableIds->count() : 0,
        ]);
    }

    // Isi panel detail (dimuat lewat fetch)
    public function show(Request $request, ShiftRequest $shiftRequest): View
    {
        $user = $request->user();
        abort_unless($this->approval->canView($user, $shiftRequest), 403);

        $shiftRequest->load([
            'employee.user.role', 'schedule.masterShift',
            'partner.user', 'partnerSchedule.masterShift', 'leaveType',
        ]);

        return view('pengajuan._detail', [
            'req' => $shiftRequest,
            'steps' => $this->approval->timeline($shiftRequest),
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
        $this->approval->decide($user, $shiftRequest, $approve);

        $notice = match (true) {
            ! $approve => 'Pengajuan ditolak.',
            $step === RequestApproval::STEP_LEADER => 'Pengajuan diverifikasi dan diteruskan ke Supervisor.',
            default => 'Pengajuan disetujui.',
        };

        return back()->with('notice', $notice);
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::SUPERVISOR), 403);

        $items = $this->approval->pendingFor($user);
        foreach ($items as $item) {
            $this->approval->decide($user, $item, true);
        }

        return back()->with('notice', $items->isEmpty()
            ? 'Tidak ada pengajuan yang siap disetujui.'
            : "{$items->count()} pengajuan disetujui.");
    }

    // Karyawan hanya melihat pengajuannya sendiri
    private function scopedQuery(User $user): Builder
    {
        return ShiftRequest::query()->when(
            $user->hasRole(Role::KARYAWAN),
            fn ($query) => $query->where('employee_id', $user->employee?->id ?? 0)
        );
    }
}
