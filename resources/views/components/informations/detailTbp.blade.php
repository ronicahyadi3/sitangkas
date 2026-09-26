<div class="modal fade" id="detailTbpModal" tabindex="-1" aria-labelledby="detailTbpModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="card card-plain">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <div>
                            <h3 class="font-weight-bolder text-info text-gradient" id="detailTbpModalTitle">
                                Daftar Dokumen TBP
                            </h3>
                            <p class="mb-0">Informasi daftar TBP yang terhubung pada dokumen ini</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="z-index: 2;">x
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-flush" id="detailTbpTable"
                                data-esign-refresh-on-complete>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="align-middle text-center">#</th>
                                        <th class="align-middle text-center">Tanggal</th>
                                        <th class="align-middle text-center">Nomor TBP</th>
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
    let detailTbpTable = null;
    let detailTbpDocumentId = null;
    let detailTbpModal = null;
    let detailTbpRequestXhr = null;

    function initDetailTbpTable() {
        if (detailTbpTable) return;

        detailTbpTable = new DataTable('#detailTbpTable', {
            ajax: {
                url: "{{ $url ?? '' }}",
                type: 'GET',
                data: function(d) {
                    d.id = detailTbpDocumentId;
                },
                beforeSend: function(xhr) {
                    if (detailTbpRequestXhr && detailTbpRequestXhr.readyState !== 4) {
                        detailTbpRequestXhr.abort();
                    }
                    detailTbpRequestXhr = xhr;
                },
                error: function(xhr) {
                    if (xhr?.statusText === 'abort') {
                        return;
                    }
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message || 'Gagal memuat daftar TBP.'
                    });
                }
            },
            processing: true,
            serverSide: true,
            searching: false,
            paging: false,
            ordering: false,
            deferRender: true,
            columns: [{
                    data: 'DT_RowIndex',
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                },
                {
                    data: 'created_at',
                    className: 'text-wrap text-center',
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
                    data: 'nomor',
                    className: 'text-wrap text-start',
                    orderable: false,
                    searchable: false,
                    defaultContent: '-',
                },
                {
                    data: 'document_contract',
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    defaultContent: '-',
                    render: function(data, type, row) {
                        return typeof window.renderDocumentDetailActions === 'function'
                            ? window.renderDocumentDetailActions(data, type, row)
                            : data;
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
        const modalEl = document.getElementById('detailTbpModal');
        if (modalEl) {
            detailTbpModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            modalEl.addEventListener('hidden.bs.modal', function() {
                detailTbpDocumentId = null;
                if (detailTbpRequestXhr && detailTbpRequestXhr.readyState !== 4) {
                    detailTbpRequestXhr.abort();
                }
            });
        }
    });

    $(document).off('click.detailTbp').on('click.detailTbp', '.detail_tbp', function(e) {
        e.preventDefault();

        detailTbpDocumentId = $(this).val() || $(this).data('id');
        if (!detailTbpDocumentId) {
            notification({
                status: 400,
                message: 'Data TBP tidak valid.'
            });
            return;
        }

        initDetailTbpTable();

        if (detailTbpModal) {
            detailTbpModal.show();
        }

        if (detailTbpTable) {
            detailTbpTable.ajax.reload(null, false);
        }
    });
</script>
