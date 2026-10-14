<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Job;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '3674010101010001';

    private Job $job;
    private Role $supervisorRole;
    private Role $leaderRole;
    private Role $karyawanRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisorRole = Role::create(['name' => Role::SUPERVISOR]);
        $this->leaderRole     = Role::create(['name' => Role::LEADER]);
        $this->karyawanRole   = Role::create(['name' => Role::KARYAWAN]);
        // Jabatan harus cocok dengan role Karyawan (aturan pasangan role dan jabatan).
        $this->job            = Job::create(['name' => 'Service']);
    }

    private function makeUser(Role $role, string $email): User
    {
        return User::create([
            'name'     => 'User ' . $email,
            'email'    => $email,
            'password' => 'password123',
            'id_role'  => $role->id,
        ]);
    }

    private function makeEmployee(Role $role, string $email, string $nik): Employee
    {
        $user = $this->makeUser($role, $email);

        return Employee::create([
            'nik'      => $nik,
            'id_user'  => $user->id,
            'job_id'   => $this->job->id,
            'no_phone' => '081200000000',
            'status'   => EmployeeStatus::Aktif,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'                  => 'Budi Santoso',
            'email'                 => 'budi@example.com',
            'no_phone'              => '0812-3456-7890',
            'nik'                   => self::NIK,
            'job_id'                => $this->job->id,
            'id_role'               => $this->karyawanRole->id,
            'password'              => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $override);
    }

    public function test_supervisor_dapat_menambah_karyawan(): void
    {
        $supervisor = $this->makeUser($this->supervisorRole, 'spv@example.com');

        $this->actingAs($supervisor)
            ->post(route('karyawan.store'), $this->payload())
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('notice', 'Karyawan Budi Santoso berhasil ditambahkan.');

        $user     = User::where('email', 'budi@example.com')->firstOrFail();
        $employee = Employee::where('nik', self::NIK)->firstOrFail();

        $this->assertSame(2, User::count());
        $this->assertSame(1, Employee::count());
        $this->assertSame($user->id, $employee->id_user);
        $this->assertSame(EmployeeStatus::Aktif, $employee->status);
        $this->assertSame('081234567890', $employee->no_phone);
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertTrue(Auth::validate(['email' => 'budi@example.com', 'password' => 'rahasia123']));
    }

    public function test_leader_dan_karyawan_mendapat_403(): void
    {
        $leader = $this->makeUser($this->leaderRole, 'leader@example.com');
        $staff  = $this->makeUser($this->karyawanRole, 'staff@example.com');

        $this->actingAs($leader)->post(route('karyawan.store'), $this->payload())->assertForbidden();
        $this->actingAs($staff)->post(route('karyawan.store'), $this->payload())->assertForbidden();

        $this->assertSame(0, Employee::count());
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->post(route('karyawan.store'), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Employee::count());
    }

    public function test_email_dan_nik_duplikat_ditolak(): void
    {
        $supervisor = $this->makeUser($this->supervisorRole, 'spv@example.com');
        $this->actingAs($supervisor)->post(route('karyawan.store'), $this->payload());

        $this->actingAs($supervisor)
            ->post(route('karyawan.store'), $this->payload(['nik' => '999']))
            ->assertSessionHasErrors(['email' => 'Email sudah digunakan.']);

        $this->actingAs($supervisor)
            ->post(route('karyawan.store'), $this->payload(['email' => 'lain@example.com']))
            ->assertSessionHasErrors(['nik' => 'NIK sudah terdaftar.']);

        $this->assertSame(1, Employee::count());
    }

    public function test_validasi_field_lain(): void
    {
        $supervisor = $this->makeUser($this->supervisorRole, 'spv@example.com');
        $this->actingAs($supervisor);

        $this->post(route('karyawan.store'), $this->payload(['password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors(['password' => 'Kata sandi minimal 8 karakter.']);

        $this->post(route('karyawan.store'), $this->payload(['password_confirmation' => 'beda12345']))
            ->assertSessionHasErrors(['password' => 'Konfirmasi kata sandi tidak cocok.']);

        $this->post(route('karyawan.store'), $this->payload(['no_phone' => '12345']))
            ->assertSessionHasErrors(['no_phone' => 'Nomor telepon tidak valid. Contoh: 081234567890.']);

        $this->post(route('karyawan.store'), $this->payload(['job_id' => 9999]))
            ->assertSessionHasErrors(['job_id' => 'Jabatan tidak ditemukan.']);

        $this->post(route('karyawan.store'), $this->payload(['id_role' => 9999]))
            ->assertSessionHasErrors(['id_role' => 'Role akses tidak ditemukan.']);

        $this->assertSame(0, Employee::count());
        $this->assertSame(1, User::count());
    }

    public function test_kegagalan_membuat_employee_tidak_meninggalkan_user_yatim(): void
    {
        $supervisor = $this->makeUser($this->supervisorRole, 'spv@example.com');

        Employee::creating(function () {
            throw new \RuntimeException('simulasi gagal');
        });

        $this->actingAs($supervisor)
            ->post(route('karyawan.store'), $this->payload())
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.com']);
        $this->assertSame(1, User::count());
        $this->assertSame(0, Employee::count());
    }

    public function test_notifikasi_untuk_supervisor_lain_tanpa_nik(): void
    {
        $creator       = $this->makeUser($this->supervisorRole, 'spv1@example.com');
        $otherEmployee = $this->makeEmployee($this->supervisorRole, 'spv2@example.com', '111');
        $leaderEmp     = $this->makeEmployee($this->leaderRole, 'leader@example.com', '222');

        $this->actingAs($creator)->post(route('karyawan.store'), $this->payload());

        $notifs = $otherEmployee->notifications()->get();
        $this->assertCount(1, $notifs);

        $n = $notifs->first();
        $this->assertSame('belum_dibaca', $n->status);
        $this->assertNull($n->schedule_id);
        $this->assertNull($n->request_id);
        $this->assertSame("Karyawan baru, Budi Santoso, ditambahkan ke tim oleh {$creator->name}", $n->message);
        $this->assertStringNotContainsString(self::NIK, $n->message);

        // Leader tidak menerima notifikasi.
        $this->assertCount(0, $leaderEmp->notifications()->get());
    }

    public function test_karyawan_baru_tampil_di_jadwal_dan_dihitung_di_dashboard(): void
    {
        $supervisor = $this->makeUser($this->supervisorRole, 'spv@example.com');
        $this->actingAs($supervisor)->post(route('karyawan.store'), $this->payload());

        $this->get(route('jadwal.index'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Libur');

        $response = $this->get(route('dashboard'))->assertOk();

        $this->assertSame(1, $response->viewData('stats')['aktif']);
        $response->assertDontSee(self::NIK);
    }
}