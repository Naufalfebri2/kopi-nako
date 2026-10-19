@php
    $user = auth()->user();
    $isSupervisor = $user->hasRole(\App\Models\Role::SUPERVISOR);
@endphp

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
        @if ($isSupervisor)
            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-table-cells-large"></i> Dashboard
                </a>
            </li>
        @endif
        <li>
            <a href="{{ route('jadwal.index') }}" class="{{ request()->routeIs('jadwal.*') ? 'active' : '' }}">
                <i class="fa-regular fa-calendar"></i> Jadwal Shift
            </a>
        </li>
        <li>
            <a href="{{ route('pengajuan.index') }}" class="{{ request()->routeIs('pengajuan.*') ? 'active' : '' }}">
                <i class="fa-solid fa-right-left"></i> Pengajuan Izin/Tukar
            </a>
        </li>
        @if ($isSupervisor && $user->canViewReports())
            <li>
                <a href="{{ route('laporan.index') }}" class="{{ request()->routeIs('laporan.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-chart-column"></i> Laporan
                </a>
            </li>
        @endif

        @if ($isSupervisor)
            @if (Route::has('master.employee.index'))
                <li>
                    <a href="{{ route('master.employee.index') }}"
                        class="{{ request()->routeIs('master.employee.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-users"></i> Master Employee
                    </a>
                </li>
            @endif
            @if (Route::has('master.shift.index'))
                <li>
                    <a href="{{ route('master.shift.index') }}"
                        class="{{ request()->routeIs('master.shift.*') ? 'active' : '' }}">
                        <i class="fa-regular fa-clock"></i> Master Shift
                    </a>
                </li>
            @endif
            @if (Route::has('master.leave.index'))
                <li>
                    <a href="{{ route('master.leave.index') }}"
                        class="{{ request()->routeIs('master.leave.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-person-walking-luggage"></i> Master Leave Type
                    </a>
                </li>
            @endif
        @endif
    </ul>

    <div class="kn-sidebar-foot">Kopi Nako Pamulang<br>© {{ date('Y') }} — Internal System</div>
</aside>
