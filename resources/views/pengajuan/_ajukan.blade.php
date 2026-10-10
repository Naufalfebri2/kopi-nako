<div class="offcanvas offcanvas-end kn-drawer" tabindex="-1" id="submitDrawer" aria-labelledby="submitTitle"
    data-reopen="{{ $errors->any() || (session('error') && old('detail_id')) ? '1' : '' }}">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="submitTitle">Ajukan Izin / Tukar Shift</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    <div class="offcanvas-body">
        <form method="POST" action="{{ route('pengajuan.store') }}" id="submitForm" class="kn-submit-form"
            data-old-jenis="{{ old('jenis') }}" data-old-detail="{{ old('detail_id') }}"
            data-old-partner="{{ old('partner_detail_id') }}" data-old-leave="{{ old('leave_id') }}">
            @csrf

            <div class="kn-drawer-scroll">
                @if ($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('error') && old('detail_id'))
                    <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
                @endif

                @if ($options->isEmpty())
                    <div class="kn-empty">Anda belum punya jadwal mendatang yang bisa diajukan.</div>
                @else
                    <label class="form-label small fw-semibold">Jenis Pengajuan</label>
                    <div class="kn-seg mb-3">
                        <input type="radio" class="btn-check" name="jenis" id="jenisIzin" value="izin" checked>
                        <label for="jenisIzin">Izin</label>
                        <input type="radio" class="btn-check" name="jenis" id="jenisTukar" value="tukar">
                        <label for="jenisTukar">Tukar Shift</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="submitDetail">Jadwal Saya</label>
                        <select class="form-select" id="submitDetail" name="detail_id" required>
                            <option value="">Pilih jadwal</option>
                            @foreach ($options as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3" id="izinFields">
                        <label class="form-label small fw-semibold" for="submitLeave">Jenis Izin</label>
                        <select class="form-select" id="submitLeave" name="leave_id">
                            <option value="">Pilih jenis izin</option>
                            @foreach ($leaveTypes as $leaveType)
                                <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="tukarFields">
                        <label class="form-label small fw-semibold" for="submitPartner">Tukar dengan</label>
                        <select class="form-select" id="submitPartner" name="partner_detail_id" disabled>
                            <option value="">Pilih rekan kerja</option>
                        </select>
                        <div class="form-text" id="tukarHint"></div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-semibold" for="submitReason">Alasan Pengajuan</label>
                        <textarea class="form-control" id="submitReason" name="reason" rows="4" minlength="10" maxlength="500" required
                            placeholder="Jelaskan alasan pengajuan Anda (minimal 10 karakter)...">{{ old('reason') }}</textarea>
                        <div class="kn-muted small text-end mt-1" id="submitCount">0 karakter</div>
                    </div>
                @endif
            </div>

            <div class="kn-drawer-footer">
                <button type="button" class="btn-kn-outline flex-fill" data-bs-dismiss="offcanvas">Batal</button>
                <button type="submit" class="btn-kn flex-fill" @disabled($options->isEmpty())>Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="submitMap">@json($submitMap)</script>
