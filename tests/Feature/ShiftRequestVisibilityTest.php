<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Employee;
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
        $shift = $this->makeShift();

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

    public function test_leader_melihat_pengajuan_sedivisi_dan_tidak_melihat_divisi_lain(): void
    {
        $user = $this->leaderBar->user;

        $this->assertEqualsCanonicalizing(
            [$this->izinLeader->id, $this->izinBar->id, $this->izinBar2->id],
            $this->listedIds($user),
        );

        $this->actingAs($user)->get($this->detailUrl($this->izinBar2))->assertOk();
        $this->actingAs($user)->get($this->detailUrl($this->izinDapur))->assertForbidden();
        $this->actingAs($user)->get($this->detailUrl($this->tukarDapurKeBar))->assertForbidden();
    }

    public function test_supervisor_melihat_semua_pengajuan(): void
    {
        $user = $this->supervisor->user;

        $this->assertCount(5, $this->listedIds($user));
        $this->actingAs($user)->get($this->detailUrl($this->izinDapur))->assertOk();
    }

    public function test_kartu_ringkasan_mengikuti_pembatasan_yang_sama(): void
    {
        $this->assertSame(
            ['total' => 2, 'disetujui' => 0, 'ditolak' => 0, 'menunggu' => 2],
            $this->statsFor($this->empBar->user),
        );

        $this->assertSame(
            ['total' => 3, 'disetujui' => 1, 'ditolak' => 0, 'menunggu' => 2],
            $this->statsFor($this->leaderBar->user),
        );

        $this->assertSame(
            ['total' => 5, 'disetujui' => 1, 'ditolak' => 0, 'menunggu' => 4],
            $this->statsFor($this->supervisor->user),
        );
    }

    public function test_filter_status_tetap_berjalan_di_dalam_pembatasan_role(): void
    {
        $response = $this->actingAs($this->leaderBar->user)
            ->get(route('pengajuan.index', ['status' => RequestStatus::Disetujui->value]))
            ->assertOk();

        $this->assertSame([$this->izinBar2->id], $response->viewData('requests')->pluck('id')->all());
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
