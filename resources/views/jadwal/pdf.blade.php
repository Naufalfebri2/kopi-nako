<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Jadwal Shift</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1a1714;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
            color: #b4763a;
        }

        .sub {
            color: #6b635b;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #e6e0d8;
            padding: 7px 9px;
            text-align: left;
        }

        th {
            background: #f7f5f2;
        }

        .foot {
            margin-top: 18px;
            font-size: 10px;
            color: #8a8279;
        }
    </style>
</head>

<body>
    <h1>Jadwal Shift — Kopi Nako Pamulang</h1>
    <div class="sub">{{ $name }} · {{ $periodLabel }}</div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Shift</th>
                <th>Jam</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->date->copy()->locale('id')->translatedFormat('l, j M Y') }}</td>
                    <td>{{ $row->schedule->masterShift->name }}</td>
                    <td>{{ $row->schedule->masterShift->time_range }}</td>
                    <td>{{ $row->status_lead ? 'Leader shift' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="foot">Hari yang tidak tercantum adalah hari libur. Dokumen ini dibuat otomatis oleh sistem.</div>
</body>

</html>
