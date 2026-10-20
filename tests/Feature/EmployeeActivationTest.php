<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Models\ShiftScheduleDetail;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class EmployeeActivationTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $supervisor;
    private Employee $leader;
    private Employee $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leader = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->karyawan = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');

        $this->assign($this->karyawan, $this->makeShift(), '2026-10-20');
    }

    public function test_karyawan_dengan_jadwal_dinonaktifkan_lalu_diaktifkan_kembali(): void
    {
        $this->actingAs($this->supervisor->user);

        $this->patch(route('karyawan.deactivate', $this->karyawan))->assertSessionHas('notice');
        $this->assertSame(EmployeeStatus::Nonaktif, $this->karyawan->fresh()->status);
        $this->assertNotContains('Budi Bar', $this->gridNames());

        $this->patch(route('karyawan.activate', $this->karyawan))->assertSessionHas('notice');
        $this->assertSame(EmployeeStatus::Aktif, $this->karyawan->fresh()->status);
        $this->assertContains('Budi Bar', $this->gridNames());

        // Jadwalnya tidak pernah hilang selama nonaktif
        $this->assertSame(1, ShiftScheduleDetail::where('id_employee', $this->karyawan->id)->count());
    }

    public function test_selain_supervisor_mendapat_403(): void
    {
        $this->karyawan->forceFill(['status' => EmployeeStatus::Nonaktif])->save();
        $lain = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Bima Kasir');

        foreach ([$this->leader, $lain] as $employee) {
            $this->actingAs($employee->user)
                ->patch(route('karyawan.activate', $this->karyawan))
                ->assertForbidden();
        }

        $this->assertSame(EmployeeStatus::Nonaktif, $this->karyawan->fresh()->status);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->patch(route('karyawan.activate', $this->karyawan))->assertRedirect(route('login'));
    }

    public function test_karyawan_yang_sudah_aktif_ditolak(): void
    {
        $this->actingAs($this->supervisor->user)
            ->patch(route('karyawan.activate', $this->karyawan))
            ->assertSessionHas('error', 'Karyawan ini sudah aktif.');
    }

    public function test_tombol_aktifkan_hanya_tampil_untuk_karyawan_nonaktif(): void
    {
        $this->karyawan->forceFill(['status' => EmployeeStatus::Nonaktif])->save();

        $html = $this->actingAs($this->supervisor->user)
            ->get(route('master.employee.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('karyawan.activate', $this->karyawan), $html);
        $this->assertStringNotContainsString(route('karyawan.activate', $this->leader), $html);
    }

    // Nama karyawan yang tampil di grid mingguan Supervisor
    private function gridNames(): array
    {
        $service = app(ScheduleService::class);
        $days = $service->weekDays(Carbon::parse('2026-10-20'));

        return $service->weeklyRows($days, null, $this->supervisor->user)
            ->map(fn (array $row) => $row['employee']->user->name)
            ->all();
    }
}
