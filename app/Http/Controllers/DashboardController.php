<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LogRequest;
use App\Models\ShiftRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard', [
            'stats' => $this->stats(),
            'chart' => $this->weeklyActivity(),
            'requests' => $this->latestRequests(),
            'notifications' => $this->notifications($request),
        ]);
    }

    private function stats(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return [
            'aktif' => Employee::where('status', EmployeeStatus::Aktif)->count(),
            'nonaktif' => Employee::where('status', EmployeeStatus::Nonaktif)->count(),
            'menunggu' => ShiftRequest::where('status', RequestStatus::Menunggu)->count(),
            'disetujui_bulan_ini' => ShiftRequest::where('status', RequestStatus::Disetujui)
                ->whereHas('logs', fn ($q) => $q
                    ->where('status', RequestStatus::Disetujui)
                    ->whereBetween('created_at', [$monthStart, $monthEnd]))
                ->count(),
        ];
    }

    // Jumlah aktivitas persetujuan per hari pada minggu berjalan (Senin sampai Minggu)
    private function weeklyActivity(): array
    {
        $start = now()->startOfWeek();

        $perDay = LogRequest::whereBetween('created_at', [$start, $start->copy()->endOfWeek()])
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

    private function latestRequests(): Collection
    {
        return ShiftRequest::with(['employee.user', 'partnerSchedule.masterShift'])
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function notifications(Request $request): Collection
    {
        $employee = $request->user()->employee;

        return $employee
            ? $employee->notifications()->latest('id')->limit(5)->get()
            : collect();
    }
}