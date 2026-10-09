<table class="table kn-table rpt-table align-middle mb-0">
    <thead>
        <tr>
            <th>Karyawan</th>
            <th>Jabatan</th>
            @foreach ($report['shifts'] as $shift)
                <th class="num">{{ $shift->name }}</th>
            @endforeach
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report['shiftRows'] as $row)
            <tr>
                <td>{{ $row['employee']->user->name }}</td>
                <td>{{ $row['employee']->job?->name }}</td>
                @foreach ($report['shifts'] as $shift)
                    <td class="num">{{ $row['counts'][$shift->id] }}</td>
                @endforeach
                <td class="num"><strong>{{ $row['total'] }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $report['shifts']->count() + 3 }}" class="kn-empty">Tidak ada karyawan untuk filter ini.
                </td>
            </tr>
        @endforelse
    </tbody>
    @if ($report['shiftRows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                @foreach ($report['shifts'] as $shift)
                    <td class="num">{{ $report['shiftTotals']['counts'][$shift->id] }}</td>
                @endforeach
                <td class="num">{{ $report['shiftTotals']['total'] }}</td>
            </tr>
        </tfoot>
    @endif
</table>
