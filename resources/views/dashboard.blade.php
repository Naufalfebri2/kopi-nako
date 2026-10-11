@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $isSupervisor = auth()->user()->hasRole(\App\Models\Role::SUPERVISOR);
@endphp

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    @if ($isSupervisor)
        <link href="{{ asset('css/karyawan.css') }}" rel="stylesheet">
    @endif
@endpush

@if ($isSupervisor)
    @push('scripts')
        <script src="{{ asset('js/karyawan.js') }}"></script>
    @endpush
@endif

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="kn-page-title">Ringkasan Shift Minggu Ini</h1>
            <div class="kn-page-sub">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</div>
        </div>

        @if ($isSupervisor)
            <button type="button" class="btn btn-kn" data-bs-toggle="modal" data-bs-target="#knTambahKaryawan">
                <i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i> Tambah Karyawan
            </button>
        @endif
    </div>

    @if (session('notice'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-solid fa-users" :value="$stats['aktif']" label="Karyawan Aktif" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-solid fa-hourglass-half" :value="$stats['menunggu']" label="Pengajuan Menunggu" tone="warn" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-solid fa-check" :value="$stats['disetujui_bulan_ini']" label="Disetujui Bulan Ini" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-regular fa-circle" :value="$stats['nonaktif']" label="Karyawan Tidak Aktif" tone="muted" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8 d-flex flex-column gap-3">
            <div class="kn-panel">
                <h3 class="kn-panel-title">Aktivitas Pengajuan Shift</h3>
                <div class="kn-chart">
                    @foreach ($chart as $day)
                        <div class="kn-chart-col">
                            <div class="kn-chart-track">
                                <div class="kn-chart-bar" style="height: {{ $day['percent'] }}%"
                                    title="{{ $day['label'] }}: {{ $day['value'] }} aktivitas"></div>
                            </div>
                            <div class="kn-chart-label">{{ $day['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="kn-panel">
                <h3 class="kn-panel-title">Pengajuan Izin/Tukar Shift</h3>
                <div class="table-responsive">
                    <table class="table kn-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Jenis</th>
                                <th>Shift Tujuan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $req)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="kn-avatar kn-avatar-sm">{{ $req->employee->user->initials }}</span>
                                            <span class="fw-semibold">{{ $req->employee->user->name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $req->type_label }}</td>
                                    <td>{{ $req->target_shift_name }}</td>
                                    <td><x-status-dot :status="$req->status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="kn-empty">Belum ada pengajuan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-3">
            <div class="kn-panel">
                <h3 class="kn-panel-title">Notifikasi</h3>
                @forelse ($notifications as $notification)
                    <div class="kn-feed-item">
                        <span class="kn-feed-icon"><i class="fa-regular fa-bell"></i></span>
                        <div class="kn-feed-text">{{ $notification->message }}</div>
                    </div>
                @empty
                    <div class="kn-empty">Belum ada notifikasi.</div>
                @endforelse
            </div>

            <div class="kn-panel">
                <h3 class="kn-panel-title">Shift Berjalan Saat Ini</h3>
                @forelse ($running->take(6) as $detail)
                    <div class="kn-shift-item">
                        <span class="kn-avatar kn-avatar-sm">{{ $detail->employee->user->initials }}</span>
                        <div>
                            <div class="fw-semibold small">{{ $detail->employee->user->name }}</div>
                            <div class="kn-muted small">{{ $detail->employee->job?->name }}</div>
                        </div>
                        <span class="kn-badge-time">
                            {{ $detail->schedule->masterShift->name }} · {{ $detail->schedule->masterShift->time_range }}
                        </span>
                    </div>
                @empty
                    <div class="kn-empty">Tidak ada shift yang sedang berjalan.</div>
                @endforelse
                @if ($running->count() > 6)
                    <div class="kn-muted small mt-2">dan {{ $running->count() - 6 }} lainnya</div>
                @endif
            </div>

            <div class="kn-panel">
                <h3 class="kn-panel-title">Jadwal Shift Hari Ini</h3>
                @forelse ($upcoming->take(6) as $detail)
                    <div class="kn-shift-item">
                        <span class="kn-avatar kn-avatar-sm">{{ $detail->employee->user->initials }}</span>
                        <div>
                            <div class="fw-semibold small">{{ $detail->employee->user->name }}</div>
                            <div class="kn-muted small">{{ $detail->employee->job?->name }}</div>
                        </div>
                        <span class="kn-badge-time kn-badge-soft">
                            {{ $detail->schedule->masterShift->name }} · {{ $detail->schedule->masterShift->time_range }}
                        </span>
                    </div>
                @empty
                    <div class="kn-empty">Tidak ada jadwal berikutnya hari ini.</div>
                @endforelse
                @if ($upcoming->count() > 6)
                    <div class="kn-muted small mt-2">dan {{ $upcoming->count() - 6 }} lainnya</div>
                @endif
            </div>
        </div>
    </div>

    @if ($isSupervisor)
        @include('karyawan._tambah-modal')
    @endif
@endsection
