<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterShift;
use App\Models\Role;
use App\Services\RequestSubmission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class SubmitScheduleFieldsTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $karyawan;
    private MasterShift $shift;
    private Carbon $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();
        $this->shift = $this->makeShift();
        $this->date = today()->addDays(3)->locale('id');
        $this->karyawan = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->assign($this->karyawan, $this->shift, $this->date->toDateString());
    }

    public function test_opsi_jadwal_memuat_hari_tanggal_tahun_dan_shift_terpisah(): void
    {
        $option = app(RequestSubmission::class)->options($this->karyawan)->first();

        $this->assertSame($this->date->translatedFormat('l'), $option['hari']);
        $this->assertSame($this->date->translatedFormat('j M'), $option['tanggal']);
        $this->assertSame($this->date->format('Y'), $option['tahun']);
        $this->assertSame($this->namaShift(), $option['shift']);
    }

    public function test_label_lama_tidak_berubah(): void
    {
        $option = app(RequestSubmission::class)->options($this->karyawan)->first();

        $this->assertSame(
            $this->date->translatedFormat('D, j M Y').' · '.$this->namaShift(),
            $option['label']
        );
    }

    public function test_form_menampilkan_empat_isian_jadwal_terpisah(): void
    {
        $this->actingAs($this->karyawan->user)
            ->get(route('pengajuan.index'))
            ->assertOk()
            ->assertSee('data-hari="'.$this->date->translatedFormat('l').'"', false)
            ->assertSee('data-tanggal="'.$this->date->translatedFormat('j M').'"', false)
            ->assertSee('data-tahun="'.$this->date->format('Y').'"', false)
            ->assertSee('data-shift="'.$this->namaShift().'"', false)
            ->assertSee('id="infoHari"', false)
            ->assertSee('id="infoTanggal"', false)
            ->assertSee('id="infoTahun"', false)
            ->assertSee('id="infoShift"', false);
    }

    private function namaShift(): string
    {
        return $this->shift->name.' ('.$this->shift->time_range.')';
    }
}
