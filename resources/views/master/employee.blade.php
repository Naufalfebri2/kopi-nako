@extends('layouts.app')

@section('title', 'Master Employee')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}" rel="stylesheet">
    <link href="{{ asset('css/jadwal.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-4">
        <div>
            <h1 class="kn-page-title">Master Employee</h1>
            <div class="kn-page-sub">Daftar semua karyawan, aktif dan nonaktif</div>
        </div>
        <button type="button" class="btn-kn" id="openAdd">+ Tambah Karyawan</button>
    </div>

    @if (session('notice'))
        <div class="alert kn-alert alert-dismissible fade show" role="alert">
            {{ session('notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <form method="GET" action="{{ route('master.employee.index') }}"
        class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <input type="search" name="q" value="{{ $term }}" maxlength="100" class="form-control kn-filter"
            style="max-width: 280px" placeholder="Cari nama, email, atau jabatan" aria-label="Cari karyawan">
        <select name="status" class="form-select kn-filter" onchange="this.form.submit()" aria-label="Filter status">
            <option value="">Semua Status</option>
            @foreach (\App\Enums\EmployeeStatus::cases() as $option)
                <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->value }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-kn-outline kn-btn-sm">Cari</button>
        @if ($term !== '' || $status)
            <a href="{{ route('master.employee.index') }}" class="btn-kn-outline kn-btn-sm">Reset</a>
        @endif
    </form>

    <div class="kn-panel p-0">
        <div class="table-responsive">
            <table class="table kn-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Karyawan</th>
                        <th>Kode Karyawan</th>
                        <th>Jabatan</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php $isSelf = $employee->id_user === auth()->id(); @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="kn-avatar kn-avatar-sm">{{ $employee->user->initials }}</span>
                                    <div>
                                        <div class="fw-semibold">{{ $employee->user->name }}</div>
                                        <div class="kn-muted small">{{ $employee->user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $employee->nik }}</td>
                            <td>{{ $employee->job?->name }}</td>
                            <td>{{ $employee->user->role?->name }}</td>
                            <td>
                                <span
                                    class="badge {{ $employee->status === \App\Enums\EmployeeStatus::Aktif ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $employee->status->value }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn-kn-outline kn-btn-sm" data-emp-edit
                                    data-update-url="{{ route('karyawan.update', $employee) }}"
                                    data-id="{{ $employee->id }}" data-name="{{ $employee->user->name }}"
                                    data-email="{{ $employee->user->email }}" data-job="{{ $employee->job_id }}"
                                    data-role="{{ $employee->user->id_role }}"
                                    data-self="{{ $isSelf ? 1 : 0 }}">Ubah</button>
                                @if ($employee->status === \App\Enums\EmployeeStatus::Nonaktif)
                                    <form method="POST" action="{{ route('karyawan.activate', $employee) }}"
                                        class="d-inline" onsubmit="return confirm(@js('Aktifkan kembali ' . $employee->user->name . '? Karyawan akan tampil lagi di Jadwal Shift.'))">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-kn-outline kn-btn-sm">Aktifkan</button>
                                    </form>
                                @endif
                                @unless ($isSelf)
                                    <button type="button" class="btn-kn-outline kn-btn-sm" data-emp-delete
                                        data-destroy-url="{{ route('karyawan.destroy', $employee) }}"
                                        data-deactivate-url="{{ route('karyawan.deactivate', $employee) }}"
                                        data-name="{{ $employee->user->name }}">Hapus</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="kn-empty">Tidak ada karyawan untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $employees->links() }}</div>

    {{-- Tambah karyawan --}}
    <div class="modal fade kn-modal" id="empAddModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" action="{{ route('karyawan.store') }}" class="modal-content" novalidate>
                @csrf
                <input type="hidden" name="dari" value="master">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Tambah Karyawan</h5>
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
                        <label class="form-label small fw-semibold" for="addName">Nama lengkap</label>
                        <input type="text" class="form-control" id="addName" name="name" maxlength="255"
                            value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="addEmail">Email</label>
                        <input type="email" class="form-control" id="addEmail" name="email" maxlength="255"
                            value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="addPhone">Nomor telepon</label>
                        <input type="text" class="form-control" id="addPhone" name="no_phone" maxlength="20"
                            value="{{ old('no_phone') }}" placeholder="081234567890" required>
                        <div class="form-text">Kode karyawan dibuat otomatis saat disimpan.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold" for="addJob">Jabatan</label>
                            <select class="form-select" id="addJob" name="job_id" required>
                                <option value="">Pilih jabatan</option>
                                @foreach ($jobs as $job)
                                    <option value="{{ $job->id }}" @selected((string) old('job_id') === (string) $job->id)>{{ $job->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold" for="addRole">Role akses</label>
                            <select class="form-select" id="addRole" name="id_role" required>
                                <option value="">Pilih role</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected((string) old('id_role') === (string) $role->id)>{{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="addPassword">Kata sandi</label>
                            <input type="password" class="form-control" id="addPassword" name="password"
                                autocomplete="new-password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="addPasswordConfirm">Ulangi kata sandi</label>
                            <input type="password" class="form-control" id="addPasswordConfirm"
                                name="password_confirmation" autocomplete="new-password" required>
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

    @include('jadwal._karyawan-modal', ['jobs' => $jobs, 'roles' => $roles])
@endsection

@push('scripts')
    <script>
        (function() {
            var addModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('empAddModal'));

            document.getElementById('openAdd').addEventListener('click', function() {
                addModal.show();
            });

            // Buka kembali bila penambahan dari halaman ini ditolak, supaya pesan kesalahan terlihat.
            @if (old('dari') === 'master')
                addModal.show();
            @endif
        })();
    </script>
@endpush
