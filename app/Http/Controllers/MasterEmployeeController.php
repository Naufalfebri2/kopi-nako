<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterEmployeeController extends Controller
{
    private const PER_PAGE = 15;

    // Pencarian hanya nama, email, dan jabatan. Kode karyawan sengaja tidak dipakai agar tidak masuk URL.
    public function index(Request $request): View
    {
        $term = trim(mb_substr((string) $request->query('q'), 0, 100));
        $status = EmployeeStatus::tryFrom((string) $request->query('status'));

        $employees = Employee::query()
            ->select('employees.*')
            ->join('users', 'users.id', '=', 'employees.id_user')
            ->with(['user.role', 'job'])
            ->when($status, fn ($query, $value) => $query->where('employees.status', $value))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereLike('users.name', "%{$term}%")
                ->orWhereLike('users.email', "%{$term}%")
                ->orWhereHas('job', fn ($job) => $job->whereLike('name', "%{$term}%"))))
            ->orderBy('users.name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('master.employee', [
            'employees' => $employees,
            'jobs' => Job::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'term' => $term,
            'status' => $status,
        ]);
    }
}
