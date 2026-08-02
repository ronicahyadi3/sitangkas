<div class="modal fade" id="ModalRekening" tabindex="-1" aria-labelledby="ModalRekeningLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="ModalRekeningLabel">
                    Formulir Tambah Rekening
                </h5>
                <i class="fa-solid fa-xmark fa-lg me-2 mt-3" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <label for="selectRekening" class="form-label fw-semibold">
                        Nomor Rekening
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-money-check-dollar"></i>
                        </span>
                        <select class="form-select" id="selectRekening" name="selectRekening" required
                            autocomplete="off"></select>
                    </div>
                </div>
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Nama Kegiatan</small>
                        <div id="detail_kegiatan" class="fw-medium"></div>
                    </li>

                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Nama Sub Kegiatan</small>
                        <div id="detail_sub_kegiatan" class="fw-medium"></div>
                    </li>

                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Nama Rekening</small>
                        <div id="detail_rekening" class="fw-medium"></div>
                    </li>

                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Nama Sub Unit</small>
                        <div id="nama_sub_unit" class="fw-medium"></div>
                    </li>

                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Pagu</small>
                        <div id="detail_pagu" class="fw-bold text-primary"></div>
                    </li>

                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Sisa Pagu</small>
                        <div id="detail_sisa_pagu" class="fw-bold text-success"></div>
                    </li>

                    <li class="list-group-item px-0">
                        <small class="text-muted fw-bolder">Jumlah Tercatat SPP</small>
                        <div id="detail_realisasi" class="fw-medium"></div>
                    </li>
                </ul>
                <div class="text-center">
                    <button type="button" class="btn btn-primary w-100" id="btnTambahRekening">
                        <i class="fa-solid fa-plus me-1"></i>
                        Tambahkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const fieldRekeningHtml = `
        <div class="mb-4">
            <label class="form-label fw-semibold">
                Unit Kerja
            </label>
            <div class="mb-3 input-group">
                <span class="input-group-text">
                    <i class="fa-solid fa-building"></i>
                </span>
                <select class="form-select" id="unitKerjaRekening" name="unitKerjaRekening" required>
                    <option value="">Pilih Unit Kerja</option>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">
                Sub Kegiatan
            </label>
            <div class="mb-3 input-group">
                <span class="input-group-text">
                    <i class="fa-solid fa-list-check"></i>
                </span>
                <select class="form-select" id="SubKegiatan" name="SubKegiatan" required>
                    <option value="">Pilih Sub Kegiatan</option>
                </select>
            </div>
        </div>

        <div id="AppendRekeningField" class="mb-4"></div>

        <div class="card shadow-none border-0 bg-transparent">
            <div class="card-body px-0 pt-0">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <button type="button"
                            class="btn btn-primary btn-sm"
                            id="ModalAddRekening">
                            <i class="fa-solid fa-plus me-2"></i>
                            Tambahkan Rekening
                        </button>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold mb-1">
                            Jumlah Total
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fa-solid fa-rupiah-sign"></i>
                            </span>
                            <input type="text" class="form-control ps-1"
                                id="nominal" placeholder="0,00" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#fieldRekening').html(fieldRekeningHtml);
</script>

<script>
    let rekeningComponent = null;
    $("#ModalAddRekening").click(function() {
        $("#ModalRekening").modal('show');
    })

    $('#ModalRekening').on('shown.bs.modal', function() {
        if (!$('#selectRekening').hasClass('select2-hidden-accessible')) {
            $('#selectRekening').select2({
                width: "80%",
                theme: 'bootstrap-5',
                containerCssClass: "wrap-text-container",
                dropdownParent: $('#ModalRekening'),
                placeholder: 'Pilih Rekening',
            });
        }
    });

    const detailPaguAN = new AutoNumeric('#detail_pagu', COptions);
    const detailSisaAN = new AutoNumeric('#detail_sisa_pagu', COptions);
    const detailRealisasiAN = new AutoNumeric('#detail_realisasi', COptions);

    const rekeningDetailCache = new Map();

    function resetRekeningSession() {
        rekeningDetailCache.clear();
        if (rekeningComponent) {
            rekeningComponent.reset();
            rekeningComponent.rekeningBudget.clear();
        }

        detailPaguAN.set(0);
        detailSisaAN.set(0);
        detailRealisasiAN.set(0);

        $('#detail_kegiatan').text('-');
        $('#detail_sub_kegiatan').text('-');
        $('#detail_rekening').text('-');
        $('#nama_sub_unit').text('-');
        $('#selectRekening').val(null).trigger('change');
    }


    $(document).ready(function() {
        rekeningComponent = new RekeningComponent(
            $('#selectRekening'),
            $('#AppendRekeningField'),
            $('#nominal')
        );
    });

    function renderRekeningDetail(rekeningId, res) {
        $('#detail_kegiatan').text(res.kegiatan);
        $('#detail_sub_kegiatan').text(res.sub_kegiatan);
        $('#detail_rekening').text(res.rekening);
        $('#nama_sub_unit').text(res.nama_sub_unit);

        detailPaguAN.set(res.pagu || 0);
        detailSisaAN.set(res.sisa_pagu || 0);
        detailRealisasiAN.set(res.total_nominal || 0);

        if (rekeningComponent) {
            rekeningComponent.rekeningBudget.set(String(rekeningId), {
                pagu: parseIDNumber(res.pagu),
                realisasi: parseIDNumber(res.total_nominal),
            });
        }
    }

    function parseIDNumber(val) {
        if (typeof val === 'number') return val;
        if (!val) return 0;

        return Number(
            String(val)
            .replace(/[^0-9,-]/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
        ) || 0;
    }

    class RekeningComponent {
        constructor(selectEl, containerEl, totalEl) {
            this.selectEl = selectEl;
            this.containerEl = containerEl;
            this.totalEl = totalEl;

            this.data = [];
            this.usedIds = new Set();
            this.index = 0;

            this.rekeningBudget = new Map();

            this.autoNumericItems = new Map();

            this.activeSubKegiatan = null;

            this.autoNumericTotal = new AutoNumeric(this.totalEl[0], {
                ...COptions,
                readOnly: true
            });

            this.activeUnitKerja = null;

            this.bindEvents();
        }

        bindEvents() {
            $('#btnTambahRekening').on('click', () => this.add());
            $(document).on('click', '.btn-hapus', e => this.remove(e));
        }

        setActiveUnitKerja(unitKerjaId) {
            this.activeUnitKerja = unitKerjaId || null;
            this.applyFilter();
        }

        validateRekeningAmount(rekeningId) {
            const budget = this.rekeningBudget.get(String(rekeningId));
            if (!budget) return;

            const list = this.autoNumericItems.get(String(rekeningId)) || [];

            let totalInput = 0;
            list.forEach(an => totalInput += Number(an.getNumber()));

            const sisaValid = budget.pagu - budget.realisasi;

            if (totalInput > sisaValid) {
                alert(
                    `Jumlah melebihi sisa pagu rekening.\n\n` +
                    `Pagu Total : Rp ${budget.pagu.toLocaleString('id-ID')}\n` +
                    `Realisasi  : Rp ${budget.realisasi.toLocaleString('id-ID')}\n` +
                    `Sisa Pagu  : Rp ${sisaValid.toLocaleString('id-ID')}`
                );

                const last = list[list.length - 1];
                last?.set(0);
                return;
            }

            this.updateTotal();
        }

        updateData(newData) {
            newData.forEach(item => {
                if (this.data.some(d => String(d.id) === String(item.id))) return;

                this.data.push(item);

                const opt = new Option(
                    `${item.uraian} (${item.rekening}) - ${item.nama_sub_unit}`,
                    item.id,
                    false,
                    false
                );
                opt.dataset.sub = item.sub_kegiatan_id;

                if (this.usedIds.has(String(item.id))) opt.disabled = true;

                this.selectEl.append(opt);
            });

            this.applyFilter();
        }

        add() {
            const id = this.selectEl.val();
            if (!id) return alert('Pilih rekening terlebih dahulu');

            const budget = this.rekeningBudget.get(String(id));
            if (!budget) {
                alert('Silakan pilih rekening untuk melihat detail pagu terlebih dahulu');
                return;
            }

            if ((budget.pagu - budget.realisasi) <= 0) {
                alert('Sisa pagu rekening ini sudah habis');
                return;
            }

            if (this.usedIds.has(String(id))) {
                alert('Rekening sudah ditambahkan');
                return;
            }

            const item = this.data.find(d => String(d.id) === String(id));
            if (!item) return;

            this.index++;
            this.usedIds.add(String(id));

            const html = `
                <div class="form-group mb-3 rekening-item" data-id="${id}" data-sub="${item.sub_kegiatan_id}">
                    <label class="form-control-label d-block">
                        ${this.getSubBadge(item.sub_kegiatan_id, item.nama_sub_unit)}
                        <span class="text-muted">
                            <i class="fas fa-clipboard-list"></i> ${item.uraian}
                        </span>
                    </label>
                    <div class="row">
                        <div class="col-12 col-lg-7">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </span>
                                <input type="text" class="form-control ps-1" value="${item.rekening}" readonly>
                            </div>
                        </div>
                        <div class="col-10 col-lg-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-rupiah-sign"></i>
                                </span>
                                <input type="text"
                                    class="form-control jumlah-input"
                                    id="jumlah-${this.index}">
                            </div>
                        </div>
                        <div class="col-2 col-lg-1">
                            <button type="button"
                                    class="btn btn-icon-only p-2 btn-danger mb-0 btn-hapus">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>`;

            this.containerEl.append(html);

            const an = new AutoNumeric(`#jumlah-${this.index}`, COptions);

            if (!this.autoNumericItems.has(String(id))) {
                this.autoNumericItems.set(String(id), []);
            }

            this.autoNumericItems.get(String(id)).push(an);

            $(`#jumlah-${this.index}`).on('input', () => {
                this.validateRekeningAmount(id);
            });

            this.selectEl.find(`option[value="${id}"]`).prop('disabled', true);
            this.selectEl.val(null).trigger('change.select2');
            $('#ModalRekening').modal('hide');
        }

        load(items) {
            items.forEach(item => {
                item.id = item.kode;
                const id = String(item.id);
                if (!this.rekeningBudget.has(id)) {
                    this.rekeningBudget.set(id, {
                        pagu: Number(item.pagu || 0),
                        realisasi: Number(item.realisasi || 0)
                    });
                }

                if (!this.data.some(d => String(d.id) === id)) {
                    this.data.push(item);

                    const opt = new Option(
                        `${item.uraian} (${item.rekening}) - ${item.nama_sub_unit}`,
                        id,
                        false,
                        false
                    );
                    opt.dataset.sub = item.sub_kegiatan_id;
                    opt.disabled = true;
                    this.selectEl.append(opt);
                }

                if (this.usedIds.has(id)) return;

                this.usedIds.add(id);
                this.index++;

                const html = `
                    <div class="form-group mb-3 rekening-item" data-id="${id}" data-sub="${item.sub_kegiatan_id}">
                        <label class="form-control-label d-block">
                            ${this.getSubBadge(item.sub_kegiatan_id, item.nama_sub_unit)}
                            <span class="text-muted">
                                <i class="fas fa-clipboard-list"></i> ${item.uraian}
                            </span>
                        </label>
                        <div class="row">
                            <div class="col-12 col-lg-7">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </span>
                                    <input type="text" class="form-control ps-1" value="${item.rekening}" readonly>
                                </div>
                            </div>
                            <div class="col-10 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fa-solid fa-rupiah-sign"></i>
                                    </span>
                                    <input type="text"
                                        class="form-control jumlah-input"
                                        id="jumlah-${this.index}">
                                </div>
                            </div>
                            <div class="col-2 col-lg-1">
                                <button type="button"
                                        class="btn btn-icon-only p-2 btn-danger mb-0 btn-hapus">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>`;

                this.containerEl.append(html);

                const an = new AutoNumeric(`#jumlah-${this.index}`, COptions);
                an.set(Number(item.jumlah || 0));

                if (!this.autoNumericItems.has(id)) {
                    this.autoNumericItems.set(id, []);
                }

                this.autoNumericItems.get(id).push(an);

                $(`#jumlah-${this.index}`).on('input', () => {
                    this.validateRekeningAmount(id);
                });
            });

            this.updateTotal();
            this.applyFilter();
        }

        remove(e) {
            const el = $(e.target).closest('.rekening-item');
            const id = String(el.data('id'));

            const list = this.autoNumericItems.get(id) || [];
            const inputEl = el.find('.jumlah-input')[0];

            this.autoNumericItems.set(
                id,
                list.filter(an => an.domElement !== inputEl)
            );

            if (this.autoNumericItems.get(id).length === 0) {
                this.autoNumericItems.delete(id);
                this.usedIds.delete(id);
                this.selectEl.find(`option[value="${id}"]`).prop('disabled', false);
            }

            el.remove();
            this.updateTotal();
        }

        setActiveSubKegiatan(subId) {
            this.activeSubKegiatan = subId || null;
            this.applyFilter();
        }

        applyFilter() {
            this.selectEl.empty().append('<option></option>');

            this.data.forEach(item => {
                if (this.usedIds.has(String(item.id))) return;

                const passSub = !this.activeSubKegiatan ||
                    String(item.sub_kegiatan_id) === String(this.activeSubKegiatan);

                const passUnit = !this.activeUnitKerja ||
                    String(item.unit_kerja_id) === String(this.activeUnitKerja);

                if (passSub && passUnit) {
                    const opt = new Option(
                        `${item.uraian} (${item.rekening}) - ${item.nama_sub_unit}`,
                        item.id,
                        false,
                        false
                    );
                    this.selectEl.append(opt);
                }
            });

            this.selectEl.trigger('change.select2');
        }

        getData() {
            const result = [];

            this.usedIds.forEach(id => {
                const item = this.data.find(d => String(d.id) === id);
                if (!item) return;

                let total = 0;
                const list = this.autoNumericItems.get(id) || [];
                list.forEach(an => total += Number(an.getNumber()));

                result.push({
                    id: item.id,
                    uraian: item.uraian,
                    rekening: item.rekening,
                    sub_kegiatan_id: item.sub_kegiatan_id,
                    jumlah: Number(total.toFixed(2))
                });
            });

            return result;
        }

        getSubBadge(subId, subUnit = '') {
            const d = window.subKegiatanMap?.[subId];
            return `
                <div class="text-primary small mb-1">
                    <i class="fas fa-hashtag"></i> ${d?.kode || subId} - ${d?.nama || ''} - ${subUnit}
                </div>`;
        }

        normalize(val) {
            return String(val).trim();
        }

        updateTotal() {
            let total = 0;
            this.autoNumericItems.forEach(list => {
                list.forEach(an => total += Number(an.getNumber()));
            });
            this.autoNumericTotal.set(total);
        }

        reset() {
            this.autoNumericItems.forEach(list => list.forEach(an => an.remove()));
            this.autoNumericItems.clear();
            this.containerEl.empty();
            this.usedIds.clear();
            this.selectEl.find('option').prop('disabled', false);
            this.selectEl.val(null).trigger('change');
            this.index = 0;
            this.autoNumericTotal.set(0);
        }
    }

    window.subKegiatanList = [];
    window.subKegiatanMap = {};

    $.ajax({
        url: `{{ route('document.sub_kegiatan') }}`,
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            categoryGu: '{{ $categoryGu }}'
        },
        dataType: 'json',
        success(response) {
            const unitKerjaDistinct = [
                ...new Map(
                    response.map(item => [
                        item.unit_kerja_id,
                        {
                            id: item.unit_kerja_id,
                            nama: item.unit_kerja
                        }
                    ])
                ).values()
            ];

            const $unit = $('#unitKerjaRekening');
            $unit.empty().append(new Option('- Pilih Unit Kerja -', ''));

            unitKerjaDistinct.forEach(u => {
                $unit.append(new Option(u.nama, u.id));
            });

            response.forEach(item => {
                window.subKegiatanList.push({
                    kode: item.kode,
                    nama: item.nama,
                    unit_kerja_id: item.unit_kerja_id,
                    unit_kerja: item.unit_kerja
                });

                window.subKegiatanMap[item.kode] = {
                    kode: item.kode,
                    nama: item.nama
                };
            });
        },
        error: function(xhr) {
            if (typeof notification === 'function') {
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    notification({
                        status: xhr.status,
                        message: xhr.responseJSON
                            .message
                    });
                } else {
                    notification({
                        status: xhr.status,
                        message: xhr.statusText ||
                            'Terjadi kesalahan saat mangambil data sub kegiatan'
                    });
                }
            } else {
                alert(xhr.statusText || 'Terjadi kesalahan saat mangambil data sub kegiatan');
            }
        },
    });

    $('#unitKerjaRekening').on('change', function() {
        const unitKerjaId = this.value;

        const $sub = $('#SubKegiatan');

        rekeningComponent.setActiveUnitKerja(unitKerjaId);

        $sub.empty().append(new Option('- Pilih Sub Kegiatan -', ''));

        const seen = new Set();

        window.subKegiatanList.forEach(item => {
            if (String(item.unit_kerja_id) === String(unitKerjaId)) {

                if (seen.has(item.kode)) return;
                seen.add(item.kode);

                $sub.append(
                    new Option(`${item.nama} (${item.kode})`, item.kode)
                );
            }
        });

        $sub.trigger('change.select2');
    });

    async function confirmSubKegiatanChange() {
        if (typeof Swal === 'undefined') {
            return confirm('Mengganti Sub Kegiatan akan menghapus data rekening yang sudah dimasukkan. Lanjutkan?');
        }

        const result = await Swal.fire({
            title: 'Ganti Sub Kegiatan?',
            text: 'Data rekening yang sudah dimasukkan akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Lanjutkan',
            cancelButtonText: 'Batal',
            reverseButtons: true
        });

        return result.isConfirmed;
    }

    let previousSubKegiatan = null;
    $('#SubKegiatan').on('change', async function() {
        const id = $(this).val();
        if (!id) return;
        if (previousSubKegiatan === null && "{{ $singleSelectedSub }}" == true) {
            previousSubKegiatan = id;
        } else if (previousSubKegiatan !== id && "{{ $singleSelectedSub }}" == true) {
            if (rekeningComponent.getData().length > 0) {
                const ok = await confirmSubKegiatanChange();
                if (!ok) {
                    $(this).val(previousSubKegiatan).trigger('change.select2');
                    return;
                }
                rekeningComponent.reset();
            }
            previousSubKegiatan = id;
        }

        const data = {
            _token: '{{ csrf_token() }}',
            categoryGu: '{{ $categoryGu }}',
            unit: $('#unitKerjaRekening').val(),
            id: id
        }

        $.ajax({
            url: `{{ route('document.rekening') }}`,
            type: 'POST',
            data: data,
            dataType: 'json',
            success(res) {
                const data = [];
                res.forEach(item => {
                    data.push({
                        id: item.kode,
                        uraian: item.uraian,
                        rekening: item.rekening,
                        sub_kegiatan_id: item.sub_kegiatan_id,
                        nama_sub_unit: item.nama_sub_unit,
                        unit_kerja_id: item.unit_kerja_id,
                        jumlah: 0
                    })
                });
                rekeningComponent.updateData(data);
                rekeningComponent.setActiveSubKegiatan(id);
            },
            error: function(xhr) {
                if (typeof notification === 'function') {
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        notification({
                            status: xhr.status,
                            message: xhr.responseJSON
                                .message
                        });
                    } else {
                        notification({
                            status: xhr.status,
                            message: xhr.statusText ||
                                'Terjadi kesalahan saat mangambil data sub kegiatan'
                        });
                    }
                } else {
                    alert(xhr.statusText ||
                        'Terjadi kesalahan saat mangambil data sub kegiatan');
                }
            },
        });
    });

    $('#selectRekening').on('change', function() {
        const id = $(this).val();
        if (!id) return;

        if (rekeningDetailCache.has(id)) {
            renderRekeningDetail(id, rekeningDetailCache.get(id));
            return;
        }

        const unit = $('#unitKerjaRekening').val();

        if (!unit) {
            alert('Pilih Unit Kerja terlebih dahulu');
            return;
        }

        $.ajax({
            url: `{{ route('document.rekening.detail') }}`,
            type: 'GET',
            data: {
                _token: '{{ csrf_token() }}',
                categoryGu: '{{ $categoryGu }}',
                unit: unit,
                id: id
            },
            dataType: 'json',
            success(res) {
                rekeningDetailCache.set(id, res);
                renderRekeningDetail(id, res);
            },
            error: function(xhr) {
                if (typeof notification === 'function') {
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        notification({
                            status: xhr.status,
                            message: xhr.responseJSON
                                .message
                        });
                    } else {
                        notification({
                            status: xhr.status,
                            message: xhr.statusText ||
                                'Gagal Memuat Detail data rekening'
                        });
                    }
                } else {
                    alert(xhr.statusText || 'Gagal Memuat Detail data rekening');
                }
            },
        });
    });

    $('#SubKegiatan').select2({
        theme: "bootstrap-5",
        width: '80%',
        dropdownParent: $('#add'),

    });

    $('#unitKerjaRekening').select2({
        theme: "bootstrap-5",
        width: '80%',
        dropdownParent: $('#add'),

    });
</script>
