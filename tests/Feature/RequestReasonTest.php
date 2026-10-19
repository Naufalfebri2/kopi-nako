<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class RequestReasonTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private Employee $employee;
    private int $detailId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDivisions();
        $this->makeEmployee(Role::SUPERVISOR, 'Manajer', 'Sari Supervisor');
        $this->employee = $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->detailId = $this->assign($this->employee, $this->makeShift(), today()->addDay()->toDateString())->id;
    }

    public function test_alasan_yang_jelas_diterima(): void
    {
        $this->submit('Anak saya sakit demam dan harus dibawa ke dokter pagi ini.')
            ->assertSessionHas('notice')
            ->assertSessionHasNoErrors();
    }

    #[DataProvider('alasanDitolak')]
    public function test_alasan_tidak_jelas_ditolak(string $reason, string $pesan): void
    {
        $this->submit($reason)->assertSessionHasErrors(['reason' => $pesan]);
    }

    public static function alasanDitolak(): array
    {
        return [
            'acak pendek' => ['aadadasd', 'Alasan minimal 20 karakter. Jelaskan dengan kalimat yang lengkap.'],
            'acak 10 huruf' => ['xacaxzcasc', 'Alasan minimal 20 karakter. Jelaskan dengan kalimat yang lengkap.'],
            'kurang dari 4 kata' => ['keperluanmendadakkeluarga besar', 'Alasan minimal 4 kata. Jelaskan dengan kalimat yang lengkap.'],
            'karakter berulang' => ['Saya ada keperluan keluargaaaa mendadak', 'Alasan tidak boleh memuat karakter yang sama berulang 4 kali berturut-turut.'],
            'kata tanpa vokal' => ['Saya ada keperluan bcdfg mendadak sekali', 'Alasan tidak jelas: kata "bcdfg" tidak mengandung huruf vokal. Tulis dengan kata yang utuh.'],
        ];
    }

    public function test_alasan_lebih_dari_500_karakter_ditolak(): void
    {
        $this->submit(str_repeat('keperluan keluarga ', 30))
            ->assertSessionHasErrors(['reason' => 'Alasan maksimal 500 karakter.']);
    }

    private function submit(string $reason)
    {
        return $this->actingAs($this->employee->user)->post(route('pengajuan.store'), [
            'jenis' => 'izin',
            'detail_id' => $this->detailId,
            'leave_id' => $this->leaveTypeId(),
            'reason' => $reason,
        ]);
    }
}
