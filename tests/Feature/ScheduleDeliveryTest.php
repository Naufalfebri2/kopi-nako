<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Mail\NotificationMail;
use App\Mail\ScheduleMail;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduleDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Employee $spv;
    private Employee $artie;
    private Employee $inge;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $job = Job::create(['name' => 'Barista']);
        $shift = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $make = fn (Role $role, string $name, string $email, string $nik) => Employee::create([
            'nik' => $nik, 'job_id' => $job->id, 'no_phone' => '081200000000', 'status' => EmployeeStatus::Aktif,
            'id_user' => User::create([
                'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
            ])->id,
        ]);

        $this->spv = $make($spvRole, 'Juandi', 'spv@example.com', '1');
        $this->artie = $make($staffRole, 'Artie', 'artie@example.com', '2');
        $this->inge = $make($staffRole, 'Inge', 'inge@example.com', '3'); // tanpa jadwal

        $this->date = today()->toDateString();

        $this->actingAs($this->spv->user)->post(route('jadwal.store'), [
            'employee_id' => $this->artie->id,
            'shift_id' => $shift->id,
            'date' => $this->date,
        ]);
    }

    public function test_jadwal_dikirim_sebagai_pdf_hanya_ke_karyawan_yang_punya_jadwal(): void
    {
        Mail::fake();

        $this->actingAs($this->spv->user)
            ->post(route('jadwal.kirim'), ['periode' => 'mingguan', 'tanggal' => $this->date])
            ->assertSessionHas('notice', 'Jadwal dikirim ke 1 karyawan.');

        Mail::assertSent(ScheduleMail::class, fn (ScheduleMail $mail) => $mail->hasTo('artie@example.com')
            && str_starts_with($mail->pdf, '%PDF'));
        Mail::assertNotSent(ScheduleMail::class, fn (ScheduleMail $mail) => $mail->hasTo('inge@example.com'));

        // Notifikasi di aplikasi tercatat, tanpa email teks ganda.
                $this->assertTrue(
            $this->artie->notifications()->where('message', 'like', '%sudah terbit%')->exists()
        );
        $this->assertSame(0, $this->inge->notifications()->count());
        Mail::assertNotSent(NotificationMail::class);
    }

    public function test_bulanan_juga_berjalan(): void
    {
        Mail::fake();

        $this->actingAs($this->spv->user)
            ->post(route('jadwal.kirim'), ['periode' => 'bulanan', 'tanggal' => $this->date])
            ->assertSessionHas('notice');

        Mail::assertSent(ScheduleMail::class, 1);
    }

    public function test_selain_supervisor_mendapat_403(): void
    {
        Mail::fake();

        $this->actingAs($this->artie->user)
            ->post(route('jadwal.kirim'), ['periode' => 'mingguan', 'tanggal' => $this->date])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_periode_tanpa_jadwal_tidak_mengirim_apa_apa(): void
    {
        Mail::fake();

        $this->actingAs($this->spv->user)
            ->post(route('jadwal.kirim'), ['periode' => 'mingguan', 'tanggal' => today()->addYear()->toDateString()])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }
}
