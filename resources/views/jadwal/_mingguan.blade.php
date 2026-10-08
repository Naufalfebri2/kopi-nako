<div class="kn-panel p-0">
    <div class="table-responsive">
        <table class="table kn-week align-middle mb-0">
            <thead>
                <tr>
                    <th class="kn-week-emp">Karyawan</th>
                    @foreach ($days as $day)
                        <th class="text-center">
                            {{ $day->copy()->locale('id')->translatedFormat('D') }}
                            <span class="kn-daynum {{ $day->isToday() ? 'is-today' : '' }}">{{ $day->day }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $employee = $row['employee']; @endphp
                    <tr>
                        <td class="kn-week-emp">
                            <div class="d-flex align-items-center gap-2">
                                <span class="kn-avatar kn-avatar-sm">{{ $employee->user->initials }}</span>
                                <div>
                                    <div class="fw-semibold small">{{ $employee->user->name }}</div>
                                    <div class="kn-muted small">{{ $employee->job?->name }}</div>
                                </div>
                            </div>
                        </td>
                        @foreach ($row['cells'] as $i => $detail)
                            <td>
                                @if ($detail)
                                    @php $shift = $detail->schedule->masterShift; @endphp

                                    @if ($canEdit)
                                        <button type="button"
                                            class="kn-shift kn-shift-{{ strtolower($shift->name) }} kn-shift-edit"
                                            data-edit title="Klik untuk mengubah atau menghapus"
                                            data-update-url="{{ route('jadwal.update', $detail) }}"
                                            data-destroy-url="{{ route('jadwal.destroy', $detail) }}"
                                            data-employee="{{ $employee->user->name }}"
                                            data-date-label="{{ $days[$i]->copy()->locale('id')->translatedFormat('l, j M Y') }}"
                                            data-shift="{{ $shift->id }}"
                                            data-lead="{{ $detail->status_lead ? 1 : 0 }}"
                                            data-can-lead="{{ $employee->user->hasRole(\App\Models\Role::LEADER) ? 1 : 0 }}">
                                            {{ $shift->name }}
                                            @if ($detail->status_lead)
                                                <i class="fa-solid fa-star kn-lead" title="Leader pada shift ini"></i>
                                            @endif
                                            <span class="kn-shift-hrs">{{ $shift->time_range }}</span>
                                        </button>
                                    @else
                                        <span class="kn-shift kn-shift-{{ strtolower($shift->name) }}">
                                            {{ $shift->name }}
                                            @if ($detail->status_lead)
                                                <i class="fa-solid fa-star kn-lead" title="Leader pada shift ini"></i>
                                            @endif
                                            <span class="kn-shift-hrs">{{ $shift->time_range }}</span>
                                        </span>
                                    @endif
                                @elseif ($canEdit)
                                    <button type="button" class="kn-shift kn-shift-libur kn-shift-add" data-assign
                                        data-employee="{{ $employee->id }}"
                                        data-date="{{ $days[$i]->toDateString() }}">
                                        <span class="kn-add-idle">Libur</span>
                                        <span class="kn-add-hover">+ Tugaskan</span>
                                    </button>
                                @else
                                    <span class="kn-shift kn-shift-libur">Libur</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="kn-empty">Tidak ada karyawan untuk filter ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($canEdit)
    <div class="kn-muted small mt-2">
        Klik sel Libur untuk menugaskan karyawan. Klik sel yang berisi shift untuk mengubah atau menghapusnya.
    </div>
@endif
