<div class="modal fade kn-modal" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="editForm" action="#" class="modal-content">
            @csrf
            @method('PUT')

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Ubah Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="kn-edit-summary">
                    <div class="fw-semibold" id="editEmployee"></div>
                    <div class="kn-muted small" id="editDate"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="editShift">Shift</label>
                    <select class="form-select" id="editShift" name="shift_id" required>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }} ({{ $shift->time_range }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="editLead" name="status_lead" value="1">
                    <label class="form-check-label small" for="editLead">
                        Leader pada shift ini (hanya untuk role Leader/Head)
                    </label>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="submit" form="editDeleteForm" class="btn-kn-outline me-auto kn-delete-btn">Hapus</button>
                <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-kn">Simpan</button>
            </div>
        </form>

        {{-- Form terpisah (tidak boleh di dalam form di atas); dipicu tombol Hapus lewat atribut form --}}
        <form method="POST" id="editDeleteForm" action="#" class="d-none"
            onsubmit="return confirm('Hapus penugasan ini? Karyawan akan menjadi Libur pada tanggal tersebut.')">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
