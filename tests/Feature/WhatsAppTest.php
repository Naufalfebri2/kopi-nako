<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\MasterShift;
use App\Models\Role;
use App\Models\User;
use App\Services\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function setupScheduling(): array
    {
        $spvRole = Role::create(['name' => Role::SUPERVISOR]);
        $staffRole = Role::create(['name' => Role::KARYAWAN]);
        $job = Job::create(['name' => 'Barista']);
        $shift = MasterShift::create(['name' => 'Pagi', 'start_date' => '09:00:00', 'end_date' => '15:00:00']);

        $make = fn (Role $role, string $name, string $email, string $nik, string $phone) => Employee::create([
            'nik' => $nik, 'job_id' => $job->id, 'no_phone' => $phone, 'status' => EmployeeStatus::Aktif,
            'id_user' => User::create([
                'name' => $name, 'email' => $email, 'password' => 'password123', 'id_role' => $role->id,
            ])->id,
        ]);

        return [
            $make($spvRole, 'Juandi', 'spv@example.com', '1', '081200000001'),
            $make($staffRole, 'Artie', 'artie@example.com', '2', '081234567890'),
            $shift,
        ];
    }

    public function test_normalisasi_nomor(): void
    {
        $gateway = new WhatsAppGateway();

        $this->assertSame('6281234567890', $gateway->normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', $gateway->normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', $gateway->normalize('6281234567890'));
        $this->assertNull($gateway->normalize('12345'));
        $this->assertNull($gateway->normalize(null));
    }

    public function test_penugasan_shift_mengirim_whatsapp_ke_karyawan(): void
    {
        config(['whatsapp.driver' => 'fonnte', 'whatsapp.token' => 'rahasia']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);

        [$spv, $staff, $shift] = $this->setupScheduling();

        $this->actingAs($spv->user)->post(route('jadwal.store'), [
            'employee_id' => $staff->id,
            'shift_id'    => $shift->id,
            'date'        => today()->addDay()->toDateString(),
        ])->assertSessionHas('notice');

        Http::assertSent(fn ($request) => $request['target'] === '6281234567890'
            && str_contains($request['message'], 'Anda dijadwalkan shift Pagi'));
    }

    public function test_whatsapp_gagal_tidak_menggagalkan_proses_utama(): void
    {
        config(['whatsapp.driver' => 'fonnte', 'whatsapp.token' => 'rahasia']);
        Http::fake(['api.fonnte.com/*' => Http::response([], 500)]);

        [$spv, $staff, $shift] = $this->setupScheduling();

        $this->actingAs($spv->user)->post(route('jadwal.store'), [
            'employee_id' => $staff->id,
            'shift_id'    => $shift->id,
            'date'        => today()->addDay()->toDateString(),
        ])->assertSessionHas('notice');

        $this->assertDatabaseHas('shift_schedule_detail', ['id_employee' => $staff->id]);
    }
}