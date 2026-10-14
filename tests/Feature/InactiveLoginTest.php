<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(EmployeeStatus $status): User
    {
        $role = Role::create(['name' => Role::KARYAWAN]);
        $job = Job::create(['name' => 'Service']);

        $user = User::create([
            'name' => 'Artie', 'email' => 'artie@example.com', 'password' => 'password123', 'id_role' => $role->id,
        ]);

        Employee::create([
            'nik' => '2', 'id_user' => $user->id, 'job_id' => $job->id,
            'no_phone' => '081200000000', 'status' => $status,
        ]);

        return $user;
    }

    public function test_karyawan_aktif_bisa_login(): void
    {
        $this->makeEmployee(EmployeeStatus::Aktif);

        $this->post('/login', ['email' => 'artie@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_karyawan_nonaktif_ditolak_dengan_pesan_umum(): void
    {
        $user = $this->makeEmployee(EmployeeStatus::Nonaktif);

        $this->post('/login', ['email' => 'artie@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors(['password' => 'Email atau kata sandi salah.']);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('employees', ['id_user' => $user->id]);
    }

    public function test_akun_tanpa_data_karyawan_tetap_bisa_login(): void
    {
        $role = Role::create(['name' => Role::SUPERVISOR]);
        User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password123', 'id_role' => $role->id]);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
    }
}