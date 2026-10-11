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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ScheduleController extends Controller
{
    private const MAX_COMBINATIONS = 200;

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
            // Daftar role untuk modal ubah data karyawan, hanya dimuat untuk Supervisor
            'roles' => $canEdit ? Role::orderBy('name')->get(['id', 'name']) : collect(),
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

        // Kompatibel dengan form lama (klik sel "Libur" mengirim satu karyawan, satu tanggal, satu shift).
        if (! $request->has('employee_ids') && $request->filled('employee_id')) {
            $request->merge(['employee_ids' => [$request->input('employee_id')]]);
        }
        if (! $request->has('dates') && $request->filled('date')) {
            $request->merge(['dates' => [$request->input('date')]]);
        }
        if (! $request->has('shift_ids') && $request->filled('shift_id')) {
            $request->merge(['shift_ids' => [$request->input('shift_id')]]);
        }

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1', 'max:50'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'dates' => ['required', 'array', 'min:1', 'max:31'],
            'dates.*' => ['date_format:Y-m-d', 'distinct'],
            'shift_ids' => ['required', 'array', 'min:1', 'max:10'],
            'shift_ids.*' => ['integer', 'distinct', 'exists:master_shift,id'],
            'tampilan' => ['nullable', 'in:mingguan,harian'],
        ], [
            'employee_ids.required' => 'Pilih minimal satu karyawan.',
            'employee_ids.min' => 'Pilih minimal satu karyawan.',
            'employee_ids.max' => 'Maksimal 50 karyawan sekaligus.',
            'employee_ids.*.exists' => 'Ada karyawan yang tidak ditemukan.',
            'employee_ids.*.distinct' => 'Ada karyawan yang dipilih dua kali.',
            'dates.required' => 'Pilih minimal satu tanggal.',
            'dates.min' => 'Pilih minimal satu tanggal.',
            'dates.max' => 'Maksimal 31 tanggal sekaligus.',
            'dates.*.date_format' => 'Format tanggal tidak valid.',
            'dates.*.distinct' => 'Ada tanggal yang dipilih dua kali.',
            'shift_ids.required' => 'Pilih minimal satu shift.',
            'shift_ids.min' => 'Pilih minimal satu shift.',
            'shift_ids.max' => 'Maksimal 10 shift sekaligus.',
            'shift_ids.*.exists' => 'Ada shift yang tidak ditemukan.',
            'shift_ids.*.distinct' => 'Ada shift yang dipilih dua kali.',
        ]);

        $total = count($data['employee_ids']) * count($data['dates']) * count($data['shift_ids']);
        if ($total > self::MAX_COMBINATIONS) {
            return back()->withInput()->withErrors([
                'assign' => 'Terlalu banyak jadwal sekaligus ('.$total.'). Maksimal '.self::MAX_COMBINATIONS.' per proses.',
            ]);
        }

        $dates = collect($data['dates'])->sort()->values();
        $statusLead = $request->boolean('status_lead');
        $created = 0;
        $skipped = 0;
        $reasons = [];

        try {
            DB::transaction(function () use ($request, $data, $dates, $statusLead, &$created, &$skipped, &$reasons) {
                foreach ($data['employee_ids'] as $employeeId) {
                    foreach ($dates as $date) {
                        foreach ($data['shift_ids'] as $shiftId) {
                            $item = [
                                'employee_id' => (int) $employeeId,
                                'shift_id' => (int) $shiftId,
                                'date' => $date,
                                'status_lead' => $statusLead,
                            ];

                            // Kombinasi yang bentrok dilewati dan dilaporkan, bukan menggagalkan semuanya.
                            if ($error = $this->schedules->assignmentError($item)) {
                                $skipped++;
                                $reasons[$error] = true;
                                continue;
                            }

                            $this->schedules->assign($request->user(), $item);
                            $created++;
                        }
                    }
                }
            });
        } catch (Throwable $e) {
            Log::error('Penambahan jadwal massal gagal.', ['exception' => $e::class]);

            return back()
                ->withInput()
                ->with('error', 'Jadwal belum dapat ditambahkan. Silakan coba lagi.');
        }

        if ($created === 0) {
            return back()->withInput()->withErrors([
                'assign' => 'Tidak ada jadwal yang ditambahkan. '.implode(' ', array_slice(array_keys($reasons), 0, 3)),
            ]);
        }

        $message = $created.' jadwal berhasil ditambahkan.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' dilewati: '.implode(' ', array_slice(array_keys($reasons), 0, 3));
        }

        return redirect()
            ->route('jadwal.index', [
                'tampilan' => $data['tampilan'] ?? 'mingguan',
                'tanggal' => $dates->first(),
            ])
            ->with('notice', $message);
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
