<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LogRequest;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'stats' => $this->stats($user),
            'chart' => $this->weeklyActivity($user),
            'requests' => $this->latestRequests($user),
            'notifications' => $this->notifications($user),
            'running' => $this->schedules->running(now()),
            'upcoming' => $this->schedules->upcomingToday(now()),
        ]);
    }

    // Karyawan hanya melihat pengajuannya sendiri
    private function requestQuery(User $user): Builder
    {
        return ShiftRequest::query()->when(
            $user->hasRole(Role::KARYAWAN),
            fn ($query) => $query->where('employee_id', $user->employee?->id ?? 0)
        );
    }

    private function stats(User $user): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return [
            'aktif' => Employee::where('status', EmployeeStatus::Aktif)->count(),
            'nonaktif' => Employee::where('status', EmployeeStatus::Nonaktif)->count(),
            'menunggu' => $this->requestQuery($user)
                ->where('status', RequestStatus::Menunggu)
                ->count(),
            'disetujui_bulan_ini' => $this->requestQuery($user)
                ->where('status', RequestStatus::Disetujui)
                ->whereHas('logs', fn ($q) => $q
                    ->where('status', RequestStatus::Disetujui)
                    ->whereBetween('created_at', [$monthStart, $monthEnd]))
                ->count(),
        ];
    }

    // Jumlah aktivitas persetujuan per hari pada minggu berjalan (Senin sampai Minggu)
    private function weeklyActivity(User $user): array
    {
        $start = now()->startOfWeek();

        $perDay = LogRequest::query()
            ->when(
                $user->hasRole(Role::KARYAWAN),
                fn ($query) => $query->whereHas(
                    'shiftRequest',
                    fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0)
                )
            )
            ->whereBetween('created_at', [$start, $start->copy()->endOfWeek()])
            ->get(['created_at'])
            ->countBy(fn (LogRequest $log) => $log->created_at->toDateString());

        $days = [];
        foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $i => $label) {
            $date = $start->copy()->addDays($i)->toDateString();
            $days[] = ['label' => $label, 'value' => $perDay->get($date, 0)];
        }

        $max = max(array_column($days, 'value')) ?: 1;

        return array_map(fn ($day) => $day + ['percent' => round($day['value'] / $max * 100)], $days);
    }

    private function latestRequests(User $user): Collection
    {
        return $this->requestQuery($user)
            ->with(['employee.user', 'partnerSchedule.masterShift'])
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function notifications(User $user): Collection
    {
        return $user->employee
            ? $user->employee->notifications()->latest('id')->limit(5)->get()
            : collect();
    }
}
