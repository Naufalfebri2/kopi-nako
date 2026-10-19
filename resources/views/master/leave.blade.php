@extends('layouts.app')

@section('title', 'Master Leave Type')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php $editId = (int) old('edit_id') ?: null; @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <h1 class="kn-page-title">Master Leave Type</h1>
            <div class="kn-page-sub">Jenis izin yang bisa dipilih karyawan saat mengajukan izin</div>
        </div>
        <button type="button" class="btn-kn" id="openAdd">+ Tambah Jenis Izin</button>
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
                        <th class="ps-4">Nama Jenis Izin</th>
                        <th>Dipakai Pengajuan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $type->name }}</td>
                            <td data-used="{{ $type->requests_count }}">{{ $type->requests_count }}</td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn-kn-outline kn-btn-sm" data-edit
                                    data-url="{{ route('master.leave.update', $type) }}" data-id="{{ $type->id }}"
                                    data-name="{{ $type->name }}" data-used="{{ $type->requests_count }}">Ubah</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="kn-empty">Belum ada jenis izin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="formModal" tabindex="-1" aria-labelledby="formTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" id="masterForm" class="modal-content"
                action="{{ $editId ? route('master.leave.update', $editId) : route('master.leave.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" id="methodField" @disabled(!$editId)>
                <input type="hidden" name="edit_id" value="{{ $editId }}" id="editIdField">

                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle">{{ $editId ? 'Ubah Jenis Izin' : 'Tambah Jenis Izin' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert kn-alert small d-none" id="usedWarning" role="status"></div>
                    <label for="nameField" class="form-label small fw-semibold">Nama Jenis Izin</label>
                    <input type="text" name="name" id="nameField" maxlength="255" required
                        class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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

                form.action = edit ? edit.url : @json(route('master.leave.store'));
                field('methodField').disabled = !edit;
                field('editIdField').value = edit ? edit.id : '';
                field('formTitle').textContent = edit ? 'Ubah Jenis Izin' : 'Tambah Jenis Izin';
                field('nameField').value = edit ? edit.name : '';

                const used = edit ? Number(edit.used) : 0;
                const warning = field('usedWarning');
                warning.classList.toggle('d-none', used === 0);
                warning.textContent = used > 0 ?
                    'Jenis izin ini dipakai ' + used + ' pengajuan. Nama barunya ikut tampil di pengajuan lama.' :
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
