@php($isSelf = $employee->id_user === auth()->id())

<div class="kn-emp-actions">
    <button type="button" class="kn-icon-btn" data-emp-edit data-id="{{ $employee->id }}"
        data-update-url="{{ route('karyawan.update', $employee) }}" data-name="{{ $employee->user->name }}"
        data-email="{{ $employee->user->email }}" data-job="{{ $employee->job_id }}"
        data-role="{{ $employee->user->id_role }}" data-self="{{ $isSelf ? '1' : '' }}" title="Ubah data karyawan"
        aria-label="Ubah {{ $employee->user->name }}">
        <i class="fa-solid fa-pen" aria-hidden="true"></i>
    </button>

    @unless ($isSelf)
        <button type="button" class="kn-icon-btn kn-icon-btn-danger" data-emp-delete
            data-destroy-url="{{ route('karyawan.destroy', $employee) }}"
            data-deactivate-url="{{ route('karyawan.deactivate', $employee) }}" data-name="{{ $employee->user->name }}"
            title="Hapus karyawan" aria-label="Hapus {{ $employee->user->name }}">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>
    @endunless
</div>
