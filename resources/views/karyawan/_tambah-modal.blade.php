@php
    $fieldStep = [
        'name' => 1,
        'email' => 1,
        'no_phone' => 1,
        'nik' => 2,
        'job_id' => 2,
        'id_role' => 2,
        'password' => 2,
    ];
    $reopen = $errors->hasAny(array_keys($fieldStep));
    $startStep = 1;
    if ($reopen) {
        foreach ($fieldStep as $field => $step) {
            if ($errors->has($field)) {
                $startStep = $step;
                break;
            }
        }
    }
@endphp

<div class="modal fade kn-wizard" id="knTambahKaryawan" tabindex="-1" data-bs-backdrop="static" aria-labelledby="knWizTitle"
    aria-hidden="true" data-reopen="{{ $reopen ? '1' : '0' }}" data-start-step="{{ $startStep }}">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="knEmpForm" method="POST" action="{{ route('karyawan.store') }}" novalidate autocomplete="off">
                @csrf
                <div class="kn-wiz-layout">
                    {{-- Panel langkah (desktop) --}}
                    <aside class="kn-wiz-side d-none d-md-flex">
                        <h2 class="kn-wiz-brand" id="knWizTitle">Tambah Karyawan</h2>
                        <ol class="kn-wiz-steps">
                            <li data-kn-step-item="1"><span class="kn-wiz-num">1</span> Data Diri &amp; Kontak</li>
                            <li data-kn-step-item="2"><span class="kn-wiz-num">2</span> Data Kepegawaian</li>
                            <li data-kn-step-item="3"><span class="kn-wiz-num">3</span> Konfirmasi</li>
                        </ol>
                    </aside>

                    <div class="kn-wiz-main">
                        <div class="kn-wiz-head">
                            <div>
                                <small class="text-muted d-md-none" id="knWizStepText">Langkah 1 dari 3</small>
                                <h3 class="kn-wiz-stepname" id="knWizStepName" aria-live="polite">Data Diri &amp; Kontak
                                </h3>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Tutup"></button>
                        </div>
                        <div class="progress kn-wiz-bar d-md-none" role="progressbar" aria-label="Kemajuan pengisian"
                            aria-valuemin="0" aria-valuemax="100" aria-valuenow="33">
                            <div class="progress-bar" style="width:33%"></div>
                        </div>

                        <div class="modal-body">
                            {{-- Langkah 1 --}}
                            <section data-kn-step="1">
                                <div class="mb-3">
                                    <label for="kn-name" class="form-label">Nama lengkap</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        id="kn-name" name="name" value="{{ old('name') }}" maxlength="255"
                                        required aria-describedby="kn-err-name"
                                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                                    <div class="invalid-feedback" id="kn-err-name">{{ $errors->first('name') }}</div>
                                </div>
                                <div class="mb-3">
                                    <label for="kn-email" class="form-label">Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                        id="kn-email" name="email" value="{{ old('email') }}" maxlength="255"
                                        required aria-describedby="kn-err-email"
                                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                                    <div class="invalid-feedback" id="kn-err-email">{{ $errors->first('email') }}</div>
                                </div>
                                <div class="mb-3">
                                    <label for="kn-no_phone" class="form-label">Nomor telepon</label>
                                    <input type="tel" class="form-control @error('no_phone') is-invalid @enderror"
                                        id="kn-no_phone" name="no_phone" value="{{ old('no_phone') }}" inputmode="tel"
                                        placeholder="08xxxxxxxxxx" required aria-describedby="kn-err-no_phone"
                                        aria-invalid="{{ $errors->has('no_phone') ? 'true' : 'false' }}">
                                    <div class="invalid-feedback" id="kn-err-no_phone">{{ $errors->first('no_phone') }}
                                    </div>
                                </div>
                            </section>

                            {{-- Langkah 2 --}}
                            <section data-kn-step="2" class="d-none">
                                <div class="mb-3">
                                    <label for="kn-nik" class="form-label">NIK</label>
                                    <input type="text" class="form-control @error('nik') is-invalid @enderror"
                                        id="kn-nik" name="nik" value="{{ old('nik') }}" maxlength="20"
                                        inputmode="numeric" required aria-describedby="kn-err-nik"
                                        aria-invalid="{{ $errors->has('nik') ? 'true' : 'false' }}">
                                    <div class="invalid-feedback" id="kn-err-nik">{{ $errors->first('nik') }}</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="kn-job_id" class="form-label">Jabatan</label>
                                        <select class="form-select @error('job_id') is-invalid @enderror"
                                            id="kn-job_id" name="job_id" required aria-describedby="kn-err-job_id"
                                            aria-invalid="{{ $errors->has('job_id') ? 'true' : 'false' }}">
                                            <option value="">Pilih jabatan</option>
                                            @foreach ($jobs as $job)
                                                <option value="{{ $job->id }}" @selected(old('job_id') == $job->id)>
                                                    {{ $job->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback" id="kn-err-job_id">
                                            {{ $errors->first('job_id') }}</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="kn-id_role" class="form-label">Role akses</label>
                                        <select class="form-select @error('id_role') is-invalid @enderror"
                                            id="kn-id_role" name="id_role" required aria-describedby="kn-err-id_role"
                                            aria-invalid="{{ $errors->has('id_role') ? 'true' : 'false' }}">
                                            <option value="">Pilih role</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @selected(old('id_role') == $role->id)>
                                                    {{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback" id="kn-err-id_role">
                                            {{ $errors->first('id_role') }}</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="kn-password" class="form-label">Kata sandi awal</label>
                                    <div class="input-group has-validation">
                                        <input type="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            id="kn-password" name="password" minlength="8" required
                                            autocomplete="new-password"
                                            aria-describedby="kn-hint-password kn-err-password"
                                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                                        <button type="button" class="btn btn-kn-outline"
                                            data-kn-toggle="kn-password" aria-pressed="false">Lihat</button>
                                        <div class="invalid-feedback" id="kn-err-password">
                                            {{ $errors->first('password') }}</div>
                                    </div>
                                    <div class="form-text" id="kn-hint-password">Minimal 8 karakter.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="kn-password_confirmation" class="form-label">Konfirmasi kata
                                        sandi</label>
                                    <div class="input-group has-validation">
                                        <input type="password" class="form-control" id="kn-password_confirmation"
                                            name="password_confirmation" required autocomplete="new-password"
                                            aria-describedby="kn-err-password_confirmation" aria-invalid="false">
                                        <button type="button" class="btn btn-kn-outline"
                                            data-kn-toggle="kn-password_confirmation"
                                            aria-pressed="false">Lihat</button>
                                        <div class="invalid-feedback" id="kn-err-password_confirmation"></div>
                                    </div>
                                </div>
                            </section>

                            {{-- Langkah 3 --}}
                            <section data-kn-step="3" class="d-none">
                                <p class="text-muted mb-3">Periksa kembali data berikut sebelum mendaftarkan karyawan.
                                </p>
                                <dl class="kn-summary">
                                    <dt>Nama lengkap</dt>
                                    <dd data-kn-sum="name"></dd>
                                    <dt>Email</dt>
                                    <dd data-kn-sum="email"></dd>
                                    <dt>Nomor telepon</dt>
                                    <dd data-kn-sum="no_phone"></dd>
                                    <dt>NIK</dt>
                                    <dd data-kn-sum="nik"></dd>
                                    <dt>Jabatan</dt>
                                    <dd data-kn-sum="job_id"></dd>
                                    <dt>Role akses</dt>
                                    <dd data-kn-sum="id_role"></dd>
                                    <dt>Kata sandi</dt>
                                    <dd data-kn-sum="password"></dd>
                                </dl>
                            </section>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-kn-outline" id="knWizPrev">Sebelumnya</button>
                            <button type="button" class="btn btn-kn" id="knWizNext">Selanjutnya</button>
                            <button type="submit" class="btn btn-kn d-none" id="knWizSubmit">Daftarkan
                                Karyawan</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
