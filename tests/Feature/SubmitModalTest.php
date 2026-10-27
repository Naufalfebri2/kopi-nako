<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class SubmitModalTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();
        $this->karyawan = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->assign($this->karyawan, $this->makeShift(), today()->addDay()->toDateString());
    }

    public function test_form_ajukan_berupa_pop_up_bukan_panel_samping(): void
    {
        $this->actingAs($this->karyawan->user)
            ->get(route('pengajuan.index'))
            ->assertOk()
            ->assertSee('id="submitModal"', false)
            ->assertDontSee('id="submitDrawer"', false)
            ->assertSee('id="detailDrawer"', false); // panel detail tetap panel samping
    }

    public function test_petunjuk_alasan_sesuai_aturan_server(): void
    {
        $this->actingAs($this->karyawan->user)
            ->get(route('pengajuan.index'))
            ->assertOk()
            ->assertSee('minlength="20"', false)
            ->assertSee('minimal 20 karakter')
            ->assertDontSee('minimal 10 karakter');
    }
}
