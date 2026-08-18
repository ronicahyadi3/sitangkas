@extends('layouts.app')
@php
    $activePosition = app(\App\Services\User\ActivePositionService::class)->get();
    $isAuditor = (int) ($activePosition?->jabatan_id ?? 0) === 13;
@endphp

@section('content')
    @if ($isAuditor)
        <div class="card mb-4 border">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-xs text-uppercase text-muted fw-bold">Mode Audit</div>
                        <div class="fw-bold text-dark">Akses baca-saja lintas perangkat daerah pada tahun aktif {{ session('tahun_aktif') }}</div>
                        <div class="text-sm text-secondary">Dokumen SPP KKPD hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
                    </div>
                    <span class="badge bg-gradient-secondary">Read Only Audit</span>
                </div>
            </div>
        </div>
    @endif

    <div id="mainTable"></div>

    @unless ($isAuditor)
        <div class="modal fade" id="add" tabindex="-1" role="dialog" aria-labelledby="add" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-body p-0">
                        <div class="card card-plain">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <div>
                                    <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen SPP KKPD</h3>
                                    <p class="mb-0">Pilih DPR, isi nomor SPP, upload PDF, dan isi rekening anggaran</p>
                                </div>
                                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="card-body">
                                <label>Uraian Pencairan</label>
                                <div class="mb-3 input-group">
                                    <span class="input-group-text pe-3"><i class="fa-solid fa-book-open"></i></span>
                                    <textarea id="uraian" class="form-control" rows="2" placeholder="Uraian Pencairan"
                                        onfocus="this.placeholder=''"></textarea>
                                </div>

                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SPP',
                                    'field' => 'spp',
                                    'readonly' => false,
                                ])

                                <div id="fieldRekening"></div>
                                <div id="fieldBmd"></div>
                                <div id="formTable" class="small mt-3"></div>

                                <div class="text-center">
                                    <button type="button" id="submit"
                                        class="btn btn-round bg-gradient-info btn-lg w-100 mt-4 mb-0">Simpan</button>
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
    @endunless
@endsection

@section('additionals')
    @include('components.informations.detail')
    @include('components.informations.history')
    @unless ($isAuditor)
        @include('components.informations.pdfview')
        @include('components.esign.esign')
        @include('components.confirmations.denied')
        @include('components.confirmations.delete')
        @include('components.confirmations.verify')
        @include('components.confirmations.submit', [
            'url' => route('kkpd.spp.submit'),
        ])

        @include('components.form.rekeningSubKegiatan', [
            'singleSelectedSub' => false,
            'categoryGu' => true,
        ])
        @include('components.form.bmd')

        @include('components.form.formCrud', [
            'urlStore' => route('kkpd.spp.store'),
            'urlUpdate' => route('kkpd.spp.update', ':id'),
            'urlEdit' => route('kkpd.spp.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan KKPD',
        'title' => 'Dokumen SPP',
        'url' => route('kkpd.spp.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [1, 9],
        'columns' => [
            [
                'data' => 'DT_RowIndex',
                'class' => 'text-center',
                'orderable' => false,
                'searchable' => false,
                'title' => 'No',
            ],
            ['data' => 'created_at', 'class' => 'text-center', 'title' => 'Tanggal'],
            ['data' => 'unit_kerja', 'class' => 'text-start text-wrap', 'title' => 'Unit Kerja'],
            ['data' => 'nomor_dpr', 'class' => 'text-start text-wrap', 'render' => 'renderNomor', 'title' => 'Nomor DPR'],
            ['data' => 'nomor_spp', 'class' => 'text-start text-wrap', 'render' => 'renderNomor', 'title' => 'Nomor SPP'],
            ['data' => 'uraian', 'class' => 'text-start text-wrap', 'title' => 'Uraian'],
            ['data' => 'nominal', 'class' => 'text-end text-wrap', 'render' => 'renderRupiah', 'title' => 'Nominal'],
            [
                'data' => 'status_button',
                'class' => 'text-center',
                'searchable' => false,
                'orderable' => false,
                'title' => 'Status Data',
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

    @unless ($isAuditor)
        @include('components.informations.Table', [
            'url' => route('kkpd.spp.form.json'),
            'nameTable' => 'formTable',
            'jabatanAccessAdd' => [],
            'data' => [
                'edited' => 'Edited',
                'data' => 'Data',
            ],
            'columns' => [
                [
                    'data' => 'DT_RowIndex',
                    'class' => 'text-center',
                    'orderable' => false,
                    'searchable' => false,
                    'title' => 'No',
                ],
                ['data' => 'created_at', 'class' => 'text-center text-wrap', 'title' => 'Tanggal'],
                ['data' => 'unit_kerja', 'class' => 'text-start text-wrap', 'title' => 'Unit Kerja'],
                ['data' => 'nomor', 'class' => 'text-start text-wrap', 'render' => 'renderNomor', 'title' => 'Nomor DPR'],
                [
                    'data' => 'status',
                    'class' => 'text-center',
                    'searchable' => false,
                    'orderable' => false,
                    'title' => 'Dokumen',
                ],
                [
                    'data' => 'action',
                    'class' => 'text-center',
                    'searchable' => false,
                    'orderable' => false,
                    'title' => 'Pilih',
                ],
            ],
        ])

        @accessJabatan([1, 9])
            <script>
                function buildFormData() {
                    const formData = new FormData();
                    formData.append('nomor_spp', $('#nomor_spp').val());
                    formData.append('uraian', $('#uraian').val());
                    $('input[name="selected_dpr[]"]:checked').each(function() {
                        formData.append('selected_dpr[]', $(this).val());
                    });

                    const file = document.getElementById('file_spp')?.files?.[0];
                    if (file) {
                        formData.append('file_spp', file);
                    }

                    const bmdFile = document.getElementById('file_bmd')?.files?.[0];
                    if (bmdFile) {
                        formData.append('file_bmd', bmdFile);
                    }

                    const belanja = $('input[name="belanja[]"]:checked').map(function() {
                        return this.value;
                    }).get();
                    if (belanja.length) {
                        formData.append('belanja', belanja.join(','));
                    }

                    if (typeof rekeningComponent === 'undefined' || !rekeningComponent) {
                        notification({
                            status: 400,
                            message: 'Silakan tambahkan rekening terlebih dahulu.'
                        });
                        return null;
                    }

                    const items = rekeningComponent.getData();
                    if (!items.length) {
                        notification({
                            status: 400,
                            message: 'Data rekening wajib diisi.'
                        });
                        return null;
                    }

                    items.forEach((item, index) => {
                        formData.append(`rekening[${index}][id]`, item.id);
                        formData.append(`rekening[${index}][uraian]`, item.uraian);
                        formData.append(`rekening[${index}][rekening]`, item.rekening);
                        formData.append(`rekening[${index}][nominal]`, item.jumlah);
                    });

                    return formData;
                }

                function resetFormAdd() {
                    $('#nomor_spp').val('');
                    $('#uraian').val('');
                    $('#file_spp').val('');
                    $('#name_spp').text('Belum ada file yang dipilih');
                    $('input[name="belanja[]"]').prop('checked', false).trigger('change');
                    if (typeof resetBmdState === 'function') {
                        resetBmdState();
                    } else {
                        $('#file_bmd').val('');
                        $('#name_bmd').val('');
                        $('#fileName_bmd').text('Belum ada file yang dipilih');
                    }

                    if (typeof resetRekeningSession === 'function') {
                        resetRekeningSession();
                    }
                }

                function ModalAddData() {
                    if (!window.FormCrud) {
                        notification({
                            status: 500,
                            message: 'Komponen form belum siap. Silakan muat ulang halaman.'
                        });
                        return;
                    }

                    resetFormAdd();
                    $('#submit').off('click').on('click', StoreData);

                    DT.set('formTable', 'Edited', false);
                    DT.set('formTable', 'Data', null);
                    formTable.ajax.reload();

                    FormCrud.showModal();
                }

                function formEdit(res, id) {
                    const d = res.data || {};

                    $('#nomor_spp').val(d.spp?.nomor || '');
                    $('#uraian').val(d.spp?.uraian || '');
                    $('#file_spp').val('');
                    $('#name_spp').text('Kosongkan jika file tidak diganti');

                    $('input[name="belanja[]"]').prop('checked', false);
                    const expenseTypes = String(d.spp?.expenditure_type || '')
                        .split(',')
                        .map(v => v.trim())
                        .filter(v => v !== '');
                    expenseTypes.forEach(v => $('#belanja' + v).prop('checked', true));
                    $('input[name="belanja[]"]').trigger('change');
                    if (typeof setBmdOriginalRequirement === 'function') {
                        setBmdOriginalRequirement(expenseTypes.some(v => ['1', '2'].includes(v)));
                    }
                    if (typeof setBmdExistingFile === 'function') {
                        setBmdExistingFile(Boolean(d.bmd?.src_name));
                    }

                    if (typeof resetRekeningSession === 'function') {
                        resetRekeningSession();
                    }

                    if (res.rekening && res.rekening.length && typeof rekeningComponent !== 'undefined' && rekeningComponent) {
                        const unitId = String(d.spp?.id_unit_kerja || res.rekening[0].unit_kerja_id || '');
                        const subId = String(res.rekening[0].sub_kegiatan_id || '');

                        if (unitId) {
                            $('#unitKerjaRekening').val(unitId).trigger('change');
                            rekeningComponent.setActiveUnitKerja(unitId);
                        }

                        if (subId) {
                            $('#SubKegiatan').val(subId).trigger('change');
                            rekeningComponent.setActiveSubKegiatan(subId);
                        }

                        rekeningComponent.load(res.rekening);
                    }

                    $('#submit')
                        .off('click')
                        .on('click', () => UpdateData(id))
                        .prop('disabled', false);

                    DT.set('formTable', 'Edited', true);
                    DT.set('formTable', 'Data', id);
                    formTable.ajax.reload();
                }

                function StoreData() {
                    if (!$('input[name="selected_dpr[]"]:checked').length) {
                        notification({
                            status: 400,
                            message: 'Pilih dokumen DPR terlebih dahulu.'
                        });
                        return;
                    }

                    FormCrud.store(function() {
                        if (typeof validateBmdRequirement === 'function') {
                            return validateBmdRequirement();
                        }
                        return true;
                    });
                }

                function UpdateData(id) {
                    if (!$('input[name="selected_dpr[]"]:checked').length) {
                        notification({
                            status: 400,
                            message: 'Pilih dokumen DPR terlebih dahulu.'
                        });
                        return;
                    }

                    FormCrud.update(id, function() {
                        if (typeof validateBmdRequirement === 'function') {
                            return validateBmdRequirement({
                                allowExisting: true,
                                enforceTransitionUpload: true
                            });
                        }
                        return true;
                    });
                }

                FormCrud.editHandler(function(data, id) {
                    formEdit(data, id);
                });
            </script>
        @endaccessJabatan
    @endunless
@endsection
