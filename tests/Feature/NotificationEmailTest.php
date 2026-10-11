<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Mail\NotificationMail;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(Role $role, Job $job, string $name, string $email, string $nik): Employee
    {
        $user = User::create([
            'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
        ]);

        return Employee::create([
            'nik' => $nik, 'id_user' => $user->id, 'job_id' => $job->id,
            'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
        ]);
    }

    public function test_penugasan_shift_mengirim_email_ke_karyawan_yang_dijadwalkan(): void
    {
        Mail::fake();

        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $job = Job::create(['name' => 'Barista']);
        $shift = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $spv = $this->makeEmployee($spvRole, $job, 'Juandi', 'spv@example.com', '1');
        $staff = $this->makeEmployee($staffRole, $job, 'Artie', 'artie@example.com', '2');

        $this->actingAs($spv->user)->post(route('jadwal.store'), [
            'employee_id' => $staff->id,
            'shift_id'    => $shift->id,
            'date'        => today()->addDay()->toDateString(),
        ]);

        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) {
            return $mail->hasTo('artie@example.com')
                && str_contains($mail->body, 'Anda dijadwalkan shift Pagi');
        });
        Mail::assertNotSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo('spv@example.com'));
    }

    public function test_pengiriman_email_gagal_tidak_menggagalkan_proses_utama(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $job = Job::create(['name' => 'Barista']);
        $shift = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $spv = $this->makeEmployee($spvRole, $job, 'Juandi', 'spv@example.com', '1');
        $staff = $this->makeEmployee($staffRole, $job, 'Artie', 'artie@example.com', '2');

        $this->actingAs($spv->user)->post(route('jadwal.store'), [
            'employee_id' => $staff->id,
            'shift_id'    => $shift->id,
            'date'        => today()->addDay()->toDateString(),
        ])->assertSessionHas('notice');

        $this->assertDatabaseHas('shift_schedule_detail', ['id_employee' => $staff->id]);
    }
}
