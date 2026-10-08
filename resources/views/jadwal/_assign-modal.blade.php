<div class="modal fade kn-modal" id="assignModal" tabindex="-1" aria-hidden="true"
    data-reopen="{{ $errors->any() ? '1' : '' }}">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('jadwal.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="tampilan" value="{{ $view }}">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Tambah Shift</h5>
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

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="assignEmployee">Karyawan</label>
                    <select class="form-select" id="assignEmployee" name="employee_id" required>
                        <option value="">Pilih karyawan</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                                {{ $employee->user->name }} — {{ $employee->job?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="assignDate">Tanggal</label>
                    <input type="date" class="form-control" id="assignDate" name="date"
                        value="{{ old('date', $date->toDateString()) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="assignShift">Shift</label>
                    <select class="form-select" id="assignShift" name="shift_id" required>
                        <option value="">Pilih shift</option>
                        @foreach ($shifts as $shift)
                            <option value="{{ $shift->id }}" @selected((string) old('shift_id') === (string) $shift->id)>
                                {{ $shift->name }} ({{ $shift->time_range }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="assignLead" name="status_lead" value="1"
                        @checked(old('status_lead'))>
                    <label class="form-check-label small" for="assignLead">
                        Jadikan Leader pada shift ini (hanya untuk role Leader/Head)
                    </label>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-kn">Simpan</button>
            </div>
        </form>
    </div>
</div>
