<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeCode;
use App\Services\RolePairing;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class EmployeeCodeTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    public function test_kode_pertama_pada_bulan_itu_dimulai_dari_001(): void
    {
        $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar'); // kode lama berformat angka

        $this->assertSame('261001B001', app(EmployeeCode::class)->next(Carbon::parse('2026-10-21')));
    }

    public function test_nomor_urut_melanjutkan_kode_terbesar_pada_bulan_yang_sama(): void
    {
        $this->setCode($this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar'), '261001B007');
        $codes = app(EmployeeCode::class);

        $this->assertSame('261001B008', $codes->next(Carbon::parse('2026-10-30')));
        $this->assertSame('261101B001', $codes->next(Carbon::parse('2026-11-02')));
    }

    public function test_nomor_urut_lebih_dari_999_ditolak(): void
    {
        $this->setCode($this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar'), '261001B999');

        $this->expectException(DomainException::class);

        app(EmployeeCode::class)->next(Carbon::parse('2026-10-21'));
    }

    public function test_format_kode_dikenali(): void
    {
        $codes = app(EmployeeCode::class);

        $this->assertTrue($codes->isValid('261001B001'));

        foreach (['0000000000000001', '261001B01', '261002B001', '26101B001', null] as $tidakValid) {
            $this->assertFalse($codes->isValid($tidakValid));
        }
    }

    public function test_karyawan_baru_mendapat_kode_otomatis_dan_isian_nik_diabaikan(): void
    {
        $supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');

        $this->tambahKaryawan($supervisor, 'baru@example.test', ['nik' => '9999999999']);

        $this->assertSame(now()->format('ym').'01B001', $this->kodeDari('baru@example.test'));
    }

    public function test_dua_karyawan_berturut_turut_mendapat_nomor_urut_berbeda(): void
    {
        $supervisor = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');

        $this->tambahKaryawan($supervisor, 'satu@example.test');
        $this->tambahKaryawan($supervisor, 'dua@example.test');

        $this->assertSame(now()->format('ym').'01B001', $this->kodeDari('satu@example.test'));
        $this->assertSame(now()->format('ym').'01B002', $this->kodeDari('dua@example.test'));
    }

    public function test_pratinjau_tidak_mengubah_data(): void
    {
        $employee = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $lama = $employee->nik;

        $this->artisan('karyawan:ubah-kode', ['--periode' => '2610'])->assertSuccessful();

        $this->assertSame($lama, $employee->fresh()->nik);
    }

    public function test_jalankan_memberi_nomor_urut_menurut_id(): void
    {
        $a = $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $b = $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
        $c = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');

        $this->artisan('karyawan:ubah-kode', ['--periode' => '2610', '--jalankan' => true])->assertSuccessful();

        $this->assertSame('261001B001', $a->fresh()->nik);
        $this->assertSame('261001B002', $b->fresh()->nik);
        $this->assertSame('261001B003', $c->fresh()->nik);
    }

    public function test_jalankan_melanjutkan_nomor_yang_sudah_ada_dan_tidak_mengulang(): void
    {
        $sudah = $this->setCode($this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor'), '261001B005');
        $lama = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');

        $this->artisan('karyawan:ubah-kode', ['--periode' => '2610', '--jalankan' => true])->assertSuccessful();

        $this->assertSame('261001B005', $sudah->fresh()->nik);
        $this->assertSame('261001B006', $lama->fresh()->nik);

        $this->artisan('karyawan:ubah-kode', ['--periode' => '2610', '--jalankan' => true])
            ->expectsOutputToContain('Semua karyawan sudah memakai format baru.')
            ->assertSuccessful();

        $this->assertSame('261001B006', $lama->fresh()->nik);
    }

    public function test_periode_tidak_valid_ditolak(): void
    {
        $employee = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $lama = $employee->nik;

        $this->artisan('karyawan:ubah-kode', ['--periode' => 'abc', '--jalankan' => true])->assertFailed();

        $this->assertSame($lama, $employee->fresh()->nik);
    }

    private function setCode(Employee $employee, string $code): Employee
    {
        $employee->forceFill(['nik' => $code])->save();

        return $employee;
    }

    private function tambahKaryawan(Employee $supervisor, string $email, array $override = []): void
    {
        // Pairing role dan jabatan dipalsukan agar test tidak bergantung pada daftar aturan asli
        $this->partialMock(RolePairing::class, fn ($mock) => $mock->shouldReceive('allows')->andReturnTrue());

        $this->actingAs($supervisor->user)
            ->post(route('karyawan.store'), $override + [
                'name' => 'Karyawan Baru',
                'email' => $email,
                'no_phone' => '081234567890',
                'job_id' => $supervisor->job_id,
                'id_role' => $supervisor->user->id_role,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasNoErrors();
    }

    private function kodeDari(string $email): string
    {
        return User::where('email', $email)->firstOrFail()->employee->nik;
    }
}
