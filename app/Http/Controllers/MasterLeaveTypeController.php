<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Services\LeaveTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterLeaveTypeController extends Controller
{
    public function __construct(private LeaveTypeService $service)
    {
    }

    public function index(): View
    {
        return view('master.leave', [
            'types' => LeaveType::withCount('requests')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->service->create($this->validated($request)['name']);

        return redirect()->route('master.leave.index')
            ->with('notice', "Jenis izin {$type->name} berhasil ditambahkan.");
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->service->update($leaveType, $this->validated($request, $leaveType)['name']);

        return redirect()->route('master.leave.index')
            ->with('notice', "Jenis izin {$leaveType->name} berhasil diperbarui.");
    }

    private function validated(Request $request, ?LeaveType $current = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('leave_type', 'name')->ignore($current?->id)],
        ], [
            'name.required' => 'Nama jenis izin wajib diisi.',
            'name.max' => 'Nama jenis izin maksimal 255 karakter.',
            'name.unique' => 'Jenis izin ini sudah ada.',
        ]);
    }
}