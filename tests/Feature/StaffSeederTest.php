<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Services\EmployeeCode;
use Database\Seeders\StaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Supervisor', 'Leader/Head', 'Karyawan'] as $role) {
            Role::create(['name' => $role]);
        }

        foreach ([
            'Supervisor', 'Head Barista', 'Lead Cook', 'Lead Service', 'Accounting', 'Security',
            'Senior Barista', 'Junior Barista', 'Service', 'Cook', 'Cook Helper',
        ] as $job) {
            Job::create(['name' => $job]);
        }
    }

    public function test_seeder_mengisi_kode_karyawan_berformat_baru_dan_unik(): void
    {
        $this->seed(StaffSeeder::class);

        $codes = Employee::orderBy('id')->pluck('nik');
        $service = app(EmployeeCode::class);

        $this->assertCount(19, $codes);
        $this->assertCount(19, $codes->unique());
        $this->assertTrue($codes->every(fn (string $code) => $service->isValid($code)));
        $this->assertSame(now()->format('ym').'01B001', $codes->first());
        $this->assertSame(now()->format('ym').'01B019', $codes->last());
    }

    public function test_menjalankan_seeder_dua_kali_tidak_menggandakan_karyawan(): void
    {
        $this->seed(StaffSeeder::class);
        $this->seed(StaffSeeder::class);

        $this->assertSame(19, Employee::count());
    }
}