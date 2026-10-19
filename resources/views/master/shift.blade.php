@extends('layouts.app')

@section('title', 'Master Shift')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php $editId = (int) old('edit_id') ?: null; @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <h1 class="kn-page-title">Master Shift</h1>
            <div class="kn-page-sub">Nama dan jam kerja shift yang dipakai di Jadwal Shift</div>
        </div>
        <button type="button" class="btn-kn" id="openAdd">+ Tambah Shift</button>
    </div>

    @if (session('notice'))
        <div class="alert kn-alert alert-dismissible fade show" role="alert">
            {{ session('notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <div class="kn-panel p-0">
        <div class="table-responsive">
            <table class="table kn-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Nama Shift</th>
                        <th>Jam</th>
                        <th>Dipakai Jadwal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $shift->name }}</td>
                            <td>
                                {{ $shift->time_range }}
                                @if ($shift->end_date <= $shift->start_date)
                                    <span class="kn-muted small">(lewat tengah malam)</span>
                                @endif
                            </td>
                            <td data-used="{{ $shift->schedules_count }}">{{ $shift->schedules_count }}</td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn-kn-outline kn-btn-sm" data-edit
                                    data-url="{{ route('master.shift.update', $shift) }}" data-id="{{ $shift->id }}"
                                    data-name="{{ $shift->name }}" data-start="{{ substr($shift->start_date, 0, 5) }}"
                                    data-end="{{ substr($shift->end_date, 0, 5) }}"
                                    data-used="{{ $shift->schedules_count }}">Ubah</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="kn-empty">Belum ada shift.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="formModal" tabindex="-1" aria-labelledby="formTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" id="masterForm" class="modal-content"
                action="{{ $editId ? route('master.shift.update', $editId) : route('master.shift.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" id="methodField" @disabled(!$editId)>
                <input type="hidden" name="edit_id" value="{{ $editId }}" id="editIdField">

                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle">{{ $editId ? 'Ubah Shift' : 'Tambah Shift' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert kn-alert small d-none" id="usedWarning" role="status"></div>

                    <div class="mb-3">
                        <label for="nameField" class="form-label small fw-semibold">Nama Shift</label>
                        <input type="text" name="name" id="nameField" maxlength="50" required
                            class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label for="startField" class="form-label small fw-semibold">Jam Mulai</label>
                            <input type="time" name="start_date" id="startField" required
                                class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date') }}">
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-6">
                            <label for="endField" class="form-label small fw-semibold">Jam Selesai</label>
                            <input type="time" name="end_date" id="endField" required
                                class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="kn-muted small mt-2">
                        Jam selesai boleh lebih kecil dari jam mulai untuk shift yang lewat tengah malam, misalnya 21:00
                        sampai 03:30.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-kn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal'));
            const form = document.getElementById('masterForm');
            const field = (id) => document.getElementById(id);

            function open(edit) {
                form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());

                form.action = edit ? edit.url : @json(route('master.shift.store'));
                field('methodField').disabled = !edit;
                field('editIdField').value = edit ? edit.id : '';
                field('formTitle').textContent = edit ? 'Ubah Shift' : 'Tambah Shift';
                field('nameField').value = edit ? edit.name : '';
                field('startField').value = edit ? edit.start : '';
                field('endField').value = edit ? edit.end : '';

                const used = edit ? Number(edit.used) : 0;
                const warning = field('usedWarning');
                warning.classList.toggle('d-none', used === 0);
                warning.textContent = used > 0 ?
                    'Shift ini dipakai ' + used +
                    ' jadwal. Penugasan lama tidak berubah, tetapi nama dan jam barunya ikut tampil di jadwal lama.' :
                    '';

                modal.show();
            }

            field('openAdd').addEventListener('click', () => open(null));
            document.querySelectorAll('[data-edit]').forEach((button) => {
                button.addEventListener('click', () => open(button.dataset));
            });

            @if ($errors->any())
                modal.show();
            @endif
        })();
    </script>
@endpush
