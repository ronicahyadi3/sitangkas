<div class="modal fade" id="tteDocumentDetailModal" tabindex="-1" aria-labelledby="tteDocumentDetailTitle"
    aria-hidden="true" data-document-detail-modal data-document-detail-table="#tteDocumentTable">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="card card-plain">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <div>
                            <h3 class="font-weight-bolder text-info text-gradient" id="tteDocumentDetailTitle">
                                Detail Dokumen Tertandatangan
                            </h3>
                            <p class="mb-0">Informasi data dokumen yang harus ditandatangani</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="z-index: 2;">x
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-flush" id="tteDocumentTable"
                                data-esign-refresh-on-complete>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="align-middle text-center">#</th>
                                        <th class="align-middle text-center">Tanggal Update</th>
                                        <th class="align-middle text-center">Tipe File</th>
                                        <th class="align-middle text-center">Status</th>
                                        <th class="align-middle text-center">Aksi</th>
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
    let tteDocumentTable = null;
    let currentDocumentId = null;
    let tteDocumentDetailModal = null;
    let detailRequestXhr = null;

    function initTteDocumentTable() {
        if (tteDocumentTable) return;

        tteDocumentTable = new DataTable('#tteDocumentTable', {
            ajax: {
                url: "{{ route('document.detail') }}",
                type: 'GET',
                data: function(d) {
                    d.id = currentDocumentId;
                },
                beforeSend: function(xhr) {
                    if (detailRequestXhr && detailRequestXhr.readyState !== 4) {
                        detailRequestXhr.abort();
                    }
                    detailRequestXhr = xhr;
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
            serverSide: false,
            searching: false,
            paging: false,
            ordering: false,
            deferRender: true,
            columns: [{
                    data: "DT_RowIndex",
                    className: "text-wrap text-center",
                },
                {
                    data: "updated_at",
                    className: "text-wrap text-center",
                    orderable: false,
                    searchable: false,
                    render: function(data) {
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
                    data: "src_type",
                    className: "text-wrap text-center",
                    orderable: false,
                    searchable: false
                },
                {
                    data: "document_contract",
                    className: "text-center",
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return typeof window.renderDocumentDetailStatus === 'function'
                            ? window.renderDocumentDetailStatus(data, type, row)
                            : '-';
                    }
                },
                {
                    data: "document_contract",
                    className: "text-center",
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return typeof window.renderDocumentDetailActions === 'function'
                            ? window.renderDocumentDetailActions(data, type, row)
                            : '-';
                    }
                },
            ],
            language: {
                paginate: {
                    previous: "<i class='fas fa-angle-left'></i>",
                    next: "<i class='fas fa-angle-right'></i>"
                }
            }
        });
    }

    $(function() {
        const modalEl = document.getElementById('tteDocumentDetailModal');
        if (modalEl) {
            tteDocumentDetailModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            modalEl.addEventListener('hidden.bs.modal', function() {
                if (modalEl.dataset.modalCoordinatorSuspended === 'true') {
                    return;
                }

                currentDocumentId = null;
                if (detailRequestXhr && detailRequestXhr.readyState !== 4) {
                    detailRequestXhr.abort();
                }
            });
        }
    });

    $(document).off('click.tteDetail').on('click.tteDetail', '.show-document[data-id]', function(e) {
        e.preventDefault();

        const docId = $(this).data('id');
        if (!docId) {
            notification({
                status: 400,
                message: 'Data dokumen tidak valid.'
            });
            return;
        }

        if (currentDocumentId === docId && tteDocumentDetailModal) {
            tteDocumentDetailModal.show();
            return;
        }

        currentDocumentId = docId;
        initTteDocumentTable();

        if (tteDocumentDetailModal) {
            tteDocumentDetailModal.show();
        }

        if (tteDocumentTable) {
            tteDocumentTable.ajax.reload(null, false);
        }
    });
</script>
