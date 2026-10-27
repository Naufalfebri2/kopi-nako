<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NoRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tidak_ada_halaman_registrasi(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_halaman_login_tidak_mengajak_mendaftar(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk')
            ->assertDontSee('Belum punya akun')
            ->assertDontSee('Registrasi');
    }
}
