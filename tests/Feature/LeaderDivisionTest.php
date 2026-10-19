<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderDivisionTest extends TestCase
{
    use RefreshDatabase;

    private Employee $spv;
    private Employee $anisa;       // Cook Helper, Siang
    private Employee $rahma;       // Cook, Pagi (rekan tukar)
    private Employee $fatah;       // Lead Cook, tidak dijadwalkan (libur)
    private Employee $hendri;      // Lead Service, Leader shift Siang (shift yang sama dengan Anisa)

    protected function setUp(): void
    {
        parent::setUp();

        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $leaderRole = Role::create(['name' => Role::LEADER]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $pagi = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);
        $siang = MasterShift::create(['name' => 'Siang', 'start_date' => '15:00:00', 'end_date' => '21:00:00']);

        $make = fn (Role $role, string $job, string $name, string $email, string $nik) => Employee::create([
            'nik' => $nik, 'job_id' => Job::firstOrCreate(['name' => $job])->id,
            'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
            'id_user' => User::create([
                'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
            ])->id,
        ]);

        $this->spv = $make($spvRole, 'Supervisor', 'Juandi', 'spv@example.com', '1');
        $this->anisa = $make($staffRole, 'Cook Helper', 'Anisa', 'anisa@example.com', '2');
        $this->rahma = $make($staffRole, 'Cook', 'Rahma', 'rahma@example.com', '3');
        $this->fatah = $make($leaderRole, 'Lead Cook', 'Fatah', 'fatah@example.com', '4');
        $this->hendri = $make($leaderRole, 'Lead Service', 'Hendriansyah', 'hendri@example.com', '5');

        $date = today()->addDay()->toDateString();

        $schedule = fn (Employee $e, MasterShift $s, bool $lead = false) => $this->actingAs($this->spv->user)
            ->post(route('jadwal.store'), array_filter([
                'employee_id' => $e->id, 'shift_id' => $s->id, 'date' => $date, 'status_lead' => $lead ? 1 : null,
            ]));

        $schedule($this->anisa, $siang);
        $schedule($this->rahma, $pagi);
        $schedule($this->hendri, $siang, true); // Leader shift Siang, tetapi divisi Service
        // Fatah (Lead Cook) sengaja tidak dijadwalkan: libur.
    }

    private function detailId(Employee $employee): int
    {
        return ShiftScheduleDetail::where('id_employee', $employee->id)->firstOrFail()->id;
    }

    public function test_verifikasi_leader_hanya_oleh_leader_sedivisi_meski_libur(): void
    {
        $this->actingAs($this->anisa->user)->post(route('pengajuan.store'), [
            'jenis' => 'tukar',
            'detail_id' => $this->detailId($this->anisa),
            'partner_detail_id' => $this->detailId($this->rahma),
            'reason' => 'Ada keperluan keluarga di sore hari.',
        ])->assertSessionHas('notice');

        $request = ShiftRequest::latest('id')->firstOrFail();
        $decide = fn (User $u, string $k) => $this->actingAs($u)->post(route('pengajuan.decide', $request), ['keputusan' => $k]);

        $decide($this->rahma->user, 'setujui');

        // Leader divisi lain, walau Leader shift yang sama, tidak berwenang.
        $decide($this->hendri->user, 'setujui')->assertForbidden();
        // Supervisor belum boleh sebelum Leader.
        $decide($this->spv->user, 'setujui')->assertForbidden();

        // Leader sedivisi (Lead Cook) berwenang walau libur.
        $decide($this->fatah->user, 'setujui')->assertSessionHas('notice');
        $decide($this->spv->user, 'setujui')->assertSessionHas('notice');

        $this->assertSame(RequestStatus::Disetujui, $request->fresh()->status);
    }
}
