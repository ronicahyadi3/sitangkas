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
                        <div class="text-sm text-secondary">Dokumen SPM KKPD hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
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
                                    <h3 class="font-weight-bolder text-info text-gradient">Unggah Dokumen SPM</h3>
                                    <p class="mb-0">Masukkan data yang diperlukan pada formulir ini</p>
                                </div>
                                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="card-body">
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SPM',
                                    'field' => 'spm',
                                    'readonly' => false,
                                ])
                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SPTJM',
                                    'field' => 'sptjm',
                                    'readonly' => false,
                                ])
                                <label>Dokumen Pernyataan SKPD</label>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text pe-3"><i class="fa-solid fa-file-pdf fa-lg"></i></span>
                                        <input type="text" class="form-control" readonly>
                                        <label class="btn btn-primary mb-0" for="file_sp">Upload</label>
                                        <input type="file" id="file_sp" name="file_sp" accept="application/pdf" class="d-none" />
                                    </div>
                                    <small class="form-text">Nama File : <span id="name_sp"
                                            class="font-weight-bold">Belum ada file yang dipilih</span></small>
                                </div>
                                <label>Dokumen Pengajuan SKPD</label>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text pe-3"><i class="fa-solid fa-file-pdf fa-lg"></i></span>
                                        <input type="text" class="form-control" readonly>
                                        <label class="btn btn-primary mb-0" for="file_sp_pengajuan">Upload</label>
                                        <input type="file" id="file_sp_pengajuan" name="file_sp_pengajuan" accept="application/pdf" class="d-none" />
                                    </div>
                                    <small class="form-text">Nama File : <span id="name_sp_pengajuan"
                                            class="font-weight-bold">Belum ada file yang dipilih</span></small>
                                </div>
                                <div id="formTable" class="small"></div>
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
        @include('components.confirmations.submit', [
            'url' => route('kkpd.spm.submit'),
        ])
        @include('components.form.formCrud', [
            'urlStore' => route('kkpd.spm.store'),
            'urlUpdate' => route('kkpd.spm.update', ':id'),
            'urlEdit' => route('kkpd.spm.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless

    @include('components.informations.Table', [
        'subtitle' => 'Pencairan GU KKPD',
        'title' => 'Dokumen SPM',
        'url' => route('kkpd.spm.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [7],
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
            ['data' => 'nomor_spp', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPP', 'render' => 'renderNomor'],
            ['data' => 'nomor_spm', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPM', 'render' => 'renderNomor'],
            ['data' => 'nomor_sptjm', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPTJM', 'render' => 'renderNomor'],
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
            'url' => route('kkpd.spm.spp.json'),
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
                ['data' => 'nomor_spp', 'class' => 'text-start text-wrap', 'title' => 'Nomor SPP', 'render' => 'renderNomor'],
                [
                    'data' => 'nominal',
                    'class' => 'text-start',
                    'title' => 'Nominal',
                    'render' => 'renderRupiah',
                ],
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
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof bindFileName === 'function') {
                    bindFileName('#file_sp', '#name_sp');
                    bindFileName('#file_sp_pengajuan', '#name_sp_pengajuan');
                }
            });

            function buildFormData() {
                const formData = new FormData();

                formData.append('nomor_spm', $('#nomor_spm').val());
                formData.append('nomor_sptjm', $('#nomor_sptjm').val());

                ['file_spm', 'file_sptjm', 'file_sp', 'file_sp_pengajuan'].forEach(id => {
                    const file = document.getElementById(id)?.files?.[0];
                    if (file) formData.append(id, file);
                });

                formData.append('selected_spp', $('input[name="selected_spp"]:checked').val() || '');

                return formData;
            }

            function resetFormAdd() {
                ['spm', 'sptjm'].forEach(field => {
                    $('#nomor_' + field).val('');
                });

                ['spm', 'sptjm', 'sp', 'sp_pengajuan'].forEach(field => {
                    $('#file_' + field).val('');
                    $('#name_' + field).text('Belum ada file yang dipilih');
                });
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

                $('#nomor_spm').val(d.spm?.nomor || '');
                $('#nomor_sptjm').val(d.sptjm?.nomor || '');

                ['spm', 'sptjm', 'sp', 'sp_pengajuan'].forEach(field => {
                    $('#file_' + field).val('');
                    $('#name_' + field).text('Kosongkan jika file tidak diganti');
                });

                $('#submit')
                    .off('click')
                    .on('click', () => UpdateData(id))
                    .prop('disabled', false);

                DT.set('formTable', 'Edited', true);
                DT.set('formTable', 'Data', id);
                formTable.ajax.reload();
            }

            function StoreData() {
                if (!$('input[name="selected_spp"]:checked').val()) {
                    notification({
                        status: 400,
                        message: 'Pilih dokumen SPP terlebih dahulu.'
                    });
                    return;
                }
                FormCrud.store();
            }

            function UpdateData(id) {
                if (!$('input[name="selected_spp"]:checked').val()) {
                    notification({
                        status: 400,
                        message: 'Pilih dokumen SPP terlebih dahulu.'
                    });
                    return;
                }
                FormCrud.update(id);
            }

            FormCrud.editHandler(function(data, id) {
                formEdit(data, id);
            });
        </script>
    @endunless
@endsection
