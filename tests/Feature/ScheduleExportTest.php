<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class ScheduleExportTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private const DATE = '2026-10-20'; // Selasa

    private Employee $supervisor;
    private Employee $leaderBar;
    private Employee $empBar;
    private Employee $empDapur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();
        $shift = $this->makeShift();

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leaderBar = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->empBar = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->empDapur = $this->makeEmployee(Role::KARYAWAN, 'Koki', 'Dewi Dapur');

        $this->assign($this->empBar, $shift, self::DATE);
        $this->assign($this->empDapur, $shift, self::DATE);
    }

    public function test_supervisor_mengunduh_semua_karyawan(): void
    {
        $response = $this->actingAs($this->supervisor->user)
            ->get(route('jadwal.export', ['tanggal' => self::DATE]))
            ->assertOk();

        $this->assertStringContainsString(
            'jadwal-shift-mingguan-2026-10-19.xlsx',
            (string) $response->headers->get('content-disposition')
        );

        $rows = $this->rowsOf($response->streamedContent());
        $names = array_column($rows, 0);

        $this->assertEqualsCanonicalizing(
            ['Budi Bar', 'Dewi Dapur', 'Lia Leader', 'Sari Supervisor'],
            $names
        );
    }

    public function test_leader_hanya_mengunduh_dirinya_dan_sedivisi(): void
    {
        $response = $this->actingAs($this->leaderBar->user)
            ->get(route('jadwal.export', ['tanggal' => self::DATE]))
            ->assertOk();

        $names = array_column($this->rowsOf($response->streamedContent()), 0);

        $this->assertEqualsCanonicalizing(['Budi Bar', 'Lia Leader'], $names);
    }

    public function test_karyawan_mendapat_403(): void
    {
        $this->actingAs($this->empBar->user)
            ->get(route('jadwal.export'))
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('jadwal.export'))->assertRedirect(route('login'));
    }

    public function test_sel_berisi_nama_shift_atau_libur(): void
    {
        $response = $this->actingAs($this->supervisor->user)
            ->get(route('jadwal.export', ['tanggal' => self::DATE]))
            ->assertOk();

        $byName = collect($this->rowsOf($response->streamedContent()))->keyBy(0);

        $this->assertStringContainsString('Pagi', (string) $byName['Budi Bar'][3]); // Selasa
        $this->assertSame('Libur', $byName['Budi Bar'][2]); // Senin
        $this->assertSame('Barista', $byName['Budi Bar'][1]);
    }

    public function test_filter_jabatan_dan_tampilan_harian_diikuti(): void
    {
        $response = $this->actingAs($this->supervisor->user)
            ->get(route('jadwal.export', [
                'tampilan' => 'harian',
                'tanggal' => self::DATE,
                'jabatan' => $this->empDapur->job_id,
            ]))
            ->assertOk();

        $this->assertStringContainsString(
            'jadwal-shift-harian-2026-10-20.xlsx',
            (string) $response->headers->get('content-disposition')
        );

        $rows = $this->rowsOf($response->streamedContent());

        $this->assertCount(1, $rows);
        $this->assertSame('Dewi Dapur', $rows[0][0]);
        $this->assertStringContainsString('Pagi', (string) $rows[0][2]);
    }

    public function test_nama_berawalan_sama_dengan_tidak_dibaca_sebagai_rumus(): void
    {
        $user = User::where('name', 'Budi Bar')->firstOrFail();
        $user->update(['name' => '=1+1']);

        $response = $this->actingAs($this->supervisor->user)
            ->get(route('jadwal.export', ['tanggal' => self::DATE]))
            ->assertOk();

        $this->assertContains('=1+1', array_column($this->rowsOf($response->streamedContent()), 0));
    }

    // Baris data (setelah judul, baris kosong, dan header) dari isi file xlsx
    private function rowsOf(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kn-xlsx');
        file_put_contents($path, $content);

        try {
            $sheet = (new Xlsx())->load($path)->getActiveSheet();
            $all = $sheet->toArray(null, false, false, false);
        } finally {
            unlink($path);
        }

        return array_values(array_slice($all, 3));
    }
}
