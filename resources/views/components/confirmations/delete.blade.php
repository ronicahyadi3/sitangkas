<div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="modal-title-delete"
    aria-hidden="true">
    <div class="modal-dialog modal-danger modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modal-title-delete">Perhatian Anda dibutuhkan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="py-3 text-center">
                    <i class="fa-solid fa-bell fa-4x"></i>
                    <h4 class="text-gradient text-danger mt-4">Apakah Anda ingin menghapus?</h4>
                    <p>Data dokumen akan <b>DIHAPUS</b> dari daftar aktif dan proses ini tidak dapat <b>DIBATALKAN</b>.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="delete-button">Ok, Lanjutkan</button>
                <button type="button" class="btn btn-link text-primary ms-auto" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

<script>
    let deleteDocumentModal = null;
    let deleteDocumentRequestData = null;
    $(function() {
        const modalElement = document.getElementById('modalDelete');
        deleteDocumentModal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const $deleteButton = $('#delete-button');

        function safeReload(dt) {
            if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
                dt.ajax.reload();
            }
        }

        function resetDeleteState() {
            deleteDocumentRequestData = null;
            $deleteButton.prop('disabled', false);
        }

        $deleteButton.on('click', function() {
            if (window.readOnlyUI?.guardAction()) return;
            if (!deleteDocumentRequestData) {
                notification({
                    status: 400,
                    message: 'Data dokumen tidak ditemukan. Silakan ulangi proses hapus.'
                });
                return;
            }

            $deleteButton.prop('disabled', true);

            $.ajax({
                type: "POST",
                url: "{{ route('document.delete') }}",
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: deleteDocumentRequestData,
                success: function(response) {
                    notification(response);
                    safeReload(mainTable);
                    resetDeleteState();
                    deleteDocumentModal.hide();
                },
                error: function(xhr) {
                    $deleteButton.prop('disabled', false);
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message ||
                            'Terjadi kesalahan saat memproses penghapusan data.'
                    });
                }
            });
        });

        $('#modalDelete').on('hidden.bs.modal', function() {
            resetDeleteState();
        });
    });

    $(document).on('click', '.delete', function(e) {
        e.preventDefault();
        if (window.readOnlyUI?.guardAction()) return;
        const button = $(this);
        deleteDocumentRequestData = {
            id: button.data('id') ?? button.val(),
            src_type: button.data('type'),
            payment_type: button.data('payment')
        };
        if (!deleteDocumentRequestData.id) {
            notification({
                status: 400,
                message: 'ID dokumen tidak valid.'
            });
            return;
        }
        deleteDocumentModal.show();
    });
</script>
