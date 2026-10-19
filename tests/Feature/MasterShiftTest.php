<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class MasterShiftTest extends TestCase
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
        $shift = $this->makeShift();

        foreach ([$this->leader, $this->karyawan] as $employee) {
            $this->actingAs($employee->user);

            $this->get(route('master.shift.index'))->assertForbidden();
            $this->post(route('master.shift.store'), $this->payload())->assertForbidden();
            $this->put(route('master.shift.update', $shift), $this->payload())->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('master.shift.index'))->assertRedirect(route('login'));
    }

    public function test_supervisor_melihat_daftar_dan_jumlah_pemakaian(): void
    {
        $dipakai = $this->makeShift('Pagi');
        $this->makeShift('Siang');
        $this->assign($this->karyawan, $dipakai, '2026-10-20');

        $this->actingAs($this->supervisor->user)
            ->get(route('master.shift.index'))
            ->assertOk()
            ->assertSee('Pagi')
            ->assertSee('Siang')
            ->assertSee('data-used="1"', false)
            ->assertSee('data-used="0"', false);
    }

    public function test_supervisor_menambah_shift_lewat_tengah_malam(): void
    {
        $this->actingAs($this->supervisor->user)
            ->post(route('master.shift.store'), $this->payload(['name' => 'Malam', 'start_date' => '21:00', 'end_date' => '03:30']))
            ->assertRedirect(route('master.shift.index'))
            ->assertSessionHas('notice');

        $this->assertDatabaseHas('master_shift', [
            'name' => 'Malam',
            'start_date' => '21:00:00',
            'end_date' => '03:30:00',
        ]);
    }

    public function test_supervisor_mengubah_shift(): void
    {
        $shift = $this->makeShift('Pagi');

        $this->actingAs($this->supervisor->user)
            ->put(route('master.shift.update', $shift), $this->payload(['name' => 'Pagi Awal', 'start_date' => '06:30', 'end_date' => '14:30']))
            ->assertRedirect(route('master.shift.index'))
            ->assertSessionHas('notice');

        $this->assertDatabaseHas('master_shift', [
            'id' => $shift->id,
            'name' => 'Pagi Awal',
            'start_date' => '06:30:00',
            'end_date' => '14:30:00',
        ]);
    }

    public function test_nama_sendiri_tidak_dianggap_duplikat_saat_ubah(): void
    {
        $shift = $this->makeShift('Pagi');

        $this->actingAs($this->supervisor->user)
            ->put(route('master.shift.update', $shift), $this->payload(['name' => 'Pagi', 'end_date' => '16:00']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('master_shift', ['id' => $shift->id, 'end_date' => '16:00:00']);
    }

    public function test_nama_milik_shift_lain_ditolak(): void
    {
        $this->makeShift('Pagi');
        $siang = $this->makeShift('Siang');

        $this->actingAs($this->supervisor->user)
            ->post(route('master.shift.store'), $this->payload(['name' => 'Pagi']))
            ->assertSessionHasErrors(['name' => 'Nama shift sudah digunakan.']);

        $this->put(route('master.shift.update', $siang), $this->payload(['name' => 'Pagi']))
            ->assertSessionHasErrors(['name' => 'Nama shift sudah digunakan.']);
    }

    #[DataProvider('isianTidakValid')]
    public function test_isian_tidak_valid_ditolak(array $override, string $kolom): void
    {
        $this->actingAs($this->supervisor->user)
            ->post(route('master.shift.store'), $this->payload($override))
            ->assertSessionHasErrors($kolom);

        $this->assertDatabaseCount('master_shift', 0);
    }

    public static function isianTidakValid(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 51)], 'name'],
            'jam mulai salah format' => [['start_date' => '25:00'], 'start_date'],
            'jam selesai kosong' => [['end_date' => ''], 'end_date'],
            'jam selesai sama dengan jam mulai' => [['start_date' => '07:00', 'end_date' => '07:00'], 'end_date'],
        ];
    }

    public function test_tidak_ada_route_hapus(): void
    {
        $shift = $this->makeShift();

        $this->assertFalse(Route::has('master.shift.destroy'));

        $this->actingAs($this->supervisor->user)
            ->delete('/master/shift/'.$shift->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('master_shift', ['id' => $shift->id]);
    }

    private function payload(array $override = []): array
    {
        return $override + ['name' => 'Baru', 'start_date' => '07:00', 'end_date' => '15:00'];
    }
}