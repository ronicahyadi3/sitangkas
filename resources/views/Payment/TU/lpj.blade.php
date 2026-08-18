@extends('layouts.app')
@php
    $activePosition = app(\App\Services\User\ActivePositionService::class)->get();
    $activeJabatanId = (int) ($activePosition?->jabatan_id ?? 0);
    $isAuditor = $activeJabatanId === 13;
    $canManagePackage = in_array($activeJabatanId, [9, 10], true);
    $canViewPackage = $canManagePackage || $isAuditor;
@endphp

@section('content')
    @if ($isAuditor)
        <div class="card mb-4 border">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-xs text-uppercase text-muted fw-bold">Mode Audit</div>
                        <div class="fw-bold text-dark">Akses baca-saja lintas perangkat daerah pada tahun aktif {{ session('tahun_aktif') }}</div>
                        <div class="text-sm text-secondary">Paket LPJ TU dan dokumen turunannya dapat dilihat tanpa fitur kelola atau submit.</div>
                    </div>
                    <span class="badge bg-gradient-secondary">Read Only Audit</span>
                </div>
            </div>
        </div>
    @endif

    <div id="mainTable"></div>

    @if ($canViewPackage)
        <div class="modal fade" id="managePackageModal" tabindex="-1" aria-labelledby="managePackageModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-body p-0">
                        <div class="card card-plain">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <div>
                                    <h3 class="font-weight-bolder text-info text-gradient" id="managePackageModalLabel">
                                        {{ $canManagePackage ? 'Kelola Dokumen LPJ TU' : 'Dokumen LPJ TU' }}
                                    </h3>
                                    <p class="mb-0">
                                        {{ $canManagePackage ? 'Unggah dan perbarui dokumen STS, LPJ, dan TBP pada paket SP2D yang sudah selesai.' : 'Tinjau dokumen STS, LPJ, dan TBP pada paket SP2D secara baca-saja.' }}
                                    </p>
                                </div>
                                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <div class="card h-100 border">
                                            <div class="card-body">
                                                <h6 class="mb-3">Ringkasan Paket</h6>
                                                <div class="small text-secondary mb-1">Unit Kerja</div>
                                                <div class="fw-bold mb-3" id="packageUnitKerja">-</div>
                                                <div class="small text-secondary mb-1">Nomor SP2D</div>
                                                <div class="fw-bold mb-3 text-wrap" id="packageNomorSp2d">-</div>
                                                <div class="small text-secondary mb-1">Nomor SPP</div>
                                                <div class="fw-bold text-wrap" id="packageNomorSpp">-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="card h-100 border">
                                            <div class="card-body">
                                                @if ($canManagePackage)
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="mb-0" id="childFormTitle">Unggah Dokumen Paket</h6>
                                                        <button type="button" class="btn btn-sm btn-light mb-0" id="resetChildForm">
                                                            Reset Form
                                                        </button>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label for="src_type" class="form-label">Tipe Dokumen</label>
                                                            <select id="src_type" class="form-select">
                                                                <option value="">Pilih Tipe</option>
                                                                <option value="STS">STS</option>
                                                                <option value="LPJ">LPJ</option>
                                                                <option value="TBP">TBP</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <label for="nomor_document" class="form-label">Nomor Dokumen</label>
                                                            <input type="text" id="nomor_document" class="form-control"
                                                                placeholder="Masukkan nomor dokumen">
                                                        </div>
                                                        <div class="col-12">
                                                            <label for="file_document" class="form-label">File PDF</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text"><i class="fa-solid fa-file-pdf"></i></span>
                                                                <input type="file" id="file_document" class="form-control"
                                                                    accept="application/pdf">
                                                            </div>
                                                            <small class="form-text">Nama File:
                                                                <span id="file_document_name" class="font-weight-bold">Belum ada file yang dipilih</span>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="text-end mt-4">
                                                        <button type="button" class="btn btn-round bg-gradient-info mb-0"
                                                            id="saveChildDocument">
                                                            Simpan Dokumen
                                                        </button>
                                                    </div>
                                                @else
                                                    <div class="h-100 d-flex align-items-center">
                                                        <div>
                                                            <h6 class="mb-2">Mode Audit Baca-Saja</h6>
                                                            <p class="text-sm text-secondary mb-0">
                                                                Auditor dapat meninjau dokumen paket dan riwayatnya, tetapi tidak dapat menambah, mengubah, menghapus, submit, atau menolak paket.
                                                            </p>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h6 class="mb-3">Dokumen Dalam Paket</h6>
                                    <div id="documentsTable"></div>
                                </div>
                            </div>
                            <div class="card-footer text-center pt-0 px-lg-2 px-1">
                                <p class="mb-4 text-sm mx-auto">
                                    Butuh bantuan?
                                    <a href="https://wa.me/6282131701177" target="_blank"
                                        class="text-info text-gradient font-weight-bold">Hubungi kami (WhatsApp)</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('additionals')
    @include('components.informations.pdfview')
    @include('components.informations.detail')
    @include('components.informations.history')
    @unless ($isAuditor)
        @include('components.esign.esign')
        @include('components.confirmations.submit', [
            'url' => route('tu.lpj.submit'),
        ])
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan TU',
        'title' => 'Dokumen LPJ Pasca SP2D',
        'url' => route('tu.lpj.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [],
        'columns' => [
            [
                'data' => 'DT_RowIndex',
                'class' => 'text-center',
                'orderable' => false,
                'searchable' => false,
                'title' => 'No',
            ],
            ['data' => 'updated_at', 'class' => 'text-center text-wrap', 'title' => 'Tanggal'],
            ['data' => 'unit_kerja', 'class' => 'text-start text-wrap', 'title' => 'Unit Kerja'],
            ['data' => 'nomor_spp', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPP', 'render' => 'renderNomor'],
            ['data' => 'nomor_spm', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPM', 'render' => 'renderNomor'],
            ['data' => 'nomor_sp2d', 'class' => 'text-start text-wrap', 'title' => 'Nomor SP2D', 'render' => 'renderNomor'],
            [
                'data' => 'status_button',
                'class' => 'text-center',
                'searchable' => false,
                'orderable' => false,
                'title' => 'Status',
            ],
            [
                'data' => 'action',
                'class' => 'text-center',
                'searchable' => false,
                'orderable' => false,
                'title' => 'Aksi/Opsi',
            ],
        ],
    ])

    @if ($canViewPackage)
        @include('components.informations.Table', [
            'url' => route('tu.lpj.documents.json'),
            'nameTable' => 'documentsTable',
            'jabatanAccessAdd' => [],
            'data' => [
                'reference' => 'Reference',
            ],
            'columns' => [
                [
                    'data' => 'DT_RowIndex',
                    'class' => 'text-center',
                    'orderable' => false,
                    'searchable' => false,
                    'title' => 'No',
                ],
                ['data' => 'updated_at', 'class' => 'text-center text-wrap', 'title' => 'Tanggal'],
                ['data' => 'src_type', 'class' => 'text-center text-wrap', 'title' => 'Tipe'],
                ['data' => 'nomor', 'class' => 'text-start text-wrap', 'title' => 'Nomor Dokumen', 'render' => 'renderNomor'],
                [
                    'data' => 'status_button',
                    'class' => 'text-center',
                    'searchable' => false,
                    'orderable' => false,
                    'title' => 'Status',
                ],
                [
                    'data' => 'action',
                    'class' => 'text-center',
                    'searchable' => false,
                    'orderable' => false,
                    'title' => 'Aksi/Opsi',
                ],
            ],
        ])
    @endif

    @if ($canViewPackage)
    <script>
        let currentPackageReference = null;
        let currentEditingDocumentId = null;
        let managePackageModalInstance = null;

        function safeReload(dt) {
            if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
                dt.ajax.reload(null, false);
            }
        }

        function bindLpjFileName() {
            $('#file_document').off('change').on('change', function() {
                const fileName = this.files?.[0]?.name || 'Belum ada file yang dipilih';
                $('#file_document_name').text(fileName);
            });
        }

        function resetChildForm() {
            currentEditingDocumentId = null;
            $('#childFormTitle').text('Unggah Dokumen Paket');
            $('#src_type').val('');
            $('#nomor_document').val('');
            $('#file_document').val('');
            $('#file_document_name').text('Belum ada file yang dipilih');
            $('#saveChildDocument').text('Simpan Dokumen').prop('disabled', false);
        }

        function openPackageManager($button) {
            currentPackageReference = $button.data('reference');
            if (!currentPackageReference) {
                notification({
                    status: 400,
                    message: 'Referensi paket tidak valid.'
                });
                return;
            }

            $('#packageUnitKerja').text($button.data('unit') || '-');
            $('#packageNomorSp2d').text($button.data('sp2d-number') || '-');
            $('#packageNomorSpp').text($button.data('spp-number') || '-');

            resetChildForm();
            DT.set('documentsTable', 'Reference', currentPackageReference);
            safeReload(documentsTable);
            managePackageModalInstance.show();
        }

        function buildChildFormData() {
            if (!currentPackageReference) {
                notification({
                    status: 400,
                    message: 'Paket LPJ belum dipilih.'
                });
                return null;
            }

            const srcType = $('#src_type').val();
            const nomor = $('#nomor_document').val();
            const fileInput = document.getElementById('file_document');
            const file = fileInput?.files?.[0];

            if (!srcType) {
                notification({
                    status: 422,
                    message: 'Tipe dokumen wajib dipilih.'
                });
                return null;
            }

            if (!nomor) {
                notification({
                    status: 422,
                    message: 'Nomor dokumen wajib diisi.'
                });
                return null;
            }

            if (!currentEditingDocumentId && !file) {
                notification({
                    status: 422,
                    message: 'File PDF wajib diunggah.'
                });
                return null;
            }

            const formData = new FormData();
            formData.append('reference', currentPackageReference);
            formData.append('src_type', srcType);
            formData.append('nomor_document', nomor);
            if (file) {
                formData.append('file_document', file);
            }

            return formData;
        }

        async function saveChildDocument() {
            const formData = buildChildFormData();
            if (!formData) return;

            const saveButton = $('#saveChildDocument');
            const url = currentEditingDocumentId
                ? @json(route('tu.lpj.update', ':id')).replace(':id', currentEditingDocumentId)
                : @json(route('tu.lpj.store'));

            $.ajax({
                type: 'POST',
                url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: formData,
                processData: false,
                contentType: false,
                beforeSend() {
                    saveButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Proses...');
                },
                success(response) {
                    notification(response);
                    resetChildForm();
                    safeReload(typeof documentsTable !== 'undefined' ? documentsTable : null);
                    safeReload(mainTable);
                },
                error(xhr) {
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message || 'Gagal menyimpan dokumen LPJ.'
                    });
                },
                complete() {
                    saveButton.prop('disabled', false).text(currentEditingDocumentId ? 'Update Dokumen' : 'Simpan Dokumen');
                }
            });
        }

        async function loadChildDocumentForEdit(id) {
            $.ajax({
                type: 'GET',
                url: @json(route('tu.lpj.edit', ':id')).replace(':id', id),
                dataType: 'json',
                success(response) {
                    currentEditingDocumentId = id;
                    $('#childFormTitle').text('Perbarui Dokumen Paket');
                    $('#src_type').val(response.data.src_type);
                    $('#nomor_document').val(response.data.nomor);
                    $('#file_document').val('');
                    $('#file_document_name').text('Kosongkan jika file tidak diganti');
                    $('#saveChildDocument').text('Update Dokumen');
                },
                error(xhr) {
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message || 'Gagal memuat dokumen LPJ.'
                    });
                }
            });
        }

        async function deleteChildDocument(id) {
            const result = await Swal.fire({
                title: 'Hapus dokumen?',
                text: 'Dokumen akan dikeluarkan dari paket aktif.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal',
            });

            if (!result.isConfirmed) return;

            $.ajax({
                type: 'POST',
                url: @json(route('tu.lpj.delete', ':id')).replace(':id', id),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success(response) {
                    notification(response);
                    resetChildForm();
                    safeReload(typeof documentsTable !== 'undefined' ? documentsTable : null);
                    safeReload(mainTable);
                },
                error(xhr) {
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message || 'Gagal menghapus dokumen LPJ.'
                    });
                }
            });
        }

        async function denyPackage(referenceHash) {
            const result = await Swal.fire({
                title: 'Tolak paket LPJ?',
                text: 'Semua dokumen aktif dalam paket akan ditandai ditolak.',
                icon: 'warning',
                input: 'textarea',
                inputLabel: 'Catatan penolakan',
                inputPlaceholder: 'Tuliskan alasan penolakan...',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Catatan penolakan wajib diisi.';
                    }
                    return null;
                },
                showCancelButton: true,
                confirmButtonText: 'Ya, tolak',
                cancelButtonText: 'Batal',
            });

            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('id', referenceHash);
            formData.append('notes', result.value);

            $.ajax({
                type: 'POST',
                url: @json(route('tu.lpj.deny')),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: formData,
                processData: false,
                contentType: false,
                success(response) {
                    notification(response);
                    safeReload(mainTable);
                    safeReload(typeof documentsTable !== 'undefined' ? documentsTable : null);
                },
                error(xhr) {
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON?.message || 'Gagal menolak paket LPJ.'
                    });
                }
            });
        }

        $(function() {
            bindLpjFileName();

            const modalEl = document.getElementById('managePackageModal');
            if (modalEl) {
                managePackageModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalEl.addEventListener('hidden.bs.modal', function() {
                    currentPackageReference = null;
                    resetChildForm();
                });
            }

            $(document).on('click', '.manage_package', function(e) {
                e.preventDefault();
                openPackageManager($(this));
            });

            @if ($canManagePackage)
                $(document).on('click', '.edit_child', function(e) {
                    e.preventDefault();
                    loadChildDocumentForEdit($(this).val());
                });

                $(document).on('click', '.delete_child', function(e) {
                    e.preventDefault();
                    deleteChildDocument($(this).val());
                });

                $(document).on('click', '.deny_package', function(e) {
                    e.preventDefault();
                    denyPackage($(this).val());
                });

                $('#resetChildForm').on('click', function() {
                    resetChildForm();
                });

                $('#saveChildDocument').on('click', function() {
                    saveChildDocument();
                });
            @endif
        });
    </script>
    @endif
@endsection
