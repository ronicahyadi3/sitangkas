    <div class="modal fade" id="edit-modal-billing" style="z-index: 1049;" tabindex="-1" role="dialog"
        aria-labelledby="modelTitleId" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header pb-0">
                    <h5 class="modal-title">Unggah Dokumen Billing</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">x
                    </button>
                </div>
                <div class="m-1" id="progres-loadings">
                    <div id="overlays" onclick="off()">
                        <div class="w-100 d-flex justify-content-center align-items-center">
                            <div class="spinner"></div>
                            <div class="d-grid gap-3" style="padding-top: 9rem;">
                                <div class="font-weight-bold text-white h1 text-center" style="font-size: 2rem;"
                                    id="progress-values">23%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-4 ">
                        <div class="input-group input-group-merge input-group-alternative">
                            <div class="input-group-prepend">
                                <span class="input-group-text form-control-label"><i
                                        class="fas fa-file-upload">&nbsp;Billing dan NTPN (Bukti Bayar)</i></span>
                            </div>
                            <div class="form-label-group flex-fill m-0">
                                <div class="custom-file">
                                    <input class="file_billing_update form-control" type="file"
                                        id="file_billing_update" accept="application/pdf" name="file_billing_update">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="submit_billing">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).on('click', '.edit_billing', function() {
            $('#edit-modal-billing').modal('show');
            $('#overlays').hide();
            document.getElementById('submit_billing').setAttribute("onClick", "SubmitBilling('" +
                $(this).val() +
                "','" + $(this).data("payment") + "')");
            $('#file_billing_update').val('');
        });

        function SubmitBilling($id, $id2) {
            var file_billing = $('#file_billing_update').prop('files')[0];
            if (file_billing) {
                var formData = new FormData();
                formData.append('id', $id);
                formData.append('payment_type', $id2);
                formData.append('billing', file_billing);
                formData.append('_token', '{{ csrf_token() }}');
                $.ajax({
                    url: '/olah_dokumen/update-billing',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    beforeSend: function() {
                        $('#overlays').show();
                    },
                    success: function(response) {
                        $('#overlays').hide();
                        notification(response)
                        datatables.ajax.reload();
                        if (typeof list_spp !== "undefined") {
                            list_spp.ajax.reload();
                        }
                        if (typeof list_tbp !== "undefined") {
                            list_tbp.ajax.reload();
                        }
                        if (typeof list_data !== "undefined") {
                            list_data.ajax.reload();
                        }
                        $('#edit-modal-billing').modal('hide');
                    },
                    error: function(xhr, status, error) {
                        $('#overlays').hide();
                        notification(response);
                    }
                });
            } else {
                alert('Silakan pilih file Billing untuk diunggah.');
            }
        };
    </script>
