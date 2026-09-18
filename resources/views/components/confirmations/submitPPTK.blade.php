    <div class="modal fade" id="SubmitToPPTK" tabindex="-1" role="dialog" aria-labelledby="submitPptkTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <div class="card card-plain">
                        <div class="card-header pb-0 d-flex justify-content-between">
                            <div>
                                <h3 class="font-weight-bolder text-info text-gradient" id="submitPptkTitle">Notifikasi submit</h3>
                                <p class="mb-0">Pilih pengguna pptk yang akan anda kirim</p>
                            </div>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"
                                style="z-index: 2;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="card-body pb-3">
                            <form role="form text-left">
                                <label>Nama PPTK </label>
                                <div class="input-group mb-3">
                                    <select class="form-control" id="user_pptk" name="user_pptk" required
                                        autocomplete="user_pptk">
                                        <option value="">- Pilih User</option>
                                    </select>
                                </div>

                                <div class="text-center">
                                    <button type="button"
                                        class="btn bg-gradient-info btn-lg btn-rounded w-100 mt-4 mb-0"
                                        id="submit_btn_pptk">Submit</button>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center pt-0 px-sm-4 px-1">
                            <p class="mb-4 mx-auto">
                                Butuh bantuan?
                                <a href="https://wa.me/6282131701177"
                                    class="text-info text-gradient font-weight-bold">Hubungi
                                    admin</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function() {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const $userPptk = $('#user_pptk');
            const $submitPptkModal = $('#SubmitToPPTK');
            const $submitPptkBtn = $('#submit_btn_pptk');
            let pptkLoaded = false;

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

            $userPptk.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Pilih PPTK',
                dropdownParent: $submitPptkModal,
                allowClear: true
            });

            function loadPptkOptions() {
                if (pptkLoaded) return;

                $.ajax({
                    type: 'GET',
                    url: "{{ route('payment.options.pptk') }}",
                    dataType: 'json',
                    success: function(data) {
                        const options = data.map(item => new Option(item.nama, item.id, false, false));
                        $userPptk.append(options).trigger('change.select2');
                        pptkLoaded = true;
                    },
                    error: function(xhr) {
                        notification({
                            status: xhr.status,
                            message: xhr.responseJSON?.message || 'Gagal memuat data PPTK'
                        });
                    }
                });
            }

            function safeReload(dt) {
                if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
                    dt.ajax.reload(null, false);
                }
            }

            function resetSubmitPptkState() {
                $submitPptkBtn.prop('disabled', false).removeData('id');
                $userPptk.val(null).trigger('change');
            }

            // OPEN MODAL
            $(document).on('click', '.submit_pptk', function(e) {
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
                loadPptkOptions();

                $submitPptkBtn.data('id', id);

                resetSubmitPptkState();
                $submitPptkBtn.data('id', id);

                $submitPptkModal.modal('show');
            });

            // SUBMIT
            $submitPptkBtn.on('click', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const id = $(this).data('id');
                const userTo = $userPptk.val();

                if (!id || !userTo) {
                    notification({
                        status: 422,
                        message: 'Pilih PPTK terlebih dahulu'
                    });
                    return;
                }

                $submitPptkBtn.prop('disabled', true);

                const formData = new FormData();
                formData.append('users_to', userTo);
                formData.append('id', id);

                $.ajax({
                    type: 'POST',
                    url: '{{ $url }}',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        notification(response);

                        $submitPptkModal.modal('hide');

                        safeReload(typeof mainTable !== 'undefined' ? mainTable : null);
                    },
                    error: function(xhr) {
                        notification(submitNotificationPayload(xhr, 'Gagal submit ke PPTK'));
                    },
                    complete: function() {
                        $submitPptkBtn.prop('disabled', false);
                    }
                });
            });

            $submitPptkModal.on('hidden.bs.modal', function() {
                resetSubmitPptkState();
            });
        });
    </script>
