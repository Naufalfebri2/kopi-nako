<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleName, string $jobName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $job = Job::firstOrCreate(['name' => $jobName]);

        $user = User::create(['name' => 'U '.$email, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id]);

        Employee::create([
            'nik' => $email, 'id_user' => $user->id, 'job_id' => $job->id,
            'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
        ]);

        return $user;
    }

    public function test_supervisor_bisa_dashboard_dan_laporan(): void
    {
        $spv = $this->makeUser(Role::SUPERVISOR, 'Supervisor', 'spv@example.com');

        $this->actingAs($spv)->get(route('dashboard'))->assertOk();
        $this->get(route('laporan.index'))->assertOk();
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_leader_dan_karyawan_hanya_jadwal_dan_pengajuan(): void
    {
        foreach ([[Role::LEADER, 'Lead Cook'], [Role::KARYAWAN, 'Cook']] as $i => [$role, $job]) {
            $user = $this->makeUser($role, $job, "u{$i}@example.com");

            $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
            $this->get(route('laporan.index'))->assertForbidden();
            $this->get(route('jadwal.index'))->assertOk();
            $this->get(route('pengajuan.index'))->assertOk();
            $this->get('/')->assertRedirect(route('jadwal.index'));
        }
    }

    public function test_login_karyawan_diarahkan_ke_jadwal(): void
    {
        $this->makeUser(Role::KARYAWAN, 'Cook', 'cook@example.com');

        $this->post('/login', ['email' => 'cook@example.com', 'password' => 'password123'])
            ->assertRedirect(route('beranda'));

        $this->get(route('beranda'))->assertRedirect(route('jadwal.index'));
    }

    public function test_lonceng_notifikasi_bisa_ditandai_dibaca(): void
    {
        $user = $this->makeUser(Role::KARYAWAN, 'Cook', 'cook@example.com');
        $user->employee->notifications()->create([
            'message' => 'Tes', 'status' => 'belum_dibaca', 'schedule_id' => null, 'request_id' => null,
        ]);

        $this->actingAs($user)->post(route('notifikasi.baca'))->assertRedirect();

        $this->assertSame(0, $user->employee->notifications()->where('status', 'belum_dibaca')->count());
    }
}
