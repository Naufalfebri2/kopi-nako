@php
    $bag = $errors->editKaryawan;
    $reopenId = $bag->any() ? old('edit_employee_id') : '';
@endphp

{{-- Ubah karyawan --}}
<div class="modal fade kn-modal" id="empEditModal" tabindex="-1" aria-hidden="true" data-reopen-id="{{ $reopenId }}"
    data-url-template="{{ route('karyawan.update', ['employee' => '__ID__']) }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="#" class="modal-content" id="empEditForm" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_employee_id" value="{{ old('edit_employee_id') }}">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Ubah Data Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                @if ($bag->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach ($bag->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="empName">Nama lengkap</label>
                    <input type="text" class="form-control" id="empName" name="name" maxlength="255"
                        value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="empEmail">Email</label>
                    <input type="email" class="form-control" id="empEmail" name="email" maxlength="255"
                        value="{{ old('email') }}" required>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold" for="empJob">Jabatan</label>
                        <select class="form-select" id="empJob" name="job_id" required>
                            <option value="">Pilih jabatan</option>
                            @foreach ($jobs as $job)
                                <option value="{{ $job->id }}" @selected((string) old('job_id') === (string) $job->id)>{{ $job->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-semibold" for="empRole">Role akses</label>
                        <select class="form-select" id="empRole" name="id_role" required>
                            <option value="">Pilih role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) old('id_role') === (string) $role->id)>{{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text d-none" id="empRoleSelfHint">Role akun Anda sendiri tidak dapat diubah.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-kn">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Hapus karyawan --}}
<div class="modal fade kn-modal" id="empDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Hapus Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Hapus <strong id="empDeleteName"></strong> secara permanen?</p>
                <p class="small text-muted mb-0">
                    Cocok untuk data yang salah masukkan. Karyawan yang sudah punya jadwal atau pengajuan
                    tidak bisa dihapus permanen. Untuk itu pilih "Nonaktifkan saja".
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn-kn-outline" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="empDeactivateForm" class="btn-kn-outline">Nonaktifkan saja</button>
                <button type="submit" form="empDeleteForm" class="btn-kn">Hapus</button>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="#" id="empDeleteForm" class="d-none">
    @csrf
    @method('DELETE')
</form>
<form method="POST" action="#" id="empDeactivateForm" class="d-none">
    @csrf
    @method('PATCH')
</form>

@push('scripts')
    <script>
        (function() {
            'use strict';

            var editEl = document.getElementById('empEditModal');
            var delEl = document.getElementById('empDeleteModal');
            if (!editEl || !delEl) return;

            var editModal = bootstrap.Modal.getOrCreateInstance(editEl);
            var delModal = bootstrap.Modal.getOrCreateInstance(delEl);
            var form = document.getElementById('empEditForm');
            var delForm = document.getElementById('empDeleteForm');
            var deactForm = document.getElementById('empDeactivateForm');
            var selfHint = document.getElementById('empRoleSelfHint');

            document.querySelectorAll('[data-emp-edit]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var d = btn.dataset;
                    var alertBox = editEl.querySelector('.alert-danger');
                    if (alertBox) alertBox.remove();

                    form.action = d.updateUrl;
                    form.elements.edit_employee_id.value = d.id;
                    form.elements.name.value = d.name;
                    form.elements.email.value = d.email;
                    form.elements.job_id.value = d.job;
                    form.elements.id_role.value = d.role;
                    selfHint.classList.toggle('d-none', d.self !== '1');

                    editModal.show();
                });
            });

            document.querySelectorAll('[data-emp-delete]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    delForm.action = btn.dataset.destroyUrl;
                    deactForm.action = btn.dataset.deactivateUrl;
                    document.getElementById('empDeleteName').textContent = btn.dataset.name;
                    delModal.show();
                });
            });

            // Buka kembali modal ubah bila server menolak, supaya pesan kesalahan terlihat.
            var reopenId = editEl.dataset.reopenId;
            if (reopenId) {
                form.action = editEl.dataset.urlTemplate.replace('__ID__', reopenId);
                editModal.show();
            }
        })();
    </script>
@endpush
