@php
    $bellEmployee = auth()->user()->employee;
    $bellItems = $bellEmployee ? $bellEmployee->notifications()->latest('id')->limit(10)->get() : collect();
    $bellUnread = $bellEmployee ? $bellEmployee->notifications()->where('status', 'belum_dibaca')->count() : 0;
@endphp

<header class="kn-topbar">
    <button class="kn-hamburger" id="hamburgerBtn" aria-label="Buka menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="d-flex align-items-center gap-2 ms-auto">
        @if ($bellEmployee)
            <div class="dropdown">
                <button class="kn-icon-btn position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                    aria-expanded="false" aria-label="Notifikasi" style="width: 38px; height: 38px;">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    @if ($bellUnread > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                            style="background: var(--accent-deep, #b4763a); font-size: 10px;">
                            {{ $bellUnread > 99 ? '99+' : $bellUnread }}
                        </span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px; max-width: 90vw;">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <span class="fw-semibold small">Notifikasi</span>
                        @if ($bellUnread > 0)
                            <form method="POST" action="{{ route('notifikasi.baca') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">
                                    Tandai semua dibaca
                                </button>
                            </form>
                        @endif
                    </div>
                    <div style="max-height: 360px; overflow-y: auto;">
                        @forelse ($bellItems as $item)
                            <div
                                class="px-3 py-2 border-bottom small {{ $item->status === 'belum_dibaca' ? 'fw-semibold' : 'text-muted' }}">
                                {{ $item->message }}
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-muted small">Belum ada notifikasi.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <div class="dropdown">
            <button class="kn-profile" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="kn-avatar">{{ auth()->user()->initials }}</span>
                <span class="kn-profile-name d-none d-sm-block">
                    {{ auth()->user()->name }}
                    <span class="kn-profile-role">{{ auth()->user()->role?->name }}</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item disabled" href="#">Pengaturan Akun</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <button type="button" class="dropdown-item fw-semibold" style="color: var(--accent-deep);"
                        data-bs-toggle="modal" data-bs-target="#logoutModal">
                        Keluar
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>
