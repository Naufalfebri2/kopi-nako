<header class="kn-topbar">
    <button class="kn-hamburger" id="hamburgerBtn" aria-label="Buka menu">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="dropdown ms-auto">
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
</header>
