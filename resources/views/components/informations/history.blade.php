<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="card card-plain">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <div>
                            <h3 class="font-weight-bolder text-info text-gradient" id="historyModalTitle">
                                Detail Riwayat Dokumen
                            </h3>
                            <p class="mb-0">Informasi perubahan data dokumen yang telah dilakukan</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="z-index: 2;">x
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">

                            <table class="table table-flush" id="historyTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="align-middle text-center">#</th>
                                        <th class="align-middle text-center">Tanggal</th>
                                        <th class="align-middle text-center">Aksi/Pengirim</th>
                                        <th class="align-middle text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let historyTable = null;
    let documentIdHistory = null;
    let historyModal = null;
    let historyRequestXhr = null;

    function initHistoryTable() {
        if (historyTable) {
            return false;
        }

        historyTable = new DataTable('#historyTable', {
            ajax: {
                url: "{{ route('document.history') }}",
                type: "POST",
                dataType: "json",
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: function(d) {
                    d.id = documentIdHistory;
                    return d;
                },
                beforeSend: function(xhr) {
                    if (historyRequestXhr && historyRequestXhr.readyState !== 4) {
                        historyRequestXhr.abort();
                    }
                    historyRequestXhr = xhr;
                },
                error: function(xhr) {
                    if (xhr?.statusText === 'abort') {
                        return;
                    }
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message ||
                            'Terjadi kesalahan saat memproses pengambilan data.'
                    });
                }
            },
            processing: true,
            serverSide: true,
            searching: false,
            columns: [{
                    data: "DT_RowIndex",
                    className: "text-center",
                    orderable: false,
                    searchable: false
                },
                {
                    data: "created_at",
                    className: "text-wrap text-center",
                    orderable: false,
                    searchable: false,
                    "render": function(data, type, row, meta) {
                        if (!data) return '-';

                        return new Date(data).toLocaleString('id-ID', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false,
                        });
                    }
                },
                {
                    data: "pengirim",
                    className: "text-wrap text-center",
                    orderable: false,
                    searchable: false
                },
                {
                    data: "status",
                    className: "text-wrap text-center",
                    orderable: false,
                    searchable: false
                },
            ],
            language: {
                paginate: {
                    previous: "<i class='fas fa-angle-left'></i>",
                    next: "<i class='fas fa-angle-right'></i>"
                }
            }
        });

        return true;
    }

    $(function() {
        const modalEl = document.getElementById('historyModal');
        if (modalEl) {
            historyModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            modalEl.addEventListener('hidden.bs.modal', function() {
                documentIdHistory = null;
                if (historyRequestXhr && historyRequestXhr.readyState !== 4) {
                    historyRequestXhr.abort();
                }
            });
        }
    });

    $(document).off('click.history').on('click.history', '.history_data', function(e) {
        e.preventDefault();
        documentIdHistory = $(this).val();
        if (!documentIdHistory) {
            notification({
                status: 400,
                message: 'Data riwayat dokumen tidak valid.'
            });
            return;
        }

        if (historyTable && documentIdHistory === historyTable.ajax.params()?.id && historyModal) {
            historyModal.show();
            return;
        }
        if (historyModal) {
            historyModal.show();
        }

        const initialized = initHistoryTable();
        if (!initialized) {
            historyTable.ajax.reload(null, false);
        }
    });
</script>
