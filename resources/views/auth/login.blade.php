@extends('layouts.guest')

@section('title', 'Masuk')

@push('styles')
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-visual" role="img" aria-label="Suasana kedai kopi Kopi Nako">
                <div class="auth-visual-brand">
                    <span class="kn-logo-box sm">
                        <img src="{{ asset('images/logo.png') }}" alt="Kopi Nako">
                    </span>
                    <span>Kopi Nako</span>
                </div>
                <div>
                    <h1>Selamat Datang di Kopi Nako</h1>
                    <p>Kelola jadwal shift dan tim Anda dengan lebih mudah, dalam satu tempat</p>
                </div>
            </div>

            <div class="auth-form-panel">
                <form class="auth-form" method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <div class="kn-logo-box lg auth-logo">
                        <img src="{{ asset('images/logo.png') }}" alt="Kopi Nako">
                    </div>
                    <h2>Selamat Datang Kembali</h2>
                    <p class="auth-sub">Masuk ke akun Anda untuk melanjutkan mengelola tim</p>

                    <div class="auth-field">
                        <label for="email">Alamat Email</label>
                        <div class="auth-input @error('email') has-error @enderror">
                            <i class="fa-regular fa-envelope icon"></i>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                placeholder="contoh: juandi@kopinako.com" autocomplete="username" autofocus>
                        </div>
                        @error('email')
                            <div class="auth-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="password">Kata Sandi</label>
                        <div class="auth-input @error('password') has-error @enderror">
                            <i class="fa-solid fa-lock icon"></i>
                            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi"
                                autocomplete="current-password">
                            <button type="button" class="auth-toggle" id="togglePass">Lihat</button>
                        </div>
                        @error('password')
                            <div class="auth-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <a href="#" class="auth-forgot"
                        onclick="alert('Silakan hubungi Supervisor Anda untuk reset kata sandi.'); return false;">
                        Lupa Kata Sandi?
                    </a>

                    <button type="submit" class="btn-kn w-100">Masuk</button>

                    <p class="auth-foot">Belum punya akun? <strong>Hubungi Supervisor</strong></p>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const pass = document.getElementById('password');
        const toggle = document.getElementById('togglePass');
        toggle.addEventListener('click', () => {
            const hidden = pass.type === 'password';
            pass.type = hidden ? 'text' : 'password';
            toggle.textContent = hidden ? 'Sembunyi' : 'Lihat';
        });
    </script>
@endpush
