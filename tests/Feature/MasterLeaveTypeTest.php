<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class MasterLeaveTypeTest extends TestCase
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
    }

    public function test_hanya_supervisor_boleh_mengakses(): void
    {
        $type = LeaveType::create(['name' => 'Cuti']);

        foreach ([$this->leader, $this->karyawan] as $employee) {
            $this->actingAs($employee->user);

            $this->get(route('master.leave.index'))->assertForbidden();
            $this->post(route('master.leave.store'), ['name' => 'Baru'])->assertForbidden();
            $this->put(route('master.leave.update', $type), ['name' => 'Baru'])->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('master.leave.index'))->assertRedirect(route('login'));
    }

    public function test_supervisor_melihat_daftar_dan_jumlah_pemakaian(): void
    {
        $detail = $this->assign($this->karyawan, $this->makeShift(), '2026-10-20');
        $this->makeRequest($detail); // memakai jenis izin "Sakit"
        LeaveType::create(['name' => 'Cuti']);

        $this->actingAs($this->supervisor->user)
            ->get(route('master.leave.index'))
            ->assertOk()
            ->assertSee('Sakit')
            ->assertSee('Cuti')
            ->assertSee('data-used="1"', false)
            ->assertSee('data-used="0"', false);
    }

    public function test_supervisor_menambah_jenis_izin(): void
    {
        $this->actingAs($this->supervisor->user)
            ->post(route('master.leave.store'), ['name' => '  Cuti Menikah  '])
            ->assertRedirect(route('master.leave.index'))
            ->assertSessionHas('notice');

        $this->assertDatabaseHas('leave_type', ['name' => 'Cuti Menikah']);
    }

    public function test_supervisor_mengubah_jenis_izin(): void
    {
        $type = LeaveType::create(['name' => 'Cuti']);

        $this->actingAs($this->supervisor->user)
            ->put(route('master.leave.update', $type), ['name' => 'Cuti Tahunan'])
            ->assertRedirect(route('master.leave.index'))
            ->assertSessionHas('notice');

        $this->assertDatabaseHas('leave_type', ['id' => $type->id, 'name' => 'Cuti Tahunan']);
    }

    public function test_nama_sendiri_tidak_dianggap_duplikat_saat_ubah(): void
    {
        $type = LeaveType::create(['name' => 'Cuti']);

        $this->actingAs($this->supervisor->user)
            ->put(route('master.leave.update', $type), ['name' => 'Cuti'])
            ->assertSessionHasNoErrors();
    }

    public function test_nama_milik_jenis_lain_ditolak(): void
    {
        LeaveType::create(['name' => 'Cuti']);
        $sakit = LeaveType::create(['name' => 'Sakit']);

        $this->actingAs($this->supervisor->user)
            ->post(route('master.leave.store'), ['name' => 'Cuti'])
            ->assertSessionHasErrors(['name' => 'Jenis izin ini sudah ada.']);

        $this->put(route('master.leave.update', $sakit), ['name' => 'Cuti'])
            ->assertSessionHasErrors(['name' => 'Jenis izin ini sudah ada.']);
    }

    #[DataProvider('namaTidakValid')]
    public function test_nama_tidak_valid_ditolak(string $name): void
    {
        $this->actingAs($this->supervisor->user)
            ->post(route('master.leave.store'), ['name' => $name])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('leave_type', 0);
    }

    public static function namaTidakValid(): array
    {
        return [
            'kosong' => [''],
            'hanya spasi' => ['   '],
            'lebih dari 255 karakter' => [str_repeat('a', 256)],
        ];
    }

    public function test_tidak_ada_route_hapus(): void
    {
        $type = LeaveType::create(['name' => 'Cuti']);

        $this->assertFalse(Route::has('master.leave.destroy'));

        $this->actingAs($this->supervisor->user)
            ->delete('/master/leave-type/'.$type->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('leave_type', ['id' => $type->id]);
    }
}