<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeRegistration
{
    public function register(User $by, array $data): Employee
    {
        return DB::transaction(function () use ($by, $data) {
            // Kata sandi dikirim apa adanya; cast `hashed` di model User yang meng-hash.
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => $data['password'],
                'id_role'  => $data['id_role'],
            ]);

            $employee = Employee::create([
                'nik'      => $data['nik'],
                'id_user'  => $user->id,
                'job_id'   => $data['job_id'],
                'no_phone' => $data['no_phone'],
                'status'   => EmployeeStatus::Aktif,
            ]);

            // Pesan sengaja tanpa NIK (data pribadi).
            $message = "Karyawan baru, {$user->name}, ditambahkan ke tim oleh {$by->name}";

            // Notifikasi menempel ke employee, sama seperti yang dibaca DashboardController.
            Employee::whereHas('user.role', fn ($q) => $q->where('name', Role::SUPERVISOR))
                ->where('id_user', '!=', $by->id)
                ->where('id', '!=', $employee->id)
                ->get()
                ->each(fn (Employee $supervisor) => $supervisor->notifications()->create([
                    'message'     => $message,
                    'status'      => 'belum_dibaca',
                    'schedule_id' => null,
                    'request_id'  => null,
                ]));

            return $employee;
        });
    }
}
