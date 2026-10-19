@php
    $oldEmployeeIds = array_map('strval', (array) old('employee_ids', []));
@endphp

<div class="modal fade kn-modal" id="assignModal" tabindex="-1" aria-hidden="true"
    data-reopen="{{ $errors->any() ? '1' : '' }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('jadwal.store') }}" class="modal-content" id="assignForm" novalidate>
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

                <div class="alert alert-danger py-2 small d-none" id="assignClientError" role="alert"></div>

                {{-- Karyawan (boleh banyak) --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0" for="assignEmployeeSearch">Karyawan</label>
                        <span class="small text-muted" id="assignEmployeeCount">0 dipilih</span>
                    </div>
                    <input type="search" class="form-control form-control-sm mb-2" id="assignEmployeeSearch"
                        placeholder="Cari nama atau jabatan" autocomplete="off">
                    <div class="form-check border-bottom pb-2 mb-2">
                        <input class="form-check-input" type="checkbox" id="assignEmployeeAll">
                        <label class="form-check-label small fw-semibold" for="assignEmployeeAll">
                            Pilih semua yang tampil
                        </label>
                    </div>
                    <div id="assignEmployee" role="group" aria-label="Daftar karyawan"
                        style="max-height: 220px; overflow-y: auto;">
                        @foreach ($employees as $employee)
                            <div class="form-check" data-assign-row
                                data-search="{{ mb_strtolower($employee->user->name . ' ' . ($employee->job?->name ?? '')) }}">
                                <input class="form-check-input" type="checkbox" name="employee_ids[]"
                                    id="assignEmp{{ $employee->id }}" value="{{ $employee->id }}"
                                    @checked(in_array((string) $employee->id, $oldEmployeeIds, true))>
                                <label class="form-check-label small" for="assignEmp{{ $employee->id }}">
                                    {{ $employee->user->name }}
                                    <span class="text-muted">— {{ $employee->job?->name }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Maksimal 50 karyawan sekaligus.</div>
                </div>

                {{-- Tanggal (rentang) --}}
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold" for="assignDate">Dari tanggal</label>
                        <input type="date" class="form-control" id="assignDate" name="date"
                            value="{{ old('date', $date->toDateString()) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold" for="assignDateTo">Sampai tanggal</label>
                        <input type="date" class="form-control" id="assignDateTo" name="date_to"
                            value="{{ old('date_to') }}">
                    </div>
                    <div class="col-12 form-text mt-1">
                        Kosongkan "Sampai tanggal" untuk satu hari saja. Maksimal 31 hari.
                    </div>
                </div>

                {{-- Shift (satu pilihan, karena satu karyawan hanya punya satu shift per hari) --}}
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

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="assignLead" name="status_lead" value="1"
                        @checked(old('status_lead'))>
                    <label class="form-check-label small" for="assignLead">
                        Jadikan Leader pada shift ini (hanya untuk role Leader/Head)
                    </label>
                </div>

                <div class="small text-muted" id="assignSummary" aria-live="polite"></div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-kn">Simpan</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        (function() {
            'use strict';

            var form = document.getElementById('assignForm');
            if (!form) return;

            var MAX_EMP = 50,
                MAX_DAYS = 31,
                MAX_TOTAL = 200;
            var list = document.getElementById('assignEmployee');
            var rows = list.querySelectorAll('[data-assign-row]');
            var boxes = list.querySelectorAll('input[name="employee_ids[]"]');
            var search = document.getElementById('assignEmployeeSearch');
            var all = document.getElementById('assignEmployeeAll');
            var count = document.getElementById('assignEmployeeCount');
            var dateFrom = document.getElementById('assignDate');
            var dateTo = document.getElementById('assignDateTo');
            var summary = document.getElementById('assignSummary');
            var errBox = document.getElementById('assignClientError');

            function checkedCount() {
                var n = 0;
                boxes.forEach(function(b) {
                    if (b.checked) n++;
                });
                return n;
            }

            // Daftar tanggal Y-m-d dari rentang Dari-Sampai (UTC agar tidak geser zona waktu).
            function dateRange() {
                var from = dateFrom.value,
                    to = dateTo.value || from;
                if (!from) return [];
                var start = new Date(from + 'T00:00:00Z'),
                    end = new Date(to + 'T00:00:00Z');
                if (isNaN(start) || isNaN(end) || end < start) return [];
                var out = [],
                    d = new Date(start);
                while (d <= end && out.length <= MAX_DAYS) {
                    out.push(d.toISOString().slice(0, 10));
                    d.setUTCDate(d.getUTCDate() + 1);
                }
                return out;
            }

            function refresh() {
                var emp = checkedCount(),
                    days = dateRange().length;
                count.textContent = emp + ' dipilih';
                dateTo.min = dateFrom.value || '';

                if (!emp || !days) {
                    summary.textContent = '';
                    return;
                }
                var total = emp * days;
                summary.textContent = emp + ' karyawan × ' + days + ' hari = ' + total + ' jadwal' +
                    (total > MAX_TOTAL ? ' (melebihi batas ' + MAX_TOTAL + ' per proses)' : '');
            }

            function showError(msg) {
                errBox.textContent = msg;
                errBox.classList.toggle('d-none', !msg);
            }

            // Pencarian karyawan
            search.addEventListener('input', function() {
                var q = search.value.trim().toLowerCase();
                rows.forEach(function(row) {
                    row.classList.toggle('d-none', q !== '' && row.dataset.search.indexOf(q) === -1);
                });
            });

            // Pilih semua yang tampil
            all.addEventListener('change', function() {
                rows.forEach(function(row) {
                    if (!row.classList.contains('d-none')) {
                        row.querySelector('input').checked = all.checked;
                    }
                });
                refresh();
            });

            list.addEventListener('change', function() {
                var visible = 0,
                    visibleChecked = 0;
                rows.forEach(function(row) {
                    if (row.classList.contains('d-none')) return;
                    visible++;
                    if (row.querySelector('input').checked) visibleChecked++;
                });
                all.checked = visible > 0 && visible === visibleChecked;
                refresh();
            });

            dateFrom.addEventListener('change', refresh);
            dateTo.addEventListener('change', refresh);

            // Kirim: ubah rentang tanggal menjadi dates[] yang dibaca server.
            form.addEventListener('submit', function(e) {
                form.querySelectorAll('input[data-generated]').forEach(function(el) {
                    el.remove();
                });
                showError('');

                var emp = checkedCount(),
                    days = dateRange();

                if (!emp) {
                    e.preventDefault();
                    showError('Pilih minimal satu karyawan.');
                    return;
                }
                if (emp > MAX_EMP) {
                    e.preventDefault();
                    showError('Maksimal ' + MAX_EMP + ' karyawan sekaligus.');
                    return;
                }
                if (!days.length) {
                    e.preventDefault();
                    showError('Tanggal tidak valid. Pastikan "Sampai" tidak lebih awal dari "Dari".');
                    return;
                }
                if (days.length > MAX_DAYS) {
                    e.preventDefault();
                    showError('Maksimal ' + MAX_DAYS + ' hari sekaligus.');
                    return;
                }
                if (!document.getElementById('assignShift').value) {
                    e.preventDefault();
                    showError('Pilih shift.');
                    return;
                }
                if (emp * days.length > MAX_TOTAL) {
                    e.preventDefault();
                    showError('Terlalu banyak jadwal sekaligus. Maksimal ' + MAX_TOTAL + ' per proses.');
                    return;
                }

                days.forEach(function(d) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'dates[]';
                    input.value = d;
                    input.setAttribute('data-generated', '1');
                    form.appendChild(input);
                });
            });

            refresh();
        })();
    </script>
@endpush
