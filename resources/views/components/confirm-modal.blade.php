<div class="modal fade" id="hbConfirm" tabindex="-1" aria-labelledby="hbConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content hb-modal">
            <form method="POST" action="#" data-hb-loading>
                @csrf
                <input type="hidden" name="_method" value="DELETE" data-confirm-method>
                <div class="modal-body p-4 text-center">
                    <span class="hb-confirm-icon is-danger" data-confirm-icon><i class="bi bi-exclamation-triangle"></i></span>
                    <h2 class="h5 mt-3" id="hbConfirmTitle" data-confirm-title>Konfirmasi</h2>
                    <p class="text-muted mb-0" data-confirm-message></p>
                </div>
                <div class="modal-footer justify-content-center border-0 pb-4 gap-2">
                    <button type="button" class="hb-btn hb-btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="hb-btn hb-btn-danger" data-confirm-submit>Ya, lanjutkan</button>
                </div>
            </form>
        </div>
    </div>
</div>
