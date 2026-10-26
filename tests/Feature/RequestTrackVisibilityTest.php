<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class RequestTrackVisibilityTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private const TRACK = 'Proses Persetujuan';

    private Employee $supervisor;
    private Employee $leader;
    private Employee $pengaju;
    private Employee $rekan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->leader = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $this->pengaju = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->rekan = $this->makeEmployee(Role::KARYAWAN, 'Kasir', 'Bima Kasir');
    }

    #[DataProvider('statusRiwayat')]
    public function test_supervisor_melihat_track_setelah_pengajuan_menjadi_riwayat(RequestStatus $status): void
    {
        $this->assertTrack($this->supervisor->user, $this->swapRequest($status), true);
    }

    public function test_supervisor_tidak_melihat_track_selama_pengajuan_menunggu(): void
    {
        $this->assertTrack($this->supervisor->user, $this->swapRequest(RequestStatus::Menunggu), false);
    }

    #[DataProvider('semuaStatus')]
    public function test_leader_dan_rekan_tidak_pernah_melihat_track(RequestStatus $status): void
    {
        $request = $this->swapRequest($status);

        $this->assertTrack($this->leader->user, $request, false);
        $this->assertTrack($this->rekan->user, $request, false);
    }

    #[DataProvider('semuaStatus')]
    public function test_pengaju_selalu_melihat_track(RequestStatus $status): void
    {
        $this->assertTrack($this->pengaju->user, $this->swapRequest($status), true);
    }

    public static function statusRiwayat(): array
    {
        return [
            'disetujui' => [RequestStatus::Disetujui],
            'ditolak' => [RequestStatus::Ditolak],
        ];
    }

    public static function semuaStatus(): array
    {
        return self::statusRiwayat() + ['menunggu' => [RequestStatus::Menunggu]];
    }

    private function swapRequest(RequestStatus $status): ShiftRequest
    {
        $shift = $this->makeShift();
        $date = '2026-10-20';

        return $this->makeSwapRequest(
            $this->assign($this->pengaju, $shift, $date),
            $this->assign($this->rekan, $shift, $date),
            ['status' => $status],
        );
    }

    private function assertTrack(User $user, ShiftRequest $request, bool $expected): void
    {
        $response = $this->actingAs($user)
            ->get(route('pengajuan.show', $request))
            ->assertOk();

        if ($expected) {
            $response->assertSee(self::TRACK);
        } else {
            $response->assertDontSee(self::TRACK);
        }
    }
}
