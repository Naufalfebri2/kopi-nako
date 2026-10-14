<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePairingTest extends TestCase
{
    use RefreshDatabase;

    private Role $spvRole;
    private Role $leaderRole;
    private Role $staffRole;
    private Job $spvJob;
    private Job $headBarista;
    private Job $service;
    private Employee $spv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spvRole    = Role::create(['name' => Role::SUPERVISOR]);
        $this->leaderRole = Role::create(['name' => Role::LEADER]);
        $this->staffRole  = Role::create(['name' => Role::KARYAWAN]);
        $this->spvJob      = Job::create(['name' => 'Supervisor']);
        $this->headBarista = Job::create(['name' => 'Head Barista']);
        $this->service     = Job::create(['name' => 'Service']);

        $this->spv = Employee::create([
            'nik' => '1', 'job_id' => $this->spvJob->id, 'no_phone' => '081200000001', 'status' => EmployeeStatus::Aktif,
            'id_user' => User::create([
                'name' => 'Juandi', 'email' => 'spv@example.com', 'password' => 'password123', 'id_role' => $this->spvRole->id,
            ])->id,
        ]);
    }

    private function store(Job $job, Role $role, string $suffix)
    {
        return $this->actingAs($this->spv->user)->post(route('karyawan.store'), [
            'name' => 'Orang '.$suffix, 'email' => "o{$suffix}@example.com", 'no_phone' => '081234567890',
            'nik' => '9'.$suffix, 'job_id' => $job->id, 'id_role' => $role->id,
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ]);
    }

    public function test_pasangan_yang_benar_diterima(): void
    {
        $this->store($this->service, $this->staffRole, '1')->assertSessionHasNoErrors();
        $this->store($this->headBarista, $this->leaderRole, '2')->assertSessionHasNoErrors();
        $this->store($this->spvJob, $this->spvRole, '3')->assertSessionHasNoErrors();

        $this->assertSame(4, Employee::count());
    }

    public function test_pasangan_yang_salah_ditolak(): void
    {
        $this->store($this->headBarista, $this->staffRole, '1')->assertSessionHasErrors('job_id');
        $this->store($this->service, $this->leaderRole, '2')->assertSessionHasErrors('job_id');
        $this->store($this->service, $this->spvRole, '3')->assertSessionHasErrors('job_id');
        $this->store($this->spvJob, $this->staffRole, '4')->assertSessionHasErrors('job_id');

        $this->assertSame(1, Employee::count());
    }

    public function test_ubah_karyawan_juga_memeriksa_pasangan(): void
    {
        $this->store($this->service, $this->staffRole, '1');
        $emp = Employee::where('nik', '91')->firstOrFail();

        $payload = ['name' => 'Orang 1', 'email' => 'o1@example.com', 'job_id' => $this->service->id, 'id_role' => $this->leaderRole->id];

        $this->actingAs($this->spv->user)->put(route('karyawan.update', $emp), $payload)
            ->assertSessionHasErrors('job_id', null, 'editKaryawan');

        $this->put(route('karyawan.update', $emp), ['job_id' => $this->headBarista->id] + $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame($this->leaderRole->id, $emp->user->fresh()->id_role);
    }
}