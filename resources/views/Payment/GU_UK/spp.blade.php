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
                        <div class="text-sm text-secondary">Dokumen GU Unit Kerja LPJ BPP hanya dapat dilihat melalui detail dokumen, riwayat perubahan, dan lampiran TBP.</div>
                    </div>
                    <span class="badge bg-gradient-secondary">Read Only Audit</span>
                </div>
            </div>
        </div>
    @endif
    <div id="mainTable"></div>
    @unless ($isAuditor)
    @accessJabatan([10])
        <div class="modal fade" id="add" tabindex="-1" role="dialog" aria-labelledby="add" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-body p-0">
                        <div class="card card-plain">
                            <div class="card-header pb-0 d-flex justify-content-between">
                                <div>
                                    <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen LPJ BPP</h3>
                                    <p class="mb-0">Isi nomor LPJ, upload PDF, lalu pilih TBP yang akan dilampirkan</p>
                                </div>
                                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal"
                                    aria-label="Close"></i>
                            </div>
                            <div class="card-body">
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen LPJ BPP',
                                    'field' => 'lpj_bpp',
                                    'readonly' => false,
                                ])
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SPJ Fungsional',
                                    'field' => 'spj_fungsional',
                                    'readonly' => true,
                                ])

                                <div id="formTable" class="small"></div>
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
    @endaccessJabatan
    @endunless
@endsection

@section('additionals')
    @include('components.informations.detail')
    @include('components.informations.detailTbp', [
        'url' => route('document.detail_tbp'),
    ])
    @include('components.informations.history')
    @unless ($isAuditor)
        @include('components.confirmations.delete')
        @include('components.confirmations.submit', [
            'url' => route('gu-uk.spp.submit'),
        ])
    @endunless

    @unless ($isAuditor)
    @accessJabatan([10])
        @include('components.form.formCrud', [
            'urlStore' => route('gu-uk.spp.store'),
            'urlUpdate' => route('gu-uk.spp.update', ':id'),
            'urlEdit' => route('gu-uk.spp.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endaccessJabatan
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan GU Unit Kerja',
        'title' => 'Dokumen LPJ BPP',
        'url' => route('gu-uk.spp.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [10],
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
            ['data' => 'nomor', 'class' => 'text-start text-wrap', 'title' => 'Nomor LPJ', 'render' => 'renderNomor'],
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
    @accessJabatan([10])
        @include('components.informations.Table', [
            'url' => route('gu-uk.spp.tbp.json'),
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
                    'title' => 'Pilih',
                ],
            ],
        ])
    @endaccessJabatan
    @endunless

    @unless ($isAuditor)
    @accessJabatan([10])
        <script>
            function buildFormData() {
                const formData = new FormData();

                formData.append('nomor_lpj_bpp', $('#nomor_lpj_bpp').val());
                $('input[name="selected_tbp[]"]:checked').each(function() {
                    formData.append('selected_tbp[]', $(this).val());
                });

                ['file_lpj_bpp', 'file_spj_fungsional'].forEach(id => {
                    const file = document.getElementById(id)?.files?.[0];
                    if (file) {
                        formData.append(id, file);
                    }
                });

                return formData;
            }

            function resetFormAdd() {
                $('#nomor_lpj_bpp').val('');
                $('#file_lpj_bpp').val('');
                $('#file_spj_fungsional').val('');
                $('#name_lpj_bpp').text('Belum ada file yang dipilih');
                $('#name_spj_fungsional').text('Belum ada file yang dipilih');
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

                $('#nomor_lpj_bpp').val(d.lpj_bpp?.nomor || '');
                $('#file_lpj_bpp').val('');
                $('#file_spj_fungsional').val('');
                $('#name_lpj_bpp').text('Kosongkan jika file tidak diganti');
                $('#name_spj_fungsional').text('Kosongkan jika file tidak diganti');

                $('#submit')
                    .off('click')
                    .on('click', () => UpdateData(id))
                    .prop('disabled', false);

                DT.set('formTable', 'Edited', true);
                DT.set('formTable', 'Data', id);
                formTable.ajax.reload();
            }

            function StoreData() {
                if (!$('input[name="selected_tbp[]"]:checked').length) {
                    notification({
                        status: 400,
                        message: 'Pilih minimal satu dokumen TBP.'
                    });
                    return;
                }

                FormCrud.store();
            }

            function UpdateData(id) {
                if (!$('input[name="selected_tbp[]"]:checked').length) {
                    notification({
                        status: 400,
                        message: 'Pilih minimal satu dokumen TBP.'
                    });
                    return;
                }

                FormCrud.update(id);
            }

            FormCrud.editHandler(function(data, id) {
                formEdit(data, id);
            });
        </script>
    @endaccessJabatan
    @endunless
@endsection
