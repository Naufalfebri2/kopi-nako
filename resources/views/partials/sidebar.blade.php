<aside class="kn-sidebar" id="sidebar">
    <div class="kn-brand">
        <span class="kn-logo-box">
            <img src="{{ asset('images/logo.png') }}" alt="Kopi Nako">
        </span>
        <div>
            <div class="kn-brand-name">Kopi Nako</div>
            <div class="kn-brand-sub">Manajemen Shift Karyawan</div>
        </div>
    </div>

    <ul class="kn-nav">
        <li>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-cells-large"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="#"><i class="fa-regular fa-calendar"></i> Jadwal Shift</a>
        </li>
        <li>
            <a href="{{ route('pengajuan.index') }}" class="{{ request()->routeIs('pengajuan.*') ? 'active' : '' }}">
                <i class="fa-solid fa-right-left"></i> Pengajuan Izin/Tukar
            </a>
        </li>
    </ul>

    <div class="kn-sidebar-foot">Kopi Nako Pamulang<br>© {{ date('Y') }} — Internal System</div>
</aside>
