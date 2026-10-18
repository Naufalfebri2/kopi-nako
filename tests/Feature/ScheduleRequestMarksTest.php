<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class ScheduleRequestMarksTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private ScheduleService $service;
    private Employee $supervisor;
    private Employee $leaderBar;
    private Employee $employee;
    private $days;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();
        $this->service = app(ScheduleService::class);
        $this->days = $this->service->weekDays(Carbon::parse('2026-10-19')); // Senin

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leaderBar = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->employee = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');

        $this->seedWeek();
    }

    public function test_grid_supervisor_menandai_pengajuan_sesuai_statusnya(): void
    {
        $marks = $this->marksOf($this->supervisor);

        $this->assertSame(['kind' => 'menunggu', 'label' => 'Menunggu izin'], $marks[0]);
        $this->assertSame(['kind' => 'ditolak', 'label' => 'Ditolak'], $marks[1]);
        $this->assertSame(['kind' => 'izin', 'label' => 'Izin'], $marks[2]);
        $this->assertNull($marks[3]); // tukar disetujui: jadwal sudah berpindah, tanpa penanda
        $this->assertSame(['kind' => 'menunggu', 'label' => 'Menunggu izin'], $marks[4]); // yang terbaru
        $this->assertSame(['kind' => 'menunggu', 'label' => 'Menunggu tukar'], $marks[5]);
        $this->assertNull($marks[6]);
    }

    public function test_tampilan_grid_memuat_penanda_dan_izin_menggantikan_shift(): void
    {
        $rows = $this->service->weeklyRows($this->days, null, $this->supervisor->user);

        $html = view('jadwal._mingguan', [
            'days' => $this->days,
            'rows' => $rows,
            'canEdit' => false,
        ])->render();

        $this->assertSame(2, substr_count($html, 'Menunggu izin')); // Senin dan Jumat
        $this->assertSame(1, substr_count($html, 'Menunggu tukar'));
        $this->assertSame(1, substr_count($html, 'Ditolak'));
        $this->assertStringContainsString('title="Izin disetujui"', $html);
    }

    public function test_leader_dan_karyawan_tidak_mendapat_penanda(): void
    {
        foreach ([$this->leaderBar, $this->employee] as $viewer) {
            $this->assertSame([null, null, null, null, null, null, null], $this->marksOf($viewer));
        }
    }

    public function test_pengajuan_dibaca_dengan_satu_query_untuk_seluruh_minggu(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service->weeklyRows($this->days, null, $this->supervisor->user);

        $requestQueries = collect(DB::getQueryLog())
            ->filter(fn (array $log) => str_contains($log['query'], 'shift_request'));

        $this->assertCount(1, $requestQueries);
    }

    // Senin menunggu izin, Selasa ditolak, Rabu izin disetujui, Kamis tukar disetujui,
    // Jumat ditolak lalu menunggu (yang terbaru menang), Sabtu menunggu tukar, Minggu libur
    private function seedWeek(): void
    {
        $shift = $this->makeShift();
        $partner = $this->makeEmployee(Role::KARYAWAN, 'Kasir', 'Bima Kasir');
        $date = fn (int $i) => $this->days[$i]->toDateString();

        $this->makeRequest($this->assign($this->employee, $shift, $date(0)));

        $this->makeRequest($this->assign($this->employee, $shift, $date(1)), ['status' => RequestStatus::Ditolak]);

        $this->makeRequest($this->assign($this->employee, $shift, $date(2)), ['status' => RequestStatus::Disetujui]);

        // Tukar disetujui: jadwal benar-benar ditukar lewat service, seperti saat Supervisor menyetujui
        $mine = $this->assign($this->employee, $shift, $date(3));
        $theirs = $this->assign($partner, $shift, $date(3));
        $swap = $this->makeSwapRequest($mine, $theirs, ['status' => RequestStatus::Disetujui]);
        $this->service->swapAssignments($swap);

        $friday = $this->assign($this->employee, $shift, $date(4));
        $this->makeRequest($friday, ['status' => RequestStatus::Ditolak]);
        $this->makeRequest($friday); // lebih baru, masih menunggu

        $this->makeSwapRequest(
            $this->assign($this->employee, $shift, $date(5)),
            $this->assign($partner, $shift, $date(5)),
        );
    }

    private function marksOf(Employee $viewer): array
    {
        return $this->service
            ->weeklyRows($this->days, null, $viewer->user)
            ->first(fn (array $row) => $row['employee']->is($this->employee))['marks'];
    }
}
