@extends('layouts.app')

@section('title', 'Jadwal Shift')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/jadwal.css') }}" rel="stylesheet">
@endpush

@section('content')
    <h1 class="kn-page-title">Jadwal Shift</h1>
    <div class="kn-page-sub mb-4">Susunan shift karyawan per minggu dan per hari</div>

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

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="kn-toggle">
            <a href="{{ $weeklyUrl }}" class="{{ $view === 'mingguan' ? 'active' : '' }}">Jadwal Mingguan</a>
            <a href="{{ $dailyUrl }}" class="{{ $view === 'harian' ? 'active' : '' }}">Papan Harian</a>
        </div>

        <div class="kn-datenav">
            <a href="{{ $prevUrl }}" aria-label="Sebelumnya"><i class="fa-solid fa-chevron-left"></i></a>
            <span>{{ $rangeLabel }}</span>
            <a href="{{ $nextUrl }}" aria-label="Berikutnya"><i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <a href="{{ $todayUrl }}" class="btn-kn-outline kn-btn-sm">Hari Ini</a>

        <form method="GET" action="{{ route('jadwal.index') }}">
            <input type="hidden" name="tampilan" value="{{ $view }}">
            <input type="hidden" name="tanggal" value="{{ $date->toDateString() }}">
            <select name="jabatan" class="form-select kn-filter" onchange="this.form.submit()">
                <option value="">Semua Jabatan</option>
                @foreach ($jobs as $job)
                    <option value="{{ $job->id }}" @selected($jobId == $job->id)>{{ $job->name }}</option>
                @endforeach
            </select>
        </form>

        @if ($canEdit)
            <button type="button" class="btn-kn kn-btn-sm ms-auto" data-assign>Tambah Shift</button>
        @endif
    </div>

    @if ($view === 'mingguan')
        @include('jadwal._mingguan')
    @else
        @include('jadwal._harian')
    @endif

    @if ($canEdit)
        @include('jadwal._assign-modal')
        @include('jadwal._edit-modal')
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/jadwal.js') }}"></script>
@endpush
