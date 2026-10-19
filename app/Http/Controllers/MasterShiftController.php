<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MasterShift;
use App\Services\MasterShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterShiftController extends Controller
{
    public function __construct(private MasterShiftService $service)
    {
    }

    public function index(): View
    {
        return view('master.shift', [
            'shifts' => MasterShift::withCount('schedules')->orderBy('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shift = $this->service->create($this->validated($request));

        return redirect()->route('master.shift.index')
            ->with('notice', "Shift {$shift->name} berhasil ditambahkan.");
    }

    public function update(Request $request, MasterShift $masterShift): RedirectResponse
    {
        $this->service->update($masterShift, $this->validated($request, $masterShift));

        return redirect()->route('master.shift.index')
            ->with('notice', "Shift {$masterShift->name} berhasil diperbarui.");
    }

    // Jam selesai boleh lebih kecil dari jam mulai (shift lewat tengah malam), asal tidak sama.
    private function validated(Request $request, ?MasterShift $current = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('master_shift', 'name')->ignore($current?->id)],
            'start_date' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:H:i', 'different:start_date'],
        ], [
            'name.required' => 'Nama shift wajib diisi.',
            'name.max' => 'Nama shift maksimal 50 karakter.',
            'name.unique' => 'Nama shift sudah digunakan.',
            'start_date.required' => 'Jam mulai wajib diisi.',
            'start_date.date_format' => 'Jam mulai harus berformat JJ:MM, contoh 09:00.',
            'end_date.required' => 'Jam selesai wajib diisi.',
            'end_date.date_format' => 'Jam selesai harus berformat JJ:MM, contoh 15:00.',
            'end_date.different' => 'Jam selesai tidak boleh sama dengan jam mulai.',
        ]);
    }
}