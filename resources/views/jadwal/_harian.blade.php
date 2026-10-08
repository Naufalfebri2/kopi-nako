@php
    $icons = ['pagi' => 'fa-sun', 'siang' => 'fa-cloud-sun', 'malam' => 'fa-moon'];
@endphp

<div class="row g-3">
    @foreach ($columns as $column)
        @php
            $shift = $column['shift'];
            $icon = $icons[strtolower($shift->name)] ?? 'fa-clock';
        @endphp
        <div class="col-lg-4">
            <div class="kn-panel kn-board-col">
                <div class="kn-board-head">
                    <i class="fa-solid {{ $icon }}"></i>
                    <span class="kn-board-name">{{ $shift->name }}</span>
                    <span class="kn-board-hours">{{ $shift->time_range }}</span>
                </div>

                @if ($canEdit)
                    <button type="button" class="kn-board-add" data-assign data-shift="{{ $shift->id }}"
                        data-date="{{ $date->toDateString() }}">+ Tugaskan</button>
                @endif

                @forelse ($column['details'] as $detail)
                    <div class="kn-board-card">
                        <span class="kn-avatar kn-avatar-sm">{{ $detail->employee->user->initials }}</span>
                        <div class="flex-fill">
                            <div class="fw-semibold small">
                                {{ $detail->employee->user->name }}
                                @if ($detail->status_lead)
                                    <i class="fa-solid fa-star kn-lead" title="Leader pada shift ini"></i>
                                @endif
                            </div>
                            <div class="kn-muted small">{{ $detail->employee->job?->name }}</div>
                        </div>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('jadwal.destroy', $detail) }}"
                                onsubmit="return confirm(@js('Hapus ' . $detail->employee->user->name . ' dari shift ' . $shift->name . '?'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="kn-board-remove" aria-label="Hapus dari shift">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="kn-empty">Belum ada karyawan.</div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
