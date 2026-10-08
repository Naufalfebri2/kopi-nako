<div class="kn-drawer-scroll">
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="kn-avatar kn-avatar-lg">{{ $req->employee->user->initials }}</span>
        <div>
            <div class="fw-semibold">{{ $req->employee->user->name }}</div>
            <div class="kn-muted small">{{ $req->employee->user->role?->name }}</div>
        </div>
    </div>

    <div class="kn-info mb-4">
        <div class="kn-info-row"><span>Jenis Pengajuan</span><span>{{ $req->type_label }}</span></div>
        @if ($req->leaveType)
            <div class="kn-info-row"><span>Jenis Izin</span><span>{{ $req->leaveType->name }}</span></div>
        @endif
        <div class="kn-info-row"><span>Shift Asal</span><span>{{ $req->source_shift_name }}</span></div>
        @if ($req->partner_id)
            <div class="kn-info-row"><span>Tukar dengan</span><span>{{ $req->partner?->user?->name }}</span></div>
            <div class="kn-info-row"><span>Shift Tujuan</span><span>{{ $req->target_shift_name }}</span></div>
        @endif
        <div class="kn-info-row"><span>Status</span><span><x-status-dot :status="$req->status" /></span></div>
    </div>

    <div class="kn-stepper mb-4">
        @foreach ($steps as $step)
            <div class="kn-step">
                <span class="kn-step-dot kn-step-{{ $step['state'] }}">
                    @switch($step['state'])
                        @case('done')
                            <i class="fa-solid fa-check"></i>
                        @break

                        @case('rejected')
                            <i class="fa-solid fa-xmark"></i>
                        @break

                        @case('skipped')
                            <i class="fa-solid fa-minus"></i>
                        @break
                    @endswitch
                </span>
                <div>
                    <div class="kn-step-title">
                        {{ $step['title'] }}
                        @if ($step['state'] === 'active')
                            <span class="kn-step-badge">Berjalan</span>
                        @endif
                    </div>
                    <div class="kn-step-meta">{{ $step['meta'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="kn-note">
        <div class="kn-note-label">Alasan Pengajuan</div>
        {{ $req->reason }}
    </div>
</div>

<div class="kn-drawer-footer">
    @if ($canDecide)
        <form method="POST" action="{{ route('pengajuan.decide', $req) }}" class="flex-fill"
            onsubmit="return confirm('Tolak pengajuan ini?')">
            @csrf
            <input type="hidden" name="keputusan" value="tolak">
            <button type="submit" class="btn-kn-outline w-100" style="color: var(--accent-deep);">Tolak</button>
        </form>
        <form method="POST" action="{{ route('pengajuan.decide', $req) }}" class="flex-fill"
            onsubmit="return confirm('{{ $awaiting === 'leader' ? 'Verifikasi pengajuan ini dan teruskan ke Supervisor?' : 'Setujui pengajuan ini?' }}')">
            @csrf
            <input type="hidden" name="keputusan" value="setujui">
            <button type="submit"
                class="btn-kn w-100">{{ $awaiting === 'leader' ? 'Verifikasi' : 'Setujui' }}</button>
        </form>
    @else
        <button type="button" class="btn-kn-outline flex-fill" data-bs-dismiss="offcanvas">Tutup</button>
    @endif
</div>
