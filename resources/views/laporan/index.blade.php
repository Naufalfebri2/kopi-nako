@extends('layouts.app')

@section('title', 'Laporan')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/laporan.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <h1 class="kn-page-title">Laporan</h1>
            <div class="kn-page-sub">
                Rekap jadwal shift dan pengajuan. Ini rekap jadwal, bukan catatan kehadiran.
            </div>
        </div>
        <a href="{{ $pdfUrl }}" class="btn-kn kn-btn-sm">
            <i class="fa-solid fa-file-pdf me-1"></i> Ekspor PDF
        </a>
    </div>

    @if ($periodError)
        <div class="alert alert-danger py-2 small">{{ $periodError }} Periode dikembalikan ke bulan ini.</div>
    @endif

    <form method="GET" action="{{ route('laporan.index') }}" class="d-flex flex-wrap align-items-end gap-2 mb-2">
        <div>
            <label class="form-label small fw-semibold mb-1" for="dari">Dari</label>
            <input type="date" class="form-control kn-filter" id="dari" name="dari"
                value="{{ $from->toDateString() }}">
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1" for="sampai">Sampai</label>
            <input type="date" class="form-control kn-filter" id="sampai" name="sampai"
                value="{{ $to->toDateString() }}">
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1" for="jabatan">Jabatan</label>
            <select class="form-select kn-filter" id="jabatan" name="jabatan">
                <option value="">Semua Jabatan</option>
                @foreach ($jobs as $job)
                    <option value="{{ $job->id }}" @selected($jobId == $job->id)>{{ $job->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-kn kn-btn-sm">Terapkan</button>
        <a href="{{ $weekUrl }}" class="btn-kn-outline kn-btn-sm">Minggu Ini</a>
        <a href="{{ $monthUrl }}" class="btn-kn-outline kn-btn-sm">Bulan Ini</a>
    </form>

    <div class="kn-muted small mb-4">
        Periode: {{ $periodLabel }}@if ($report['jobName'])
            · Jabatan: {{ $report['jobName'] }}
        @endif
    </div>

    <div class="row row-cols-2 row-cols-lg-5 g-3 mb-4">
        <div class="col">
            <x-stat-card icon="fa-regular fa-calendar-check" :value="$report['summary']['penugasan']" label="Penugasan Shift" />
        </div>
        <div class="col">
            <x-stat-card icon="fa-regular fa-file-lines" :value="$report['summary']['pengajuan']" label="Total Pengajuan" tone="muted" />
        </div>
        <div class="col">
            <x-stat-card icon="fa-solid fa-check" :value="$report['summary']['disetujui']" label="Disetujui" />
        </div>
        <div class="col">
            <x-stat-card icon="fa-solid fa-xmark" :value="$report['summary']['ditolak']" label="Ditolak" />
        </div>
        <div class="col">
            <x-stat-card icon="fa-solid fa-hourglass-half" :value="$report['summary']['menunggu']" label="Menunggu" tone="warn" />
        </div>
    </div>

    <div class="kn-panel mb-3">
        <h3 class="kn-panel-title">Rekap Shift per Karyawan</h3>
        <div class="table-responsive">
            @include('laporan._tabel-shift')
        </div>
    </div>

    <div class="kn-panel">
        <h3 class="kn-panel-title">Rekap Pengajuan per Karyawan</h3>
        <div class="table-responsive">
            @include('laporan._tabel-pengajuan')
        </div>
        <div class="kn-muted small mt-2">Pengajuan dihitung berdasarkan tanggal pengajuan dibuat.</div>
    </div>
@endsection
