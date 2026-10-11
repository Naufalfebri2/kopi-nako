<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private Job $job;
    private Role $supervisorRole;
    private Role $leaderRole;
    private Role $karyawanRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisorRole = Role::create(['name' => Role::SUPERVISOR]);
        $this->leaderRole     = Role::create(['name' => Role::LEADER]);
        $this->karyawanRole   = Role::create(['name' => Role::KARYAWAN]);
        $this->job            = Job::create(['name' => 'Barista']);
    }

    private function makeEmployee(Role $role, string $email, string $nik): Employee
    {
        $user = User::create([
            'name' => 'User ' . $email, 'email' => $email,
            'password' => 'password123', 'id_role' => $role->id,
        ]);

        return Employee::create([
            'nik' => $nik, 'id_user' => $user->id, 'job_id' => $this->job->id,
            'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
        ]);
    }

    private function payload(Employee $e, array $override = []): array
    {
        return array_merge([
            'name' => 'Nama Baru', 'email' => $e->user->email,
            'job_id' => $this->job->id, 'id_role' => $e->user->id_role,
        ], $override);
    }

    public function test_supervisor_dapat_mengubah_data_karyawan_tanpa_mengubah_nik_dan_telepon(): void
    {
        $spv = $this->makeEmployee($this->supervisorRole, 'spv@example.com', '1');
        $emp = $this->makeEmployee($this->karyawanRole, 'a@example.com', '2');

        $this->actingAs($spv->user)
            ->put(route('karyawan.update', $emp), $this->payload($emp, [
                'name' => 'Budi Baru', 'id_role' => $this->leaderRole->id,
                'nik' => '999', 'no_phone' => '081299999999',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice');

        $emp->refresh();
        $this->assertSame('Budi Baru', $emp->user->name);
        $this->assertSame($this->leaderRole->id, $emp->user->id_role);
        $this->assertSame('2', $emp->nik);
        $this->assertSame('081200000000', $emp->no_phone);
    }

    public function test_non_supervisor_mendapat_403(): void
    {
        $leader = $this->makeEmployee($this->leaderRole, 'l@example.com', '1');
        $emp    = $this->makeEmployee($this->karyawanRole, 'a@example.com', '2');

        $this->actingAs($leader->user)->put(route('karyawan.update', $emp), $this->payload($emp))->assertForbidden();
        $this->actingAs($leader->user)->delete(route('karyawan.destroy', $emp))->assertForbidden();
        $this->actingAs($leader->user)->patch(route('karyawan.deactivate', $emp))->assertForbidden();
        $this->assertSame(EmployeeStatus::Aktif, $emp->fresh()->status);
    }

    public function test_email_sendiri_tidak_dianggap_duplikat_tetapi_milik_orang_lain_ditolak(): void
    {
        $spv = $this->makeEmployee($this->supervisorRole, 'spv@example.com', '1');
        $a   = $this->makeEmployee($this->karyawanRole, 'a@example.com', '2');
        $this->makeEmployee($this->karyawanRole, 'b@example.com', '3');

        $this->actingAs($spv->user)
            ->put(route('karyawan.update', $a), $this->payload($a))
            ->assertSessionHasNoErrors();

        $this->put(route('karyawan.update', $a), $this->payload($a, ['email' => 'b@example.com']))
            ->assertSessionHasErrors(['email' => 'Email sudah digunakan.'], null, 'editKaryawan');
    }

    public function test_supervisor_tidak_dapat_mengubah_role_sendiri(): void
    {
        $spv = $this->makeEmployee($this->supervisorRole, 'spv@example.com', '1');

        $this->actingAs($spv->user)
            ->put(route('karyawan.update', $spv), $this->payload($spv, ['id_role' => $this->karyawanRole->id]))
            ->assertSessionHasErrors(['id_role' => 'Anda tidak dapat mengubah role akun Anda sendiri.'], null, 'editKaryawan');

        $this->assertSame($this->supervisorRole->id, $spv->user->fresh()->id_role);
    }

    public function test_karyawan_tanpa_riwayat_dihapus_permanen(): void
    {
        $spv = $this->makeEmployee($this->supervisorRole, 'spv@example.com', '1');
        $emp = $this->makeEmployee($this->karyawanRole, 'salah@example.com', '2');

        $this->actingAs($spv->user)
            ->delete(route('karyawan.destroy', $emp))
            ->assertSessionHas('notice');

        $this->assertDatabaseMissing('employees', ['nik' => '2']);
        $this->assertDatabaseMissing('users', ['email' => 'salah@example.com']);
    }

    public function test_karyawan_dengan_riwayat_tidak_bisa_dihapus_tetapi_bisa_dinonaktifkan(): void
    {
        $spv = $this->makeEmployee($this->supervisorRole, 'spv@example.com', '1');
        $emp = $this->makeEmployee($this->karyawanRole, 'a@example.com', '2');

        $shift = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $this->actingAs($spv->user)->post(route('jadwal.store'), [
            'employee_id' => $emp->id,
            'shift_id'    => $shift->id,
            'date'        => now()->toDateString(),
        ]);

        $this->delete(route('karyawan.destroy', $emp))->assertSessionHas('error');
        $this->assertDatabaseHas('employees', ['id' => $emp->id]);

        $this->patch(route('karyawan.deactivate', $emp))->assertSessionHas('notice');
        $this->assertSame(EmployeeStatus::Nonaktif, $emp->fresh()->status);

        // Periksa data baris grid, bukan teks halaman: pesan flash memuat nama karyawan.
        $rows = $this->get(route('jadwal.index'))->viewData('rows');
        $this->assertFalse($rows->contains(fn ($row) => $row['employee']->id === $emp->id));
    }

    public function test_supervisor_tidak_dapat_menghapus_atau_menonaktifkan_diri_sendiri(): void
    {
        $spv = $this->makeEmployee($this->supervisorRole, 'spv@example.com', '1');

        $this->actingAs($spv->user)->delete(route('karyawan.destroy', $spv))->assertSessionHas('error');
        $this->patch(route('karyawan.deactivate', $spv))->assertSessionHas('error');

        $this->assertSame(EmployeeStatus::Aktif, $spv->fresh()->status);
        $this->assertDatabaseHas('employees', ['id' => $spv->id]);
    }
}
