@extends('layouts.app')

@section('title', 'Pengajuan Izin/Tukar')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pengajuan.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php
        $fmt = fn($date) => $date ? $date->copy()->locale('id')->translatedFormat('j M Y') : '—';
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <h1 class="kn-page-title">Pengajuan Izin / Tukar Shift</h1>
            <div class="kn-page-sub">Riwayat lengkap dan status persetujuan pengajuan karyawan</div>
        </div>
        @if ($canSubmit)
            <button type="button" class="btn-kn" id="openSubmit">+ Ajukan Baru</button>
        @endif
    </div>

    @if (session('notice'))
        <div class="alert kn-alert alert-dismissible fade show" role="alert">
            {{ session('notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-regular fa-file-lines" :value="$stats['total']" label="Total Diajukan" tone="muted" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-solid fa-check" :value="$stats['disetujui']" label="Disetujui" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-solid fa-xmark" :value="$stats['ditolak']" label="Ditolak" />
        </div>
        <div class="col-6 col-lg-3">
            <x-stat-card icon="fa-solid fa-hourglass-half" :value="$stats['menunggu']" label="Menunggu" tone="warn" />
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <form method="GET" action="{{ route('pengajuan.index') }}" class="d-flex flex-wrap gap-2">
            <select name="status" class="form-select kn-filter" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach (\App\Enums\RequestStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <select name="jenis" class="form-select kn-filter" onchange="this.form.submit()">
                <option value="">Semua Jenis</option>
                <option value="izin" @selected(request('jenis') === 'izin')>Izin</option>
                <option value="tukar" @selected(request('jenis') === 'tukar')>Tukar Shift</option>
            </select>
            <select name="bulan" class="form-select kn-filter" onchange="this.form.submit()">
                <option value="">Semua Bulan</option>
                <option value="ini" @selected(request('bulan') === 'ini')>Bulan Ini</option>
                <option value="lalu" @selected(request('bulan') === 'lalu')>Bulan Lalu</option>
            </select>
        </form>

        @if ($isSupervisor)
            <form method="POST" action="{{ route('pengajuan.approve-all') }}" class="ms-auto"
                onsubmit="return confirm('Setujui {{ $readyCount }} pengajuan yang menunggu keputusan Supervisor?')">
                @csrf
                <button type="submit" class="btn-kn" @disabled($readyCount === 0)>
                    Setujui Semua yang Menunggu
                </button>
            </form>
        @endif
    </div>

    <div class="kn-panel p-0">
        <div class="table-responsive">
            <table class="table kn-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Karyawan</th>
                        <th>Jenis</th>
                        <th>Tanggal Diajukan</th>
                        <th>Tanggal Shift</th>
                        <th>Shift Asal</th>
                        <th>Shift Tujuan</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        <tr class="kn-row" data-detail-url="{{ route('pengajuan.show', $req) }}">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="kn-avatar kn-avatar-sm">{{ $req->employee->user->initials }}</span>
                                    <div>
                                        <div class="fw-semibold">{{ $req->employee->user->name }}</div>
                                        <div class="kn-muted small">{{ $req->employee->user->role?->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $req->type_label }}</td>
                            <td>{{ $fmt($req->created_at) }}</td>
                            <td>{{ $fmt($req->shift_date) }}</td>
                            <td>{{ $req->source_shift_name }}</td>
                            <td>{{ $req->target_shift_name }}</td>
                            <td>
                                <x-status-dot :status="$req->status" />
                                @if ($actionableIds->contains($req->id))
                                    <span class="kn-badge-action">Perlu tindakan Anda</span>
                                @endif
                            </td>
                            <td class="kn-chevron pe-4"><i class="fa-solid fa-chevron-right"></i></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kn-empty">Belum ada pengajuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $requests->links() }}</div>

    <div class="offcanvas offcanvas-end kn-drawer" tabindex="-1" id="detailDrawer" aria-labelledby="detailTitle">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="detailTitle">Detail Pengajuan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body" id="detailBody"></div>
    </div>

    @if ($canSubmit)
        @include('pengajuan._ajukan')
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pengajuan.js') }}"></script>
@endpush
