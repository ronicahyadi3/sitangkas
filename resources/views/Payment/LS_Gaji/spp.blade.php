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
                        <div class="text-sm text-secondary">Dokumen LS Gaji SPP hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
                    </div>
                    <span class="badge bg-gradient-secondary">Read Only Audit</span>
                </div>
            </div>
        </div>
    @endif
    <div id="mainTable"></div>
    @unless ($isAuditor)
        <div class="modal fade" id="add" tabindex="-1" role="dialog" aria-labelledby="add" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body p-0">
                        <div class="card card-plain">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <div>
                                    <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen</h3>
                                    <p class="mb-0">Masukkan data yang diperlukan pada formulir ini</p>
                                </div>
                                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="card-body">
                                <label>Uraian Pencairan</label>
                                <div class="mb-3 input-group">
                                    <span class="input-group-text pe-3"><i class="fa-solid fa-book-open"></i></span>
                                    <textarea type="text" id="uraian" class="form-control" rows="2" placeholder="Uraian Pencairan"
                                        onfocus="this.placeholder=''"></textarea>
                                </div>
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SPP',
                                    'field' => 'spp',
                                    'readonly' => false,
                                ])
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SPJ',
                                    'field' => 'spj',
                                    'readonly' => true,
                                ])
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen Billing',
                                    'field' => 'billing',
                                    'readonly' => true,
                                ])
                                <div id="fieldRekening"></div>
                                <div id="fieldBmd"></div>
                                <div class="text-center">
                                    <button type="button" id="submit"
                                        class="btn btn-round bg-gradient-info btn-lg w-100 mt-4 mb-0">Submit</button>
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
        @include('components.confirmations.denied')
        @include('components.confirmations.delete')
        @include('components.confirmations.verify')
        @include('components.form.editBilling')
        @include('components.form.bmd')
        @include('components.form.rekeningSubKegiatan', [
            'singleSelectedSub' => true,
            'categoryGu' => false,
        ])
        @include('components.confirmations.submitPPTK', [
            'url' => route('ls-gaji.spp.submit.pptk'),
        ])

        @include('components.confirmations.submit', [
            'url' => route('ls-gaji.spp.submit'),
        ])
        @include('components.form.formCrud', [
            'urlStore' => route('ls-gaji.spp.store'),
            'urlUpdate' => route('ls-gaji.spp.update', ':id'),
            'urlEdit' => route('ls-gaji.spp.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless
    @include('components.informations.Table', [
        'subtitle' => 'Pencairan LS Gaji',
        'title' => 'Dokumen SPP',
        'url' => route('ls-gaji.spp.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [9, 10],
        'columns' => [
            [
                'data' => 'DT_RowIndex',
                'class' => 'text-center',
                'orderable' => false,
                'searchable' => false,
                'title' => 'No',
            ],
            ['data' => 'created_at_spp', 'class' => 'text-center', 'title' => 'Tanggal'],
            ['data' => 'unit_kerja_spp', 'class' => 'text-start text-wrap', 'title' => 'Unit Kerja'],
            ['data' => 'nomor', 'class' => 'text-start text-wrap', 'title' => 'Nomor'],
            ['data' => 'uraian', 'class' => 'text-start text-wrap', 'title' => 'Uraian'],
            [
                'data' => 'nominal',
                'class' => 'text-start',
                'render' => 'renderRupiah',
                'title' => 'Nominal',
            ],
            [
                'data' => 'status',
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

    @unless ($isAuditor)
        @accessJabatan([9, 10])
        <script>
            function buildFormData() {
                const formData = new FormData();
                formData.append('nomor_spp', $('#nomor_spp').val());
                formData.append('uraian', $('#uraian').val());

                const file_spp = $('#file_spp')[0].files[0];
                const file_spj = $('#file_spj')[0].files[0];
                const file_billing = $('#file_billing')[0].files[0];
                const file_bmd = $('#file_bmd')[0].files[0];

                formData.append('file_spp', file_spp);
                formData.append('file_spj', file_spj);
                formData.append('file_billing', file_billing);
                formData.append('file_bmd', file_bmd);

                const belanja = $('input[name="belanja[]"]:checked')
                    .map(function() {
                        return $(this).val();
                    })
                    .get();

                formData.append('belanja', belanja);

                if (typeof rekeningComponent === 'undefined' || !rekeningComponent) {
                    notification({
                        status: 400,
                        message: 'Silakan tambahkan rekening terlebih dahulu.'
                    });
                    return null;
                }

                const items = rekeningComponent.getData();

                items.forEach((item, index) => {
                    formData.append(`rekening[${index}][id]`, item.id);
                    formData.append(`rekening[${index}][uraian]`, item.uraian);
                    formData.append(`rekening[${index}][rekening]`, item.rekening);
                    formData.append(`rekening[${index}][nominal]`, item.jumlah);
                });

                formData.append(
                    'nominal',
                    Number(AutoNumeric.getNumber('#nominal')).toFixed(2)
                );

                formData.append(
                    'sub_kegiatan_id',
                    $("#SubKegiatan").val()
                );

                return formData;
            }

            function resetFormAdd() {
                [
                    '#uraian',
                    '#nominal',
                    '#nomor_spp'
                ].forEach(sel => $(sel).val(''));
                $('input[name="belanja[]"]').prop('checked', false);
                [
                    ['file_spp', 'name_spp'],
                    ['file_spj', 'name_spj'],
                    ['file_billing', 'name_billing'],
                    ['file_bmd', 'fileName_bmd']
                ].forEach(([file, label]) => {
                    $('#' + file).val('');
                    $('#' + label).text('Belum ada file yang dipilih');
                });
                if (typeof resetBmdState === 'function') {
                    resetBmdState();
                } else {
                    $('#name_bmd').text('');
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
                FormCrud.showModal();
            }

            function waitForSelectOption(selector, value, timeout = 6000) {
                const wanted = String(value ?? '');
                if (!wanted) return Promise.resolve(false);

                return new Promise(resolve => {
                    const started = Date.now();
                    const timer = setInterval(() => {
                        const exists = $(`${selector} option`).toArray().some(opt => String(opt.value) ===
                            wanted);
                        if (exists) {
                            clearInterval(timer);
                            resolve(true);
                            return;
                        }

                        if (Date.now() - started >= timeout) {
                            clearInterval(timer);
                            resolve(false);
                        }
                    }, 100);
                });
            }

            async function formEdit(d, id) {

                $('#submit')
                    .off('click')
                    .on('click', () => UpdateData(id))
                    .prop('disabled', false);

                $('#nomor_spp').val(d.data.nomor);
                $('#uraian').val(d.data.uraian);

                if (d.data.expenditure_type) {
                    const types = d.data.expenditure_type.split(',');
                    types.forEach(el => $('#belanja' + el).prop('checked', true));
                }
                if (typeof setBmdOriginalRequirement === 'function') {
                    setBmdOriginalRequirement(Boolean(d.data.expenditure_type) &&
                        d.data.expenditure_type.split(',').some(value => ['1', '2'].includes(String(value).trim())));
                }
                if (typeof toggleFileInput === 'function') {
                    toggleFileInput();
                }

                if (typeof setBmdExistingFile === 'function') {
                    setBmdExistingFile(Boolean(d.has_bmd));
                }

                if (d.rekening && d.rekening.length) {
                    if (typeof resetRekeningSession === 'function') {
                        resetRekeningSession();
                    }

                    const subId = String(d.rekening[0].sub_kegiatan_id || '');
                    const unitId = String(d.data.id_unit_kerja || d.rekening[0].unit_kerja_id || '');

                    if (unitId) {
                        await waitForSelectOption('#unitKerjaRekening', unitId);
                        $('#unitKerjaRekening')
                            .val(unitId)
                            .trigger('change');

                        rekeningComponent.setActiveUnitKerja(unitId);
                    }

                    if (subId) {
                        await waitForSelectOption('#SubKegiatan', subId);
                        $('#SubKegiatan')
                            .val(subId)
                            .trigger('change');

                        rekeningComponent.setActiveSubKegiatan(subId);
                    }

                    rekeningComponent.load(d.rekening);
                }
            }

            function StoreData() {
                FormCrud.store(function() {
                    if (typeof validateBmdRequirement === 'function') {
                        return validateBmdRequirement();
                    }
                    return true;
                });
            }

            function UpdateData(id) {
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
