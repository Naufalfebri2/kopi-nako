<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class RouteRateLimitTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $supervisor;
    private Employee $supervisorLain;
    private Employee $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->supervisorLain = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Bayu Supervisor');
        $this->karyawan = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');

        // Route uji yang ringan, dengan middleware yang sama seperti grup auth
        Route::middleware(['web', 'auth', 'throttle:aplikasi'])->get('/_uji/baca', fn () => 'ok');
    }

    public function test_membaca_dibatasi_120_per_menit_lalu_429(): void
    {
        $this->actingAs($this->karyawan->user);

        for ($i = 0; $i < 120; $i++) {
            $this->get('/_uji/baca')->assertOk();
        }

        $this->get('/_uji/baca')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSee('Terlalu banyak permintaan');
    }

    public function test_menulis_dibatasi_30_per_menit_dan_kembali_dengan_pesan(): void
    {
        $this->actingAs($this->supervisor->user);

        for ($i = 0; $i < 30; $i++) {
            $this->post(route('pengajuan.approve-all'))->assertSessionHas('notice');
        }

        $this->post(route('pengajuan.approve-all'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('Terlalu banyak permintaan', session('error'));
    }

    public function test_jatah_dihitung_per_pengguna_bukan_per_ip(): void
    {
        $this->actingAs($this->supervisor->user);

        for ($i = 0; $i < 31; $i++) {
            $this->post(route('pengajuan.approve-all'));
        }

        $this->flushSession();

        $this->actingAs($this->supervisorLain->user)
            ->post(route('pengajuan.approve-all'))
            ->assertSessionHas('notice')
            ->assertSessionMissing('error');
    }

    public function test_ekspor_dibatasi_10_per_menit_dengan_keranjang_sendiri(): void
    {
        $this->actingAs($this->supervisor->user);

        for ($i = 0; $i < 10; $i++) {
            $this->get(route('jadwal.export'))->assertOk();
        }

        $this->get(route('jadwal.export'))->assertStatus(429);

        // Jatah membaca halaman lain tidak ikut habis
        $this->get(route('jadwal.index'))->assertOk();
    }

    public function test_semua_route_yang_wajib_login_memakai_batas_aplikasi(): void
    {
        $tanpaBatas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => in_array('auth', $route->gatherMiddleware(), true))
            ->reject(fn (LaravelRoute $route) => in_array('throttle:aplikasi', $route->gatherMiddleware(), true))
            ->map(fn (LaravelRoute $route) => $route->methods()[0].' /'.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $tanpaBatas);
    }
}
