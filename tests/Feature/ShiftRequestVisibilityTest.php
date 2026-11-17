<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class ShiftRequestVisibilityTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private MasterShift $shift;

    private Employee $supervisor;
    private Employee $leaderBar;
    private Employee $empBar;
    private Employee $empBar2;
    private Employee $empDapur;

    private ShiftRequest $izinLeader;
    private ShiftRequest $izinBar;
    private ShiftRequest $izinBar2;
    private ShiftRequest $izinDapur;
    private ShiftRequest $tukarDapurKeBar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();
        $this->shift = $this->makeShift();
        $shift = $this->shift;

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leaderBar = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->empBar = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->empBar2 = $this->makeEmployee(Role::KARYAWAN, 'Kasir', 'Bima Kasir');
        $this->empDapur = $this->makeEmployee(Role::KARYAWAN, 'Koki', 'Dewi Dapur');

        $date = '2026-10-20';

        $this->izinLeader = $this->makeRequest($this->assign($this->leaderBar, $shift, $date));
        $this->izinBar = $this->makeRequest($this->assign($this->empBar, $shift, $date));
        $this->izinBar2 = $this->makeRequest(
            $this->assign($this->empBar2, $shift, $date),
            ['status' => RequestStatus::Disetujui],
        );
        $this->izinDapur = $this->makeRequest($this->assign($this->empDapur, $shift, $date));

        // Karyawan Dapur mengajak Karyawan Bar tukar shift pada tanggal lain
        $this->tukarDapurKeBar = $this->makeSwapRequest(
            $this->assign($this->empDapur, $shift, '2026-10-21'),
            $this->assign($this->empBar, $shift, '2026-10-21'),
        );
    }

    public function test_karyawan_hanya_melihat_pengajuan_miliknya_dan_yang_menunjuknya_sebagai_rekan(): void
    {
        $user = $this->empBar->user;

        $this->assertEqualsCanonicalizing(
            [$this->izinBar->id, $this->tukarDapurKeBar->id],
            $this->listedIds($user),
        );

        $this->actingAs($user)->get($this->detailUrl($this->izinBar2))->assertForbidden();
        $this->actingAs($user)->get($this->detailUrl($this->izinDapur))->assertForbidden();
    }

    public function test_leader_melihat_pengajuan_menunggu_sedivisi_dan_tidak_melihat_divisi_lain(): void
    {
        $user = $this->leaderBar->user;

        // izinBar2 sudah Disetujui (riwayat), jadi tidak lagi tampil di daftar Leader
        $this->assertEqualsCanonicalizing(
            [$this->izinLeader->id, $this->izinBar->id],
            $this->listedIds($user),
        );

        $this->actingAs($user)->get($this->detailUrl($this->izinDapur))->assertForbidden();
        $this->actingAs($user)->get($this->detailUrl($this->tukarDapurKeBar))->assertForbidden();
    }

    public function test_supervisor_melihat_semua_pengajuan_termasuk_riwayat(): void
    {
        $user = $this->supervisor->user;

        $this->assertCount(5, $this->listedIds($user));
        $this->assertContains($this->izinBar2->id, $this->listedIds($user));
        $this->actingAs($user)->get($this->detailUrl($this->izinDapur))->assertOk();
    }

    public function test_kartu_ringkasan_mengikuti_pembatasan_yang_sama(): void
    {
        $this->assertSame(
            ['total' => 2, 'disetujui' => 0, 'ditolak' => 0, 'menunggu' => 2],
            $this->statsFor($this->empBar->user),
        );

        // Leader tidak lagi menghitung riwayat
        $this->assertSame(
            ['total' => 2, 'disetujui' => 0, 'ditolak' => 0, 'menunggu' => 2],
            $this->statsFor($this->leaderBar->user),
        );

        $this->assertSame(
            ['total' => 5, 'disetujui' => 1, 'ditolak' => 0, 'menunggu' => 4],
            $this->statsFor($this->supervisor->user),
        );
    }

    public function test_supervisor_dapat_memfilter_status_riwayat(): void
    {
        $response = $this->actingAs($this->supervisor->user)
            ->get(route('pengajuan.index', ['status' => RequestStatus::Disetujui->value]))
            ->assertOk();

        $this->assertSame([$this->izinBar2->id], $response->viewData('requests')->pluck('id')->all());
    }

    public function test_leader_tidak_bisa_membuka_riwayat_lewat_filter_status(): void
    {
        $response = $this->actingAs($this->leaderBar->user)
            ->get(route('pengajuan.index', ['status' => RequestStatus::Disetujui->value]))
            ->assertOk();

        // Filter status diabaikan untuk Leader; izinBar2 (Disetujui) tetap tidak tampil
        $this->assertEqualsCanonicalizing(
            [$this->izinLeader->id, $this->izinBar->id],
            $response->viewData('requests')->pluck('id')->all(),
        );
    }

    public function test_karyawan_tidak_melihat_pengajuannya_sendiri_yang_sudah_diputuskan(): void
    {
        $ditolak = $this->makeRequest(
            $this->assign($this->empBar, $this->shift, '2026-10-22'),
            ['status' => RequestStatus::Ditolak],
        );
        $disetujui = $this->makeRequest(
            $this->assign($this->empBar, $this->shift, '2026-10-23'),
            ['status' => RequestStatus::Disetujui],
        );

        $ids = $this->listedIds($this->empBar->user);

        $this->assertNotContains($ditolak->id, $ids);
        $this->assertNotContains($disetujui->id, $ids);
        $this->assertEqualsCanonicalizing([$this->izinBar->id, $this->tukarDapurKeBar->id], $ids);
    }

    public function test_karyawan_tidak_bisa_membuka_riwayat_lewat_filter_status(): void
    {
        $disetujui = $this->makeRequest(
            $this->assign($this->empBar, $this->shift, '2026-10-23'),
            ['status' => RequestStatus::Disetujui],
        );

        $response = $this->actingAs($this->empBar->user)
            ->get(route('pengajuan.index', ['status' => RequestStatus::Disetujui->value]))
            ->assertOk();

        $ids = $response->viewData('requests')->pluck('id')->all();

        $this->assertNotContains($disetujui->id, $ids);
        $this->assertEqualsCanonicalizing([$this->izinBar->id, $this->tukarDapurKeBar->id], $ids);
    }

    public function test_filter_status_dan_kartu_riwayat_hanya_tampil_untuk_supervisor(): void
    {
        foreach ([$this->empBar->user, $this->leaderBar->user] as $user) {
            $this->actingAs($user)->get(route('pengajuan.index'))
                ->assertOk()
                ->assertDontSee('Semua Status')
                ->assertDontSee('Total Diajukan');
        }

        $this->actingAs($this->supervisor->user)->get(route('pengajuan.index'))
            ->assertOk()
            ->assertSee('Semua Status')
            ->assertSee('Total Diajukan');
    }

    public function test_stepper_hanya_tampil_untuk_pengaju(): void
    {
        $this->actingAs($this->empBar->user)->get($this->detailUrl($this->izinBar))
            ->assertOk()
            ->assertSee('Proses Persetujuan');

        // Rekan tukar, Leader sedivisi, dan Supervisor tetap melihat info dasar tanpa stepper
        foreach ([
            [$this->empBar->user, $this->tukarDapurKeBar],
            [$this->leaderBar->user, $this->izinBar],
            [$this->supervisor->user, $this->izinBar],
        ] as [$viewer, $request]) {
            $this->actingAs($viewer)->get($this->detailUrl($request))
                ->assertOk()
                ->assertDontSee('Proses Persetujuan')
                ->assertSee('Jenis Pengajuan')
                ->assertSee('Alasan Pengajuan');
        }
    }

    private function listedIds(User $user): array
    {
        return $this->actingAs($user)
            ->get(route('pengajuan.index'))
            ->assertOk()
            ->viewData('requests')
            ->pluck('id')
            ->all();
    }

    private function statsFor(User $user): array
    {
        return $this->actingAs($user)->get(route('pengajuan.index'))->assertOk()->viewData('stats');
    }

    private function detailUrl(ShiftRequest $request): string
    {
        return route('pengajuan.show', $request); // sesuaikan bila nama route detail berbeda
    }
}
