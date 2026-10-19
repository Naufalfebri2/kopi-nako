<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Notification;
use App\Models\Role;
use App\Models\ShiftRequest;
use App\Models\ShiftScheduleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Job $job;
    private Role $supervisorRole;
    private Role $leaderRole;
    private Role $karyawanRole;
    private MasterShift $pagi;
    private MasterShift $siang;
    private string $date;
    private Employee $spv;
    private Employee $artie;
    private Employee $inge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisorRole = Role::create(['name' => Role::SUPERVISOR]);
        $this->leaderRole     = Role::create(['name' => Role::LEADER]);
        $this->karyawanRole   = Role::create(['name' => Role::KARYAWAN]);
        // Jabatan masuk divisi Barista, supaya tukar shift antar pelaku di test ini sesama divisi.
        $this->job            = Job::create(['name' => 'Junior Barista']);
        $this->pagi  = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);
        $this->siang = MasterShift::create(['name' => 'Siang', 'start_date' => '15:00:00', 'end_date' => '21:00:00']);
        $this->date  = today()->addDay()->toDateString();

        $this->spv   = $this->makeEmployee($this->supervisorRole, 'Juandi', 'spv@example.com', '1');
        $this->artie = $this->makeEmployee($this->karyawanRole, 'Artie', 'artie@example.com', '2');
        $this->inge  = $this->makeEmployee($this->karyawanRole, 'Inge', 'inge@example.com', '3');

        $this->schedule($this->artie, $this->pagi);
        $this->schedule($this->inge, $this->siang);
    }

    private function makeEmployee(Role $role, string $name, string $email, string $nik): Employee
    {
        $user = User::create([
            'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
        ]);

        return Employee::create([
            'nik' => $nik, 'id_user' => $user->id, 'job_id' => $this->job->id,
            'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
        ]);
    }

    private function schedule(Employee $employee, MasterShift $shift, bool $lead = false): void
    {
        $this->actingAs($this->spv->user)->post(route('jadwal.store'), array_filter([
            'employee_id' => $employee->id,
            'shift_id'    => $shift->id,
            'date'        => $this->date,
            'status_lead' => $lead ? 1 : null,
        ]));
    }

    private function detail(Employee $employee): ShiftScheduleDetail
    {
        return ShiftScheduleDetail::where('id_employee', $employee->id)->firstOrFail();
    }

    private function submitSwap(): ShiftRequest
    {
        $this->actingAs($this->artie->user)->post(route('pengajuan.store'), [
            'jenis'             => 'tukar',
            'detail_id'         => $this->detail($this->artie)->id,
            'partner_detail_id' => $this->detail($this->inge)->id,
            'reason'            => 'Ada keperluan keluarga di pagi hari.',
        ])->assertSessionHas('notice');

        return ShiftRequest::latest('id')->firstOrFail();
    }

    private function decide(User $user, ShiftRequest $request, string $keputusan)
    {
        return $this->actingAs($user)->post(route('pengajuan.decide', $request), ['keputusan' => $keputusan]);
    }

    // Ringkasan notifikasi untuk pesan kegagalan: siapa menerima apa.
    private function notificationDump(ShiftRequest $request): string
    {
        return Notification::where('request_id', $request->id)
            ->get(['id_employee', 'message'])
            ->map(fn ($n) => "employee {$n->id_employee}: {$n->message}")
            ->implode(' | ');
    }

    public function test_rekan_menolak_pengajuan_ditolak_dan_jadwal_tidak_tertukar(): void
    {
        $artieSchedule = $this->detail($this->artie)->id_schedule;
        $request = $this->submitSwap();

        $this->decide($this->inge->user, $request, 'tolak')->assertSessionHas('notice');

        $this->assertSame(RequestStatus::Ditolak, $request->fresh()->status);
        $this->assertSame($artieSchedule, $this->detail($this->artie)->id_schedule);

        $steps = $this->actingAs($this->spv->user)
            ->get(route('pengajuan.show', $request))->viewData('steps');
        $this->assertSame('rejected', $steps[1]['state']);
        $this->assertSame('Tidak dilanjutkan', end($steps)['meta']);

        $this->assertTrue(
            Notification::where('request_id', $request->id)
                ->where('id_employee', $this->artie->id)
                ->where('message', 'like', '%ditolak oleh Inge%')
                ->exists()
        );
    }

    public function test_rekan_setuju_lalu_supervisor_setuju_menukar_jadwal(): void
    {
        $artieSchedule = $this->detail($this->artie)->id_schedule;
        $ingeSchedule  = $this->detail($this->inge)->id_schedule;
        $request = $this->submitSwap();

        $this->decide($this->inge->user, $request, 'setujui');
        $this->assertSame(RequestStatus::Menunggu, $request->fresh()->status);
        $this->assertSame($artieSchedule, $this->detail($this->artie)->id_schedule);

        $this->decide($this->spv->user, $request, 'setujui');

        $this->assertSame(RequestStatus::Disetujui, $request->fresh()->status);
        $this->assertSame($ingeSchedule, $this->detail($this->artie)->id_schedule);
        $this->assertSame($artieSchedule, $this->detail($this->inge)->id_schedule);
    }

    public function test_urutan_tidak_bisa_dilompati(): void
    {
        $request = $this->submitSwap();

        // Supervisor dan pengaju tidak boleh memutuskan sebelum rekan.
        $this->decide($this->spv->user, $request, 'setujui')->assertForbidden();
        $this->decide($this->artie->user, $request, 'setujui')->assertForbidden();

        // Karyawan lain tidak bisa memutuskan atas nama Inge.
        $other = $this->makeEmployee($this->karyawanRole, 'Lain', 'lain@example.com', '9');
        $this->decide($other->user, $request, 'setujui')->assertForbidden();

        $this->assertSame(RequestStatus::Menunggu, $request->fresh()->status);
        $this->assertSame(0, $request->logs()->count());
    }

    public function test_rekan_melihat_pengajuan_dan_orang_lain_tidak(): void
    {
        $request = $this->submitSwap();
        $other = $this->makeEmployee($this->karyawanRole, 'Lain', 'lain@example.com', '9');

        $this->actingAs($this->inge->user)->get(route('pengajuan.show', $request))->assertOk();
        $this->actingAs($other->user)->get(route('pengajuan.show', $request))->assertForbidden();
    }

    public function test_leader_dan_supervisor_baru_diberi_tahu_setelah_rekan_setuju(): void
    {
        $leader = $this->makeEmployee($this->leaderRole, 'Alfi', 'alfi@example.com', '4');
        $this->schedule($leader, $this->pagi, true); // Leader pada shift Pagi milik Artie

        // Pastikan setup benar: Leader tersimpan sebagai Leader, di jadwal yang sama dengan Artie.
        $leaderDetail = $this->detail($leader);
        $this->assertTrue((bool) $leaderDetail->status_lead, 'Leader tidak tersimpan dengan status_lead = true.');
        $this->assertSame(
            $this->detail($this->artie)->id_schedule,
            $leaderDetail->id_schedule,
            'Leader dan Artie tidak berada di jadwal (shift) yang sama.'
        );

        $request = $this->submitSwap();

        // Saat diajukan hanya rekan yang diberi tahu.
        $notified = Notification::where('request_id', $request->id)->pluck('id_employee')->all();
        $this->assertSame([$this->inge->id], $notified);

        // Leader belum bisa memverifikasi sebelum rekan setuju.
        $this->decide($leader->user, $request, 'setujui')->assertForbidden();

        $this->decide($this->inge->user, $request, 'setujui');

        $dump = $this->notificationDump($request);
        $this->assertTrue(
            Notification::where('request_id', $request->id)->where('id_employee', $leader->id)->exists(),
            "Leader (employee {$leader->id}) tidak diberi tahu. Notifikasi yang ada: {$dump}"
        );
        $this->assertFalse(
            Notification::where('request_id', $request->id)->where('id_employee', $this->spv->id)->exists(),
            "Supervisor diberi tahu terlalu dini. Notifikasi yang ada: {$dump}"
        );

        // Supervisor baru bisa setelah Leader memverifikasi.
        $this->decide($this->spv->user, $request, 'setujui')->assertForbidden();
        $this->decide($leader->user, $request, 'setujui');
        $this->assertTrue(
            Notification::where('request_id', $request->id)->where('id_employee', $this->spv->id)->exists(),
            'Supervisor tidak diberi tahu setelah Leader memverifikasi. Notifikasi yang ada: '.$this->notificationDump($request)
        );

        $this->decide($this->spv->user, $request, 'setujui');
        $this->assertSame(RequestStatus::Disetujui, $request->fresh()->status);
    }
}
