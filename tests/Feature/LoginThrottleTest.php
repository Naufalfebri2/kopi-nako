<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsScheduleData;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use BuildsScheduleData;
    use RefreshDatabase;

    private const EMAIL = 'budi-bar@example.test';
    private const EMAIL_LAIN = 'lia-leader@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeEmployee(Role::KARYAWAN, 'Barista', 'Budi Bar');
        $this->makeEmployee(Role::LEADER, 'Barista', 'Lia Leader');
    }

    public function test_lima_percobaan_gagal_mengunci_login_akun_itu(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login(self::EMAIL, 'salah')
                ->assertSessionHasErrors(['password' => 'Email atau kata sandi salah.']);
        }

        // Percobaan ke-6 ditolak walau kata sandinya benar
        $this->login(self::EMAIL, 'password')->assertSessionHasErrors('password');

        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('password'));
        $this->assertGuest();
    }

    public function test_akun_lain_tidak_ikut_terkunci(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login(self::EMAIL, 'salah');
        }

        $this->login(self::EMAIL_LAIN, 'password')->assertRedirect(route('beranda'));

        $this->assertAuthenticated();
    }

    public function test_login_berhasil_menghapus_hitungan_gagal(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->login(self::EMAIL, 'salah');
        }

        $this->login(self::EMAIL, 'password')->assertRedirect(route('beranda'));
        $this->post(route('logout'));

        for ($i = 0; $i < 4; $i++) {
            $this->login(self::EMAIL, 'salah');
        }

        $this->login(self::EMAIL, 'password')->assertRedirect(route('beranda'));
        $this->assertAuthenticated();
    }

    public function test_pesan_sama_untuk_email_tidak_dikenal_dan_kata_sandi_salah(): void
    {
        $this->login('tidak-ada@example.test', 'apa-saja')
            ->assertSessionHasErrors(['password' => 'Email atau kata sandi salah.']);

        $this->login(self::EMAIL, 'salah')
            ->assertSessionHasErrors(['password' => 'Email atau kata sandi salah.']);
    }

    public function test_login_gagal_dan_berhasil_dicatat_tanpa_kata_sandi_dan_email(): void
    {
        Log::spy();

        $this->login(self::EMAIL, 'kata-sandi-salah');
        $this->post(route('login'), ['email' => self::EMAIL, 'password' => 'password']);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'Login gagal.'
                && ! str_contains(json_encode($context), 'kata-sandi-salah')
                && ! str_contains(json_encode($context), self::EMAIL))
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => $message === 'Login berhasil.' && isset($context['user_id']))
            ->once();
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->post(route('login'), ['email' => $email, 'password' => $password]);
    }
}
