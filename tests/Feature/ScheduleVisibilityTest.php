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

class ScheduleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private array $e = [];

    protected function setUp(): void
    {
        parent::setUp();

        MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $make = function (string $key, string $role, string $job, string $name) {
            $this->e[$key] = Employee::create([
                'nik' => $key, 'job_id' => Job::firstOrCreate(['name' => $job])->id,
                'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
                'id_user' => User::create([
                    'name' => $name, 'email' => "$key@example.com", 'password' => 'password123',
                    'id_role' => Role::firstOrCreate(['name' => $role])->id,
                ])->id,
            ]);
        };

        $make('spv', Role::SUPERVISOR, 'Supervisor', 'Juandi');
        $make('lead', Role::LEADER, 'Lead Cook', 'Fatah');
        $make('cook', Role::KARYAWAN, 'Cook', 'Anisa');
        $make('helper', Role::KARYAWAN, 'Cook Helper', 'Rahma');
        $make('barista', Role::KARYAWAN, 'Senior Barista', 'Haikal');
    }

    private function names(User $user): array
    {
        return $this->actingAs($user)->get(route('jadwal.index'))->viewData('rows')
            ->map(fn ($row) => $row['employee']->user->name)->sort()->values()->all();
    }

    public function test_karyawan_hanya_melihat_dirinya(): void
    {
        $this->assertSame(['Anisa'], $this->names($this->e['cook']->user));
    }

    public function test_leader_melihat_dirinya_dan_tim_sedivisi(): void
    {
        $this->assertSame(['Anisa', 'Fatah', 'Rahma'], $this->names($this->e['lead']->user));
    }

    public function test_supervisor_melihat_semua(): void
    {
        $this->assertSame(['Anisa', 'Fatah', 'Haikal', 'Juandi', 'Rahma'], $this->names($this->e['spv']->user));
    }

    public function test_papan_harian_juga_dibatasi(): void
    {
        $columns = $this->actingAs($this->e['cook']->user)
            ->get(route('jadwal.index', ['tampilan' => 'harian']))->viewData('columns');

        $this->assertCount(1, $columns);
        $this->assertSame(0, $columns->sum(fn ($c) => $c['details']->count()));
    }
}