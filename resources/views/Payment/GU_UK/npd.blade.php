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
                        <div class="text-sm text-secondary">Dokumen GU Unit Kerja NPD hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
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
                                <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen NPD</h3>
                                <p class="mb-0">Isi nomor dan upload file PDF NPD</p>
                            </div>
                            <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                        </div>
                        <div class="card-body">
                            @include('components.form.inputFilePdf', [
                                'title' => 'Dokumen NPD',
                                'field' => 'npd',
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
        @include('components.confirmations.delete')
        @include('components.confirmations.submit', [
            'url' => route('gu-uk.npd.submit'),
        ])

        @include('components.form.formCrud', [
            'urlStore' => route('gu-uk.npd.store'),
            'urlUpdate' => route('gu-uk.npd.update', ':id'),
            'urlEdit' => route('gu-uk.npd.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan GU Unit Kerja',
        'title' => 'Dokumen NPD',
        'url' => route('gu-uk.npd.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [8],
        'columns' => [
            [
                'data' => 'DT_RowIndex',
                'class' => 'text-center',
                'orderable' => false,
                'searchable' => false,
                'title' => 'No',
            ],
            ['data' => 'created_at_npd', 'class' => 'text-center', 'title' => 'Tanggal'],
            ['data' => 'unit_kerja_npd', 'class' => 'text-start text-wrap', 'title' => 'Unit Kerja'],
            ['data' => 'nomor_npd', 'class' => 'text-start text-wrap', 'render' => 'renderNomor', 'title' => 'Nomor'],
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
        @accessJabatan([8])
        <script>
            function buildFormData() {
                const formData = new FormData();
                formData.append('nomor_npd', $('#nomor_npd').val());

                const file = $('#file_npd')[0].files[0];
                if (file) {
                    formData.append('file_npd', file);
                }

                return formData;
            }

            function resetFormAdd() {
                $('#nomor_npd').val('');
                $('#file_npd').val('');
                $('#name_npd').text('Belum ada file yang dipilih');
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
                $('#nomor_npd').val(res.data.nomor || '');
                $('#file_npd').val('');
                $('#name_npd').text('Kosongkan jika file tidak diganti');
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
