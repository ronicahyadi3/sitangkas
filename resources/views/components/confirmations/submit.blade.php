    <div class="modal fade" id="submitData" tabindex="-1" role="dialog" aria-labelledby="submitDataTitle" aria-hidden="true">
        <div class="modal-dialog modal-danger modal-dialog-centered modal-" role="document">
            <div class="modal-content">
                <div class="card">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <h4 class="font-weight-bolder text-info text-gradient" id="submitDataTitle">Perhatian anda dibutuhkan</h4>
                        <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                    </div>
                    <div class="card-body mt-7 mb-3 text-center">
                        <i class="fa-solid fa-bell fa-2xl text-warning" style="font-size: 5rem;"></i>
                        <h4 class="heading mt-5 ">Apakah anda ingin submit?</h4>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-round bg-gradient-info btn-lg w-100 mt-4 mb-0"
                            id="btn_submit">Ok, Mengerti</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function() {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const $submitDataModal = $('#submitData');
            const $submitDataBtn = $('#btn_submit');

            function submitNotificationPayload(xhr, fallbackMessage) {
                const rawMessage = xhr.responseJSON?.message || fallbackMessage;
                const normalizedMessage = /sudah\s+(pernah\s+)?disubmit|sudah\s+disubmit/i.test(rawMessage)
                    ? 'Data telah disubmit sebelumnya. Silakan periksa status dokumen.'
                    : rawMessage;

                return {
                    status: xhr.status,
                    message: normalizedMessage
                };
            }

            function safeReload(dt) {
                if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
                    dt.ajax.reload();
                }
            }

            function resetSubmitState() {
                $submitDataBtn.removeData('id');
                $submitDataBtn.prop('disabled', false);
            }

            $(document).on('click', '.submit_data', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                const id = $(this).val();
                if (!id) {
                    notification({
                        status: 400,
                        message: 'ID dokumen tidak valid.'
                    });
                    return;
                }
                $submitDataBtn.data('id', id);
                $submitDataModal.modal('show');
            });

            $submitDataBtn.on('click', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const id = $(this).data('id');
                if (!id) return;
                $submitDataBtn.prop('disabled', true);
                const formData = new FormData();
                formData.append('id', id);
                $.ajax({
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    data: formData,
                    processData: false,
                    contentType: false,
                    url: '{{ $url }}',
                    success: function(response) {
                        notification(response);
                        $submitDataModal.modal('hide');
                        safeReload(typeof mainTable !== 'undefined' ? mainTable : null);
                    },
                    error: function(xhr) {
                        notification(submitNotificationPayload(xhr, 'Gagal submit data'));
                    },
                    complete: function() {
                        resetSubmitState();
                    }
                });
            });

            $submitDataModal.on('hidden.bs.modal', function() {
                resetSubmitState();
            });

        })
    </script>
