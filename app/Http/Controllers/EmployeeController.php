<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Services\EmployeeManagement;
use App\Services\EmployeeRegistration;
use App\Services\RolePairing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class EmployeeController extends Controller
{
    public function __construct(private RolePairing $pairing)
    {
    }

    public function store(Request $request, EmployeeRegistration $registration): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::SUPERVISOR), 403);

        $request->merge([
            'name'     => trim((string) $request->input('name')),
            'email'    => Str::lower(trim((string) $request->input('email'))),
            'no_phone' => preg_replace('/[\s\-()]+/', '', (string) $request->input('no_phone')),
        ]);

        // Kode karyawan tidak diisi dari form: dibuat otomatis oleh EmployeeRegistration.
        $validator = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'no_phone' => ['required', 'regex:/^(\+62|62|0)8\d{8,12}$/'],
            'job_id'   => ['required', 'exists:job,id'],
            'id_role'  => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->messages() + [
            'no_phone.required'  => 'Nomor telepon wajib diisi.',
            'no_phone.regex'     => 'Nomor telepon tidak valid. Contoh: 081234567890.',
            'password.required'  => 'Kata sandi wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $validator->after(fn ($v) => $this->checkPairing($v, $request));

        $oldInput = $request->except(['password', 'password_confirmation']);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($oldInput);
        }

        try {
            $employee = $registration->register($user, $validator->validated());
        } catch (Throwable $e) {
            // Hanya nama kelas: pesan QueryException bisa memuat data dari binding SQL.
            Log::error('Pendaftaran karyawan gagal.', ['exception' => $e::class]);

            return back()
                ->with('error', 'Karyawan belum dapat ditambahkan. Silakan coba lagi.')
                ->withInput($oldInput);
        }

        // Dari halaman Master Employee kembali ke sana, selain itu ke Dashboard seperti semula.
        $target = $request->input('dari') === 'master' ? 'master.employee.index' : 'dashboard';

        return redirect()
            ->route($target)
            ->with('notice', "Karyawan {$employee->user->name} berhasil ditambahkan.");
    }

    public function update(Request $request, Employee $employee, EmployeeManagement $management): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::SUPERVISOR), 403);

        $request->merge([
            'name'  => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validator = Validator::make($request->all(), [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->id_user)],
            'job_id'  => ['required', 'exists:job,id'],
            'id_role' => ['required', 'exists:roles,id'],
        ], $this->messages());

        $validator->after(function ($v) use ($user, $employee, $request) {
            if ($employee->id_user === $user->id && (int) $request->input('id_role') !== (int) $user->id_role) {
                $v->errors()->add('id_role', 'Anda tidak dapat mengubah role akun Anda sendiri.');
            }

            $this->checkPairing($v, $request);
        });

        $oldInput = $request->only(['name', 'email', 'job_id', 'id_role']) + ['edit_employee_id' => $employee->id];

        if ($validator->fails()) {
            return back()->withErrors($validator, 'editKaryawan')->withInput($oldInput);
        }

        try {
            $management->update($employee, $validator->validated());
        } catch (Throwable $e) {
            Log::error('Perubahan data karyawan gagal.', ['exception' => $e::class]);

            return back()->with('error', 'Data karyawan belum dapat disimpan. Silakan coba lagi.');
        }

        return back()->with('notice', "Data karyawan {$validator->validated()['name']} berhasil diperbarui.");
    }

    // Hapus permanen, hanya untuk karyawan tanpa riwayat (salah masukkan).
    public function destroy(Request $request, Employee $employee, EmployeeManagement $management): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::SUPERVISOR), 403);

        if ($employee->id_user === $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if ($blocker = $management->deletionBlocker($employee)) {
            return back()->with('error', $blocker);
        }

        $name = $employee->user->name;

        try {
            $management->delete($employee);
        } catch (Throwable $e) {
            Log::error('Penghapusan karyawan gagal.', ['exception' => $e::class]);

            return back()->with('error', 'Karyawan belum dapat dihapus. Silakan coba lagi.');
        }

        return back()->with('notice', "Karyawan {$name} berhasil dihapus.");
    }

    // Untuk karyawan yang sudah punya riwayat: tidak tampil lagi di jadwal, data tetap tersimpan.
    public function deactivate(Request $request, Employee $employee, EmployeeManagement $management): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::SUPERVISOR), 403);

        if ($employee->id_user === $user->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        try {
            $management->deactivate($employee);
        } catch (Throwable $e) {
            Log::error('Penonaktifan karyawan gagal.', ['exception' => $e::class]);

            return back()->with('error', 'Karyawan belum dapat dinonaktifkan. Silakan coba lagi.');
        }

        return back()->with('notice', "Karyawan {$employee->user->name} dinonaktifkan dan tidak lagi tampil di jadwal.");
    }

    // Kebalikan dari deactivate: jadwal dan riwayat tidak pernah dihapus, jadi langsung tampil lagi.
    public function activate(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->hasRole(Role::SUPERVISOR), 403);

        if ($employee->status === EmployeeStatus::Aktif) {
            return back()->with('error', 'Karyawan ini sudah aktif.');
        }

        try {
            $employee->forceFill(['status' => EmployeeStatus::Aktif])->save();
        } catch (Throwable $e) {
            Log::error('Pengaktifan karyawan gagal.', ['exception' => $e::class]);

            return back()->with('error', 'Karyawan belum dapat diaktifkan. Silakan coba lagi.');
        }

        return back()->with('notice', "Karyawan {$employee->user->name} diaktifkan kembali dan tampil lagi di jadwal.");
    }

    // Jabatan harus sesuai dengan role akses (aturan dari klien).
    private function checkPairing($validator, Request $request): void
    {
        if ($validator->errors()->has('job_id') || $validator->errors()->has('id_role')) {
            return;
        }

        $role = Role::find($request->input('id_role'));
        $job = Job::find($request->input('job_id'));

        if (! $role || ! $job || $this->pairing->allows($role->name, $job->name)) {
            return;
        }

        $validator->errors()->add(
            'job_id',
            "Jabatan {$job->name} tidak sesuai dengan role {$role->name}. Jabatan yang diizinkan: "
            .implode(', ', $this->pairing->allowedJobs($role->name)).'.'
        );
    }

    private function messages(): array
    {
        return [
            'name.required'    => 'Nama lengkap wajib diisi.',
            'name.max'         => 'Nama lengkap maksimal 255 karakter.',
            'email.required'   => 'Email wajib diisi.',
            'email.email'      => 'Format email tidak valid.',
            'email.max'        => 'Email maksimal 255 karakter.',
            'email.unique'     => 'Email sudah digunakan.',
            'job_id.required'  => 'Jabatan wajib dipilih.',
            'job_id.exists'    => 'Jabatan tidak ditemukan.',
            'id_role.required' => 'Role akses wajib dipilih.',
            'id_role.exists'   => 'Role akses tidak ditemukan.',
        ];
    }
}
