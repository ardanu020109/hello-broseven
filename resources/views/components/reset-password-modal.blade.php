<div class="modal fade" id="hbResetPw" tabindex="-1" aria-labelledby="hbResetPwTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hb-modal">
            <form method="POST" action="#" data-hb-loading>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="hbResetPwTitle">Reset Password</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Password baru untuk <strong data-resetpw-name></strong>. Semua sesi login karyawan ini akan diakhiri.</p>
                    <label for="resetPwInput" class="form-label fw-semibold">Password baru</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="resetPwInput" name="password" minlength="8" maxlength="100" autocomplete="new-password" required>
                        <button type="button" class="btn btn-outline-secondary" data-hb-togglepass="#resetPwInput" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-hb-genpass="#resetPwInput">Buat acak</button>
                    </div>
                    <div class="form-text">Minimal 8 karakter.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="hb-btn hb-btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
