<div class="modal fade kn-modal" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
        <div class="modal-content p-4 text-center">
            <h5 class="fw-bold mb-2">Keluar dari akun?</h5>
            <p class="text-muted mb-4" style="font-size: 13.5px;">Anda perlu masuk kembali untuk mengakses dashboard.</p>
            <form method="POST" action="{{ route('logout') }}" class="d-flex gap-2">
                @csrf
                <button type="button" class="btn-kn-outline flex-fill" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-kn-danger flex-fill">Ya, Keluar</button>
            </form>
        </div>
    </div>
</div>
