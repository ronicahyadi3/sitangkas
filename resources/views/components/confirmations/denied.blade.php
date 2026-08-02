<div class="modal fade" id="modal-denied" tabindex="-1" role="dialog" aria-labelledby="modal-title-denied" aria-hidden="true">
    <div class="modal-dialog modal-danger modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modal-title-denied">Konfirmasi Penolakan Dokumen</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="py-3 text-center">
                    <i class="fa-solid fa-bell fa-4x"></i>
                    <h4 class="text-gradient text-danger mt-4">Apakah Anda ingin menolak?</h4>
                    <p>Dokumen akan ditandai <b>DITOLAK</b> dan proses tidak dapat dilanjutkan sebelum diperbaiki.</p>
                </div>
                <div class="form-group text-center card" id="sp2d-form">
                    <div class="row card-body">
                        <div class="col-lg-6 p-0">
                            <div class="custom-switch custom-control form-check-inline">
                                <input type="radio" class="custom-control-input" name="option-denied" value="0"
                                    id="sp2d">
                                <label class="custom-control-label text-dark" for="sp2d">Tolak SP2D</label>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="custom-switch custom-control form-check-inline">
                                <input type="radio" class="custom-control-input" name="option-denied" value="1"
                                    id="spp">
                                <label class="custom-control-label text-dark" for="spp">Tolak Pengajuan</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="notes" class="form-control-label text-white">Catatan :</label>
                    <textarea class="form-control" id="notes" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="denied-button">Ok, Lanjutkan</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        const modalEl = document.getElementById('modal-denied');
        const deniedModal = bootstrap.Modal.getOrCreateInstance(modalEl);

        const $modal = $('#modal-denied');
        const $notes = $('#notes');
        const $sp2dForm = $('#sp2d-form');
        const $radioSp2d = $('#sp2d');
        const $radioSpp = $('#spp');
        const $confirmBtn = $('#denied-button');

        let deniedPayload = null;

        function resetDeniedForm() {
            $notes.val('');
            $modal.find('input[name="option-denied"]').prop('checked', false);
            $confirmBtn.prop('disabled', false);
        }

        function setDeniedMode(isSimpleDenied) {
            if (isSimpleDenied) {
                $radioSp2d.prop('disabled', true);
                $radioSpp.prop('disabled', true);
                $sp2dForm.hide();
            } else {
                $radioSp2d.prop('disabled', false);
                $radioSpp.prop('disabled', false);
                $sp2dForm.show();
            }
        }

        function safeReload(dt) {
            if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
                dt.ajax.reload();
            }
        }

        $(document).on('click', '.denied, .denied_sp2d', function(e) {
            e.preventDefault();
            if (window.readOnlyUI?.guardAction()) return;

            const $btn = $(this);
            const isSimpleDenied = $btn.hasClass('denied');

            resetDeniedForm();
            setDeniedMode(isSimpleDenied);

            deniedPayload = {
                id: $btn.data('id') ?? $btn.val(),
                src_type: $btn.data('type'),
                payment_type: $btn.data('payment')
            };

            if (!deniedPayload.id) {
                notification({
                    status: 400,
                    message: 'ID dokumen tidak valid.'
                });
                deniedPayload = null;
                return;
            }

            deniedModal.show();
        });

        $confirmBtn.on('click', function() {
            if (window.readOnlyUI?.guardAction()) return;
            if (!deniedPayload) {
                notification({
                    status: 400,
                    message: 'Data dokumen tidak ditemukan. Silakan ulangi proses penolakan.'
                });

                $confirmBtn.prop('disabled', false);
                deniedModal.hide();
                return;
            }

            const isOptionVisible = $sp2dForm.is(':visible');
            const selectedOption = $modal.find('input[name="option-denied"]:checked').val();

            if (isOptionVisible && (selectedOption === undefined)) {
                notification({
                    status: 422,
                    message: 'Pilih opsi penolakan terlebih dahulu.'
                });
                return;
            }

            $confirmBtn.prop('disabled', true);

            const formData = new FormData();
            formData.append('id', deniedPayload.id);
            formData.append('notes', $notes.val());
            formData.append('src_type', deniedPayload.src_type);
            formData.append('payment_type', deniedPayload.payment_type);

            if (isOptionVisible) {
                formData.append('option_denied', selectedOption);
            }

            $.ajax({
                    type: "POST",
                    url: "{{ route('document.denied') }}",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: formData,
                    dataType: "json",
                    processData: false,
                    contentType: false
                })
                .done(function(response) {
                    notification(response);
                    safeReload(mainTable);
                    safeReload(formTable);
                    resetDeniedForm();
                    deniedPayload = null;
                    deniedModal.hide();
                })
                .fail(function(xhr) {
                    $confirmBtn.prop('disabled', false);
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message ??
                            'Terjadi kesalahan saat memproses data'
                    });
                });
        });

        $modal.on('hidden.bs.modal', function() {
            resetDeniedForm();
            deniedPayload = null;
        });
    });
</script>
