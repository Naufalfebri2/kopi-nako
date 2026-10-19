<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Services\RolePairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class MasterEmployeeTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $supervisor;
    private Employee $leader;
    private Employee $empBar;
    private Employee $empDapur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leader = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->empBar = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->empDapur = $this->makeEmployee(Role::KARYAWAN, 'Koki', 'Dewi Dapur');
    }

    public function test_hanya_supervisor_boleh_mengakses(): void
    {
        foreach ([$this->leader, $this->empBar] as $employee) {
            $this->actingAs($employee->user)->get(route('master.employee.index'))->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('master.employee.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_memuat_karyawan_aktif_dan_nonaktif(): void
    {
        $this->empDapur->forceFill(['status' => EmployeeStatus::Nonaktif])->save();

        $this->assertEqualsCanonicalizing(
            ['Budi Bar', 'Dewi Dapur', 'Lia Leader', 'Sari Supervisor'],
            $this->listedNames([])
        );
    }

    public function test_pencarian_nama_email_dan_jabatan_tanpa_membedakan_huruf(): void
    {
        $this->assertSame(['Budi Bar'], $this->listedNames(['q' => 'budi']));
        $this->assertSame(['Budi Bar'], $this->listedNames(['q' => 'BUDI-BAR@example']));
        $this->assertSame(['Dewi Dapur'], $this->listedNames(['q' => 'koki']));
    }

    public function test_pencarian_tidak_memakai_nik(): void
    {
        $this->assertSame([], $this->listedNames(['q' => $this->empBar->nik]));
    }

    public function test_filter_status(): void
    {
        $this->empDapur->forceFill(['status' => EmployeeStatus::Nonaktif])->save();

        $this->assertSame(['Dewi Dapur'], $this->listedNames(['status' => 'Nonaktif']));
        $this->assertCount(3, $this->listedNames(['status' => 'Aktif']));
        $this->assertCount(4, $this->listedNames(['status' => 'tidak-dikenal']));
    }

    public function test_tombol_hapus_tidak_tampil_untuk_akun_sendiri(): void
    {
        $html = $this->actingAs($this->supervisor->user)
            ->get(route('master.employee.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-destroy-url="'.route('karyawan.destroy', $this->empBar).'"', $html);
        $this->assertStringNotContainsString('data-destroy-url="'.route('karyawan.destroy', $this->supervisor).'"', $html);
        $this->assertStringContainsString('data-update-url="'.route('karyawan.update', $this->supervisor).'"', $html);
    }

    public function test_tambah_dari_master_kembali_ke_master(): void
    {
        $this->allowAllPairings();

        $this->actingAs($this->supervisor->user)
            ->post(route('karyawan.store'), $this->newEmployee(['dari' => 'master']))
            ->assertRedirect(route('master.employee.index'))
            ->assertSessionHas('notice');

        $this->assertDatabaseHas('users', ['email' => 'baru@example.test']);
    }

    public function test_tambah_tanpa_penanda_tetap_ke_dashboard(): void
    {
        $this->allowAllPairings();

        $this->actingAs($this->supervisor->user)
            ->post(route('karyawan.store'), $this->newEmployee())
            ->assertRedirect(route('dashboard'));
    }

    public function test_tambah_gagal_kembali_ke_master_dengan_pesan_kesalahan(): void
    {
        $this->actingAs($this->supervisor->user)
            ->from(route('master.employee.index'))
            ->post(route('karyawan.store'), $this->newEmployee(['dari' => 'master', 'email' => '']))
            ->assertRedirect(route('master.employee.index'))
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.']);
    }

    public function test_ubah_dari_master_memakai_aturan_yang_sama(): void
    {
        $this->actingAs($this->supervisor->user)
            ->from(route('master.employee.index'))
            ->put(route('karyawan.update', $this->supervisor), [
                'name' => 'Sari Supervisor',
                'email' => 'sari-supervisor@example.test',
                'job_id' => $this->supervisor->job_id,
                'id_role' => $this->empBar->user->id_role,
            ])
            ->assertRedirect(route('master.employee.index'))
            ->assertSessionHasErrorsIn('editKaryawan', ['id_role' => 'Anda tidak dapat mengubah role akun Anda sendiri.']);
    }

    // Pairing role dan jabatan dipalsukan agar test tidak bergantung pada daftar aturan asli
    private function allowAllPairings(): void
    {
        $this->partialMock(RolePairing::class, fn ($mock) => $mock->shouldReceive('allows')->andReturnTrue());
    }

    private function newEmployee(array $override = []): array
    {
        return $override + [
            'name' => 'Karyawan Baru',
            'email' => 'baru@example.test',
            'no_phone' => '081234567890',
            'nik' => '3201234567890001',
            'job_id' => $this->empBar->job_id,
            'id_role' => $this->empBar->user->id_role,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function listedNames(array $query): array
    {
        return $this->actingAs($this->supervisor->user)
            ->get(route('master.employee.index', $query))
            ->assertOk()
            ->viewData('employees')
            ->map(fn (Employee $employee) => $employee->user->name)
            ->all();
    }
}
