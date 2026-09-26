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
                        <div class="text-sm text-secondary">Dokumen Pengajuan TU hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
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
                                    <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen Pengajuan TU</h3>
                                    <p class="mb-0">Isi nomor pengajuan dan upload file PDF</p>
                                </div>
                                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="card-body">
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen Pengajuan',
                                    'field' => 'pengajuan',
                                    'readonly' => false,
                                ])

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
        @include('components.confirmations.denied')
        @include('components.confirmations.verify')
        @include('components.confirmations.delete')
        @include('components.confirmations.submit', [
            'url' => route('tu.pengajuan.submit'),
        ])

        @include('components.form.formCrud', [
            'urlStore' => route('tu.pengajuan.store'),
            'urlUpdate' => route('tu.pengajuan.update', ':id'),
            'urlEdit' => route('tu.pengajuan.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan TU',
        'title' => 'Dokumen Pengajuan',
        'url' => route('tu.pengajuan.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [1, 8],
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
            ['data' => 'nomor_pengajuan', 'class' => 'text-start text-wrap', 'render' => 'renderNomor', 'title' => 'Nomor Pengajuan'],
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
        @accessJabatan([1, 8])
            <script>
                function buildFormData() {
                    const formData = new FormData();
                    formData.append('nomor_pengajuan', $('#nomor_pengajuan').val());

                    const file = $('#file_pengajuan')[0].files[0];
                    if (file) {
                        formData.append('file_pengajuan', file);
                    }

                    return formData;
                }

                function resetFormAdd() {
                    $('#nomor_pengajuan').val('');
                    $('#file_pengajuan').val('');
                    $('#name_pengajuan').text('Belum ada file yang dipilih');
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

                function formEdit(res, id) {
                    $('#submit').off('click').on('click', () => UpdateData(id)).prop('disabled', false);
                    $('#nomor_pengajuan').val(res.data.nomor || '');
                    $('#file_pengajuan').val('');
                    $('#name_pengajuan').text('Kosongkan jika file tidak diganti');
                }

                function StoreData() {
                    FormCrud.store();
                }

                function UpdateData(id) {
                    FormCrud.update(id);
                }

                FormCrud.editHandler(function(data, id) {
                    formEdit(data, id);
                });
            </script>
        @endaccessJabatan
    @endunless
@endsection
