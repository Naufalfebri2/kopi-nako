<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\ShiftRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class DetailModalTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $supervisor;
    private Employee $leader;
    private Employee $pengaju;
    private Employee $rekan;
    private ShiftRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leader = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->pengaju = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->rekan = $this->makeEmployee(Role::KARYAWAN, 'Kasir', 'Bima Kasir');

        $shift = $this->makeShift();
        $date = today()->addDay()->toDateString();

        $this->request = $this->makeSwapRequest(
            $this->assign($this->pengaju, $shift, $date),
            $this->assign($this->rekan, $shift, $date),
        );
    }

    public function test_halaman_pengajuan_memakai_pop_up_untuk_detail(): void
    {
        foreach ($this->semuaPeran() as $employee) {
            $this->actingAs($employee->user)
                ->get(route('pengajuan.index'))
                ->assertOk()
                ->assertSee('id="detailModal"', false)
                ->assertDontSee('id="detailDrawer"', false);
        }
    }

    public function test_isi_detail_berupa_bagian_pop_up_untuk_semua_peran(): void
    {
        foreach ($this->semuaPeran() as $employee) {
            $this->actingAs($employee->user)
                ->get(route('pengajuan.show', $this->request))
                ->assertOk()
                ->assertSee('class="modal-body"', false)
                ->assertSee('class="modal-footer', false)
                ->assertDontSee('offcanvas', false)
                ->assertDontSee('kn-drawer', false);
        }
    }

    public function test_tombol_keputusan_untuk_yang_berwenang_dan_tutup_untuk_lainnya(): void
    {
        $this->actingAs($this->rekan->user)
            ->get(route('pengajuan.show', $this->request))
            ->assertSee('Setuju Tukar')
            ->assertSee('Tolak');

        $this->actingAs($this->pengaju->user)
            ->get(route('pengajuan.show', $this->request))
            ->assertDontSee('Setuju Tukar')
            ->assertSee('data-bs-dismiss="modal"', false);
    }

    private function semuaPeran(): array
    {
        return [$this->supervisor, $this->leader, $this->pengaju, $this->rekan];
    }
}
