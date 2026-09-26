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
                        <div class="text-sm text-secondary">Dokumen LS Gaji SP2D hanya dapat dilihat melalui detail dokumen dan riwayat perubahan.</div>
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

                                <label> Dikirimkan kepada </label>
                                <div class="mb-3 input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user-tie"></i></span>
                                    <select class="form-select" id="users" name="users" required>
                                        <option value="">Pilih User</option>
                                    </select>
                                </div>

                                <label>Nominal Pencairan</label>
                                <div class="mb-3 input-group">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-rupiah-sign"></i>
                                    </span>
                                    <input type="text" class="form-control ps-1" id="nominal" placeholder="0,00">
                                </div>

                                <label>Nomor Rekening</label>
                                <div class="mb-3 input-group">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-money-check-dollar"></i>
                                    </span>
                                    <input type="number" class="form-control ps-1" id="rekening" placeholder="0,00">
                                </div>

                                @include('components.form.inputFilePdf', [
                                    'title' => 'Dokumen SP2D',
                                    'field' => 'sp2d',
                                    'readonly' => false,
                                ])

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
        @include('components.confirmations.submit', [
            'url' => route('ls-gaji.sp2d.submit'),
        ])
        @include('components.form.formCrud', [
            'urlStore' => route('ls-gaji.sp2d.store'),
            'urlUpdate' => route('ls-gaji.sp2d.update', ':id'),
            'urlEdit' => route('ls-gaji.sp2d.edit', ':id'),
            'modalId' => 'add',
            'submitId' => 'submit',
        ])
    @endunless
    @include('components.informations.Table', [
        'subtitle' => 'Pencairan LS Gaji',
        'title' => 'Dokumen SP2D',
        'url' => route('ls-gaji.sp2d.json'),
        'nameTable' => 'mainTable',
        'jabatanAccessAdd' => [4],
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
            [
                'data' => 'nomor',
                'class' => 'text-start text-wrap',
                'title' => 'Nomor SP2D',
                'render' => 'renderNomor',
            ],
            ['data' => 'uraian', 'class' => 'text-start text-wrap', 'title' => 'Uraian'],
            [
                'data' => 'nominal',
                'class' => ' text-start text-wrap',
                'render' => 'renderRupiah',
                'title' => 'Nominal',
            ],
            ['data' => 'user_name', 'class' => 'text-start text-wrap', 'title' => 'BUD/ Kuasa BUD'],
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
        @include('components.informations.Table', [
            'url' => route('ls-gaji.sp2d.spp.json'),
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
                [
                    'data' => 'nomor',
                    'class' => 'text-start text-wrap',
                    'title' => 'Nomor SPM',
                    'render' => 'renderNomor',
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
    @endunless

    @unless ($isAuditor)
        @accessJabatan([4])
        <script>
            const nominal = new AutoNumeric('#nominal', COptions);

            function buildFormData() {
                const formData = new FormData();

                formData.append('nomor_sp2d', $('#nomor_sp2d').val());
                formData.append('rekening', $('#rekening').val());
                formData.append('uraian', $('#uraian').val());
                formData.append('user', $('#users').val());

                const fileInput = document.getElementById('file_sp2d');
                if (fileInput?.files?.length) {
                    formData.append('file_sp2d', fileInput.files[0]);
                }

                formData.append('selected_spm', $('input[name="selected_spm"]:checked').val());

                formData.append(
                    'nominal',
                    Number(AutoNumeric.getNumber('#nominal')).toFixed(2)
                );

                return formData;
            }

            function resetFormAdd() {
                $('#nomor_sp2d').val('');
                $('#rekening').val('');
                $('#uraian').val('');
                $('#users').val('').trigger('change');
                nominal.set(0);
                [
                    ['file_sp2d', 'name_sp2d']
                ].forEach(([file, label]) => {
                    $('#' + file).val('');
                    $('#' + label).text('Belum ada file yang dipilih');
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

            function formEdit(d, id) {
                $('#nomor_sp2d').val(d.data.nomor);
                $('#users')
                    .val(d.data.users_to)
                    .trigger('change');

                nominal.set(d.data.nominal);
                $('#uraian').val(d.data.uraian);
                $('#rekening').val(d.data.rekening);

                $('#submit')
                    .off('click')
                    .on('click', () => UpdateData(id))
                    .prop('disabled', false);

                DT.set('formTable', 'Edited', true);
                DT.set('formTable', 'Data', id);

                formTable.ajax.reload();
            }

            function StoreData() {
                if (!$('input[name="selected_spm"]:checked').val()) {
                    notification({
                        status: 400,
                        message: 'Pilih dokumen SPM terlebih dahulu.'
                    });
                    return;
                }
                FormCrud.store();
            }

            function UpdateData(id) {
                if (!$('input[name="selected_spm"]:checked').val()) {
                    notification({
                        status: 400,
                        message: 'Pilih dokumen SPM terlebih dahulu.'
                    });
                    return;
                }
                FormCrud.update(id);
            }

            FormCrud.editHandler(function(data, id) {
                formEdit(data, id);
            });

            $.ajax({
                url: '/users/bud',
                dataType: 'json',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    open: true,
                },
                success(data) {
                    $.each(data, function(index, item) {
                        $("#users").append(
                            $('<option></option>').val(item.position_id).text(item.nama)
                        );
                    });
                },
                error() {
                notification({
                    status: 500,
                    message: 'Gagal memuat data user BUD.'
                });
                }
            })

            $('#users').select2({
                theme: 'bootstrap-5',
                width: '80%',
                placeholder: 'Pilih User',
                allowClear: true,
                dropdownParent: $('#add'),
            });
        </script>
        @endaccessJabatan
    @endunless
@endsection
