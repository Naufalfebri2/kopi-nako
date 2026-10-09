<table class="table kn-table rpt-table align-middle mb-0">
    <thead>
        <tr>
            <th>Karyawan</th>
            <th>Jabatan</th>
            <th class="num">Izin</th>
            <th class="num">Tukar Shift</th>
            <th class="num">Menunggu</th>
            <th class="num">Disetujui</th>
            <th class="num">Ditolak</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report['requestRows'] as $row)
            <tr>
                <td>{{ $row['employee']->user->name }}</td>
                <td>{{ $row['employee']->job?->name }}</td>
                <td class="num">{{ $row['izin'] }}</td>
                <td class="num">{{ $row['tukar'] }}</td>
                <td class="num">{{ $row['menunggu'] }}</td>
                <td class="num">{{ $row['disetujui'] }}</td>
                <td class="num">{{ $row['ditolak'] }}</td>
                <td class="num"><strong>{{ $row['total'] }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="kn-empty">Tidak ada pengajuan pada periode ini.</td>
            </tr>
        @endforelse
    </tbody>
    @if ($report['requestRows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $report['summary']['izin'] }}</td>
                <td class="num">{{ $report['summary']['tukar'] }}</td>
                <td class="num">{{ $report['summary']['menunggu'] }}</td>
                <td class="num">{{ $report['summary']['disetujui'] }}</td>
                <td class="num">{{ $report['summary']['ditolak'] }}</td>
                <td class="num">{{ $report['summary']['pengajuan'] }}</td>
            </tr>
        </tfoot>
    @endif
</table>
