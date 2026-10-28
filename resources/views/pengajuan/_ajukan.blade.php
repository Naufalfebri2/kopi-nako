<div class="modal fade" id="submitModal" tabindex="-1" aria-labelledby="submitTitle" aria-hidden="true"
    data-reopen="{{ $errors->any() || (session('error') && old('detail_id')) ? '1' : '' }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('pengajuan.store') }}" id="submitForm" class="modal-content"
            data-old-jenis="{{ old('jenis') }}" data-old-detail="{{ old('detail_id') }}"
            data-old-partner="{{ old('partner_detail_id') }}" data-old-leave="{{ old('leave_id') }}">
            @csrf

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="submitTitle">Ajukan Izin / Tukar Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
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

                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="submitDetail">Jadwal Saya</label>
                        <select class="form-select" id="submitDetail" name="detail_id" required>
                            <option value="">Pilih jadwal</option>
                            @foreach ($options as $option)
                                <option value="{{ $option['id'] }}" data-hari="{{ $option['hari'] }}"
                                    data-tanggal="{{ $option['tanggal'] }}" data-tahun="{{ $option['tahun'] }}"
                                    data-shift="{{ $option['shift'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Rincian jadwal yang dipilih: hanya tampilan, tanpa name sehingga tidak ikut terkirim --}}
                    <div class="row g-2 mb-3" id="jadwalInfo">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="infoHari">Hari</label>
                            <input type="text" class="form-control" id="infoHari" placeholder="—" readonly
                                tabindex="-1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="infoTanggal">Tanggal</label>
                            <input type="text" class="form-control" id="infoTanggal" placeholder="—" readonly
                                tabindex="-1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="infoTahun">Tahun</label>
                            <input type="text" class="form-control" id="infoTahun" placeholder="—" readonly
                                tabindex="-1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" for="infoShift">Shift</label>
                            <input type="text" class="form-control" id="infoShift" placeholder="—" readonly
                                tabindex="-1">
                        </div>
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
                        <textarea class="form-control" id="submitReason" name="reason" rows="4" minlength="20" maxlength="500"
                            required placeholder="Jelaskan alasan pengajuan Anda dengan kalimat yang lengkap (minimal 20 karakter)...">{{ old('reason') }}</textarea>
                        <div class="kn-muted small text-end mt-1" id="submitCount">0 karakter</div>
                    </div>
                @endif
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-kn" @disabled($options->isEmpty())>Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="submitMap">@json($submitMap)</script>
