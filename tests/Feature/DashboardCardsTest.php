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

class DashboardCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_kartu_dashboard_menghitung_sesuai_brief(): void
    {
        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $job = Job::create(['name' => 'Barista']);
        $shift = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $make = fn (Role $role, string $name, string $email, string $nik) => Employee::create([
            'nik' => $nik, 'job_id' => $job->id, 'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
            'id_user' => User::create([
                'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
            ])->id,
        ]);

        $spv = $make($spvRole, 'Juandi', 'spv@example.com', '1');
        $artie = $make($staffRole, 'Artie', 'artie@example.com', '2');
        $make($staffRole, 'Inge', 'inge@example.com', '3'); // tanpa jadwal

        $this->actingAs($spv->user)->post(route('jadwal.store'), [
            'employee_id' => $artie->id,
            'shift_id'    => $shift->id,
            'date'        => today()->toDateString(),
        ]);

        $stats = $this->get(route('dashboard'))->assertOk()->viewData('stats');

        $this->assertSame(1, $stats['shift_post']);
        $this->assertSame(1, $stats['bertugas']);
        $this->assertSame(3, $stats['aktif']);
        $this->assertSame(0, $stats['request']);
        $this->assertSame(0, $stats['completed']);
    }
}