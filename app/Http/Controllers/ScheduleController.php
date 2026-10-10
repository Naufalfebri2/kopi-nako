<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftScheduleDetail;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedules)
    {
    }

    public function index(Request $request): View
    {
        $view = $request->query('tampilan') === 'harian' ? 'harian' : 'mingguan';
        $date = $this->parseDate($request->query('tanggal'));
        $jobId = $request->integer('jabatan') ?: null;
        $canEdit = $request->user()->hasRole(Role::SUPERVISOR);
        $step = $view === 'mingguan' ? 7 : 1;

        $data = [
            'view' => $view,
            'date' => $date,
            'jobId' => $jobId,
            'jobs' => Job::orderBy('name')->get(),
            'shifts' => MasterShift::orderBy('start_date')->get(),
            'canEdit' => $canEdit,
            'employees' => $canEdit ? $this->schedules->activeEmployees() : collect(),
            'rangeLabel' => $this->rangeLabel($view, $date),
            'prevUrl' => $this->url($view, $date->copy()->subDays($step), $jobId),
            'nextUrl' => $this->url($view, $date->copy()->addDays($step), $jobId),
            'todayUrl' => $this->url($view, now()->startOfDay(), $jobId),
            'weeklyUrl' => $this->url('mingguan', $date, $jobId),
            'dailyUrl' => $this->url('harian', $date, $jobId),
        ];

        if ($view === 'mingguan') {
            $days = $this->schedules->weekDays($date);
            $data['days'] = $days;
            $data['rows'] = $this->schedules->weeklyRows($days, $jobId);
        } else {
            $data['columns'] = $this->schedules->dayBoard($date, $jobId);
        }

        return view('jadwal.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Role::SUPERVISOR), 403);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'shift_id' => ['required', 'integer', 'exists:master_shift,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'tampilan' => ['nullable', 'in:mingguan,harian'],
        ], [
            'employee_id.required' => 'Pilih karyawan.',
            'shift_id.required' => 'Pilih shift.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Format tanggal tidak valid.',
        ]);
        $data['status_lead'] = $request->boolean('status_lead');

        if ($error = $this->schedules->assignmentError($data)) {
            return back()->withInput()->withErrors(['assign' => $error]);
        }

        $this->schedules->assign($request->user(), $data);

        return redirect()
            ->route('jadwal.index', [
                'tampilan' => $data['tampilan'] ?? 'mingguan',
                'tanggal' => $data['date'],
            ])
            ->with('notice', 'Jadwal berhasil ditambahkan.');
    }

    public function update(Request $request, ShiftScheduleDetail $detail): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Role::SUPERVISOR), 403);

        $data = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:master_shift,id'],
        ], [
            'shift_id.required' => 'Pilih shift.',
        ]);
        $data['status_lead'] = $request->boolean('status_lead');

        if ($error = $this->schedules->updateError($detail, $data)) {
            return back()->with('error', $error);
        }

        $this->schedules->update($request->user(), $detail, $data);

        return back()->with('notice', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Request $request, ShiftScheduleDetail $detail): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Role::SUPERVISOR), 403);

        if ($error = $this->schedules->removeError($detail)) {
            return back()->with('error', $error);
        }

        $this->schedules->remove($detail);

        return back()->with('notice', 'Jadwal berhasil dihapus.');
    }

    private function parseDate(?string $value): Carbon
    {
        try {
            return $value
                ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    private function rangeLabel(string $view, Carbon $date): string
    {
        if ($view === 'harian') {
            return $date->copy()->locale('id')->translatedFormat('l, j M Y');
        }

        $start = $date->copy()->startOfWeek()->locale('id');
        $end = $date->copy()->endOfWeek()->locale('id');

        return $start->month === $end->month
            ? $start->translatedFormat('j').' – '.$end->translatedFormat('j M Y')
            : $start->translatedFormat('j M').' – '.$end->translatedFormat('j M Y');
    }

    private function url(string $view, Carbon $date, ?int $jobId): string
    {
        return route('jadwal.index', array_filter([
            'tampilan' => $view,
            'tanggal' => $date->toDateString(),
            'jabatan' => $jobId,
        ]));
    }
}
