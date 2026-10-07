@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="kn-page-title">Ringkasan Shift Minggu Ini</h1>
    <div class="kn-page-sub mb-4">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</div>

    <div class="kn-panel">Dashboard akan diisi di tahap berikutnya.</div>
@endsection
