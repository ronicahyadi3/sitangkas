@props(['urlStore', 'urlUpdate', 'urlEdit', 'submitId' => 'submit', 'modalId' => 'add'])

<script>
    (function() {

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        const modal = new bootstrap.Modal(
            document.getElementById(@json($modalId))
        );

        const submitBtn = $('#' + @json($submitId));

        function buildAjaxErrorPayload(xhr, fallbackMessage = 'Terjadi kesalahan') {
            const status = Number(xhr?.status) || 500;
            const errors = xhr?.responseJSON?.errors;
            let message = '';

            if (errors && typeof errors === 'object') {
                const firstFieldErrors = Object.values(errors).find(value => {
                    if (Array.isArray(value)) {
                        return value.length > 0;
                    }

                    return value !== null && value !== undefined && String(value).trim() !== '';
                });

                if (Array.isArray(firstFieldErrors) && firstFieldErrors.length > 0) {
                    message = String(firstFieldErrors[0]).trim();
                } else if (firstFieldErrors !== undefined && firstFieldErrors !== null) {
                    message = String(firstFieldErrors).trim();
                }
            }

            if (!message) {
                message = xhr?.responseJSON?.message ||
                    xhr?.statusText ||
                    fallbackMessage;
            }

            return {
                status,
                message
            };
        }

        function submitData(url, extraValidationCallback) {
            if (window.readOnlyUI?.guardAction()) return;

            if (typeof extraValidationCallback === 'function') {
                if (!extraValidationCallback()) return;
            }

            const formData = buildFormData();
            if (!formData) return;

            $.ajax({
                type: "POST",
                url,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                data: formData,
                processData: false,
                contentType: false,
                beforeSend() {
                    submitBtn
                        .prop('disabled', true)
                        .html('<i class="fa fa-spinner fa-spin"></i> Proses...');
                },
                success(response) {
                    notification?.(response);
                    mainTable?.ajax.reload(null, false);
                    if (typeof resetRekeningSession === 'function') {
                        resetRekeningSession();
                    }
                    modal.hide();
                },
                error(xhr) {
                    notification?.(buildAjaxErrorPayload(xhr));
                },
                complete() {
                    submitBtn.prop('disabled', false).html('Simpan');
                }
            });
        }

        window.FormCrud = {
            store(extraValidation) {
                submitData(@json($urlStore), extraValidation);
            },

            update(id, extraValidation) {
                submitData(
                    @json($urlUpdate).replace(':id', id),
                    extraValidation
                );
            },

            editHandler(callback) {
                $(document).on('click', '.edit_data', function(e) {
                    e.preventDefault();
                    if (window.readOnlyUI?.guardAction()) return;
                    resetFormAdd?.();

                    const id = $(this).val();

                    $.ajax({
                        type: 'GET',
                        url: @json($urlEdit).replace(':id', id),
                        dataType: 'json',
                        success(res) {
                            if (res.status !== 200 || !res.data) {
                                notification?.({
                                    status: 404,
                                    message: 'Data tidak ditemukan'
                                });
                                return;
                            }

                            callback(res, id);
                            modal.show();
                        },
                        error(xhr) {
                            notification?.(buildAjaxErrorPayload(xhr));
                        }
                    });
                });
            },

            showModal() {
                if (window.readOnlyUI?.guardAction()) return;
                modal.show();
            }
        };

    })();
</script>
