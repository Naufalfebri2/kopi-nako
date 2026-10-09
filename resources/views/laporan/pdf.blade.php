<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Shift Kopi Nako</title>
    <style>
        @page {
            margin: 28px 32px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1714;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 12px;
            margin: 18px 0 6px;
        }

        .meta {
            color: #8a8177;
            margin-bottom: 2px;
        }

        .summary {
            margin: 12px 0 4px;
            padding: 8px 10px;
            background: #f7f5f2;
            border: 1px solid #e5e1da;
        }

        .summary span {
            margin-right: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 5px 7px;
            border: 1px solid #e5e1da;
            text-align: left;
        }

        th {
            background: #f7f5f2;
            font-size: 9px;
        }

        .num {
            text-align: right;
        }

        tfoot td {
            font-weight: bold;
            background: #f7f5f2;
        }

        .kn-empty {
            text-align: center;
            color: #8a8177;
        }

        .note {
            margin-top: 14px;
            color: #8a8177;
            font-size: 9px;
        }
    </style>
</head>

<body>
    <h1>Kopi Nako Pamulang — Laporan Shift dan Pengajuan</h1>
    <div class="meta">
        Periode: {{ $periodLabel }}@if ($report['jobName'])
            · Jabatan: {{ $report['jobName'] }}
        @endif
    </div>
    <div class="meta">Dibuat oleh {{ $generatedBy }} pada {{ $generatedAt }}</div>

    <div class="summary">
        <span><strong>{{ $report['summary']['penugasan'] }}</strong> penugasan shift</span>
        <span><strong>{{ $report['summary']['pengajuan'] }}</strong> pengajuan</span>
        <span><strong>{{ $report['summary']['disetujui'] }}</strong> disetujui</span>
        <span><strong>{{ $report['summary']['ditolak'] }}</strong> ditolak</span>
        <span><strong>{{ $report['summary']['menunggu'] }}</strong> menunggu</span>
    </div>

    <h2>Rekap Shift per Karyawan</h2>
    @include('laporan._tabel-shift')

    <h2>Rekap Pengajuan per Karyawan</h2>
    @include('laporan._tabel-pengajuan')

    <div class="note">
        Laporan ini merekap jadwal shift dan pengajuan, bukan catatan kehadiran.
        Pengajuan dihitung berdasarkan tanggal pengajuan dibuat.
    </div>
</body>

</html>
