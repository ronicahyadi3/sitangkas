<div class="modal fade" id="verifyData" tabindex="-1" role="dialog" aria-labelledby="modal-title-verify"
    aria-hidden="true">
    <div class="modal-dialog modal-danger modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modal-title-verify">Perhatian Anda dibutuhkan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="py-3 text-center">
                    <i class="fa-solid fa-bell fa-4x"></i>
                    <h4 class="text-gradient text-danger mt-4">Apakah Anda ingin verifikasi?</h4>
                    <p>Dokumen yang dipilih akan <b>DIVERIFIKASI</b> dan lanjut ke tahap berikutnya sesuai alur.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="verify-button">Ok,
                    Mengerti</button>
                <button type="button" class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

<script>
    let verifyDocumentModal = null;
    let verifyDocumentRequestData = null;
    $(function() {
        const modalElement = document.getElementById('verifyData');
        verifyDocumentModal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const $verifyButton = $('#verify-button');

        function safeReload(dt) {
            if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
                dt.ajax.reload();
            }
        }

        function resetVerifyState() {
            verifyDocumentRequestData = null;
            $verifyButton.prop('disabled', false);
        }

        $verifyButton.on('click', function() {
            if (window.readOnlyUI?.guardAction()) return;
            if (!verifyDocumentRequestData) {
                notification({
                    status: 400,
                    message: 'Data dokumen tidak ditemukan. Silakan ulangi proses verifikasi.'
                });
                return;
            }

            $verifyButton.prop('disabled', true);

            $.ajax({
                type: "POST",
                url: "{{ route('document.verify') }}",
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: verifyDocumentRequestData,
                success: function(response) {
                    notification(response);
                    safeReload(typeof mainTable !== 'undefined' ? mainTable : null);
                    resetVerifyState();
                    verifyDocumentModal.hide();
                },
                error: function(xhr) {
                    $verifyButton.prop('disabled', false);
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message ||
                            'Terjadi kesalahan saat memproses verifikasi data.'
                    });
                }
            });
        });

        $('#verifyData').on('hidden.bs.modal', function() {
            resetVerifyState();
        });
    });

    $(document).on('click', '.verify_data', function(e) {
        e.preventDefault();
        if (window.readOnlyUI?.guardAction()) return;
        const button = $(this);
        verifyDocumentRequestData = {
            id: button.data('id') ?? button.val(),
            src_type: button.data('type'),
            payment_type: button.data('payment')
        };
        if (!verifyDocumentRequestData.id) {
            notification({
                status: 400,
                message: 'ID dokumen tidak valid.'
            });
            return;
        }
        verifyDocumentModal.show();
    });
</script>
