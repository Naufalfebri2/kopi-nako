<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapDivisionTest extends TestCase
{
    use RefreshDatabase;

    private Employee $spv;
    private Employee $artie;   // Cook, shift Pagi
    private Employee $dina;    // Cook Helper (sedivisi), shift Siang
    private Employee $sari;    // Service (beda divisi), shift Siang

    protected function setUp(): void
    {
        parent::setUp();

        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $pagi = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);
        $siang = MasterShift::create(['name' => 'Siang', 'start_date' => '15:00:00', 'end_date' => '21:00:00']);

        $make = fn (Role $role, Job $job, string $name, string $email, string $nik) => Employee::create([
            'nik' => $nik, 'job_id' => $job->id, 'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
            'id_user' => User::create([
                'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
            ])->id,
        ]);

        $this->spv = $make($spvRole, Job::create(['name' => 'Supervisor']), 'Juandi', 'spv@example.com', '1');
        $this->artie = $make($staffRole, Job::create(['name' => 'Cook']), 'Artie', 'artie@example.com', '2');
        $this->dina = $make($staffRole, Job::create(['name' => 'Cook Helper']), 'Dina', 'dina@example.com', '3');
        $this->sari = $make($staffRole, Job::create(['name' => 'Service']), 'Sari', 'sari@example.com', '4');

        $date = today()->addDay()->toDateString();

        foreach ([[$this->artie, $pagi], [$this->dina, $siang], [$this->sari, $siang]] as [$employee, $shift]) {
            $this->actingAs($this->spv->user)->post(route('jadwal.store'), [
                'employee_id' => $employee->id, 'shift_id' => $shift->id, 'date' => $date,
            ]);
        }
    }

    private function detailId(Employee $employee): int
    {
        return ShiftScheduleDetail::where('id_employee', $employee->id)->firstOrFail()->id;
    }

    private function submit(Employee $partner)
    {
        return $this->actingAs($this->artie->user)->post(route('pengajuan.store'), [
            'jenis' => 'tukar',
            'detail_id' => $this->detailId($this->artie),
            'partner_detail_id' => $this->detailId($partner),
            'reason' => 'Ada keperluan keluarga di pagi hari.',
        ]);
    }

    public function test_daftar_rekan_hanya_sedivisi(): void
    {
        $options = $this->actingAs($this->artie->user)->get(route('pengajuan.index'))->viewData('options');

        $labels = $options->flatMap(fn ($option) => collect($option['partners'])->pluck('label'))->implode(' | ');

        $this->assertStringContainsString('Dina', $labels);
        $this->assertStringNotContainsString('Sari', $labels);
    }

    public function test_tukar_dengan_beda_divisi_ditolak(): void
    {
        $this->submit($this->sari)
            ->assertSessionHas('error', 'Rekan harus berasal dari divisi yang sama.');

        $this->assertSame(0, ShiftRequest::count());
    }

    public function test_tukar_dengan_sedivisi_diterima_meski_beda_jabatan(): void
    {
        $this->submit($this->dina)->assertSessionHas('notice');

        $this->assertSame(1, ShiftRequest::count());
    }
}
