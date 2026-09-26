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
                        <div class="text-sm text-secondary">Dokumen GU SKPD SPP, LPJ, SPJ, dan TBP hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
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
                                <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen SPP</h3>
                                <p class="mb-0">Masukkan data yang diperlukan pada formulir ini</p>
                            </div>
                            <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                        </div>
                        <div class="card-body">
                            <label>Uraian Pencairan</label>
                            <div class="mb-3 input-group">
                                <span class="input-group-text pe-3"><i class="fa-solid fa-book-open"></i></span>
                                <textarea id="uraian" class="form-control" rows="2" placeholder="Uraian Pencairan" onfocus="this.placeholder=''"></textarea>
                            </div>

                            @include('components.form.inputFilePdf', [
                                'title' => 'Dokumen SPP',
                                'field' => 'spp',
                                'readonly' => false,
                            ])

                            @include('components.form.inputFilePdf', [
                                'title' => 'Dokumen LPJ',
                                'field' => 'lpj',
                                'readonly' => false,
                            ])

                            <label>Dokumen SPJ Fungsional</label>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text pe-3"><i class="fa-solid fa-file-pdf fa-lg"></i></span>
                                    <input type="text" class="form-control" readonly>
                                    <label class="btn btn-primary mb-0" for="file_spj_fungsional">Upload</label>
                                    <input type="file" id="file_spj_fungsional" accept="application/pdf" class="d-none" />
                                </div>
                                <small class="form-text">Nama File : <span id="name_spj_fungsional"
                                        class="font-weight-bold">Belum ada file yang dipilih</span></small>
                            </div>

                            <div id="fieldRekening"></div>
                            <div id="fieldBmd"></div>
                            <div id="formTable" class="small mt-3"></div>

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
    @include('components.informations.detailTbp', [
        'url' => route('document.detail_tbp'),
    ])
    @include('components.informations.history')
    @unless ($isAuditor)
        @include('components.informations.pdfview')
        @include('components.confirmations.denied')
        @include('components.confirmations.delete')
        @include('components.confirmations.submit', [
            'url' => route('gu-skpd.spp.submit'),
        ])
        @include('components.confirmations.verify')
        @include('components.form.bmd')

        @include('components.form.rekeningSubKegiatan', [
            'singleSelectedSub' => false,
            'categoryGu' => true,
        ])

        @include('components.form.formCrud', [
            'urlStore' => route('gu-skpd.spp.store'),
            'urlUpdate' => route('gu-skpd.spp.update', ':id'),
            'urlEdit' => route('gu-skpd.spp.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan GU SKPD',
        'title' => 'Dokumen SPP, LPJ & SPJ',
        'url' => route('gu-skpd.spp.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [9, 1],
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
            ['data' => 'nomor_spp', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPP', 'render' => 'renderNomor'],
            ['data' => 'nomor_lpj', 'class' => 'text-start text-wrap', 'title' => 'Nomor LPJ', 'render' => 'renderNomor'],
            ['data' => 'uraian', 'class' => 'text-start text-wrap', 'title' => 'Uraian'],
            [
                'data' => 'nominal',
                'class' => 'text-start',
                'render' => 'renderRupiah',
                'title' => 'Nominal',
            ],
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

    @unless ($isAuditor)
    @include('components.informations.Table', [
        'url' => route('gu-skpd.spp.tbp.json'),
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
            ['data' => 'nomor_npd', 'class' => 'text-start text-wrap', 'title' => 'Nomor NPD', 'render' => 'renderNomor'],
            ['data' => 'nomor', 'class' => 'text-start text-wrap', 'title' => 'Nomor TBP', 'render' => 'renderNomor'],
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
                'title' => 'Aksi/Opsi',
            ],
        ],
    ])

    <script>
        document.getElementById('file_spj_fungsional')?.addEventListener('change', function() {
            const f = this.files?.[0];
            document.getElementById('name_spj_fungsional').textContent = f ? f.name : 'Belum ada file yang dipilih';
        });
        function buildFormData() {
            const formData = new FormData();

            formData.append('nomor_spp', $('#nomor_spp').val());
            formData.append('nomor_lpj', $('#nomor_lpj').val());
            formData.append('uraian', $('#uraian').val());
            $('input[name="selected_tbp[]"]:checked').each(function() {
                formData.append('selected_tbp[]', $(this).val());
            });

            const belanja = $('input[name="belanja[]"]:checked')
                .map(function() {
                    return $(this).val();
                })
                .get();
            formData.append('belanja', belanja.join(','));

            ['file_spp', 'file_lpj', 'file_spj_fungsional', 'file_bmd'].forEach(id => {
                const file = document.getElementById(id)?.files?.[0];
                if (file) formData.append(id, file);
            });

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
            $('#uraian').val('');
            ['spp', 'lpj', 'spj_fungsional'].forEach(field => {
                $('#nomor_' + field).val('');
                $('#file_' + field).val('');
                $('#name_' + field).text('Belum ada file yang dipilih');
            });
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

        function waitForSelectOption(selector, value, timeout = 6000) {
            const wanted = String(value ?? '');
            if (!wanted) return Promise.resolve(false);

            return new Promise(resolve => {
                const started = Date.now();
                const timer = setInterval(() => {
                    const exists = $(`${selector} option`).toArray().some(opt => String(opt.value) === wanted);
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

        async function formEdit(res, id) {
            const d = res.data || {};

            $('#nomor_spp').val(d.spp?.nomor || '');
            $('#nomor_lpj').val(d.lpj?.nomor || '');
            $('#uraian').val(d.lpj?.uraian || '');
            $('#file_spp, #file_lpj, #file_spj_fungsional, #file_bmd').val('');
            $('#name_spp, #name_lpj, #name_spj_fungsional').text('Kosongkan jika file tidak diganti');

            $('input[name="belanja[]"]').prop('checked', false);
            if (d.lpj?.expenditure_type) {
                d.lpj.expenditure_type.split(',').forEach(v => $('#belanja' + v).prop('checked', true));
            }
            if (typeof setBmdOriginalRequirement === 'function') {
                const expenseTypes = String(d.lpj?.expenditure_type || '')
                    .split(',')
                    .map(v => v.trim())
                    .filter(v => v !== '');
                setBmdOriginalRequirement(expenseTypes.some(v => ['1', '2'].includes(v)));
            }
            if (typeof setBmdExistingFile === 'function') {
                setBmdExistingFile(Boolean(d.bmd?.src_name));
            }
            if (typeof toggleFileInput === 'function') {
                toggleFileInput();
            }

            if (typeof resetRekeningSession === 'function') {
                resetRekeningSession();
            }

            if (res.rekening && res.rekening.length && typeof rekeningComponent !== 'undefined' && rekeningComponent) {
                const unitId = String(d.lpj?.id_unit_kerja || res.rekening[0].unit_kerja_id || '');
                const subId = String(res.rekening[0].sub_kegiatan_id || '');

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
            if ($('input[name="selected_tbp[]"]:checked').length === 0) {
                notification({
                    status: 400,
                    message: 'Pilih dokumen TBP terlebih dahulu.'
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
            if ($('input[name="selected_tbp[]"]:checked').length === 0) {
                notification({
                    status: 400,
                    message: 'Pilih dokumen TBP terlebih dahulu.'
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
    @endunless
@endsection
