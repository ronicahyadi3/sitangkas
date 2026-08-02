@extends('layouts.app')

@section('styling')
    <style>
        .sidenav {
            display: none !important;
        }

        .context-shell {
            max-width: 1180px;
            margin: 36px auto 64px;
        }

        .context-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.5rem;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.2), transparent 32%),
                radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.12), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 20px 44px rgba(15, 23, 42, 0.08);
        }

        .context-hero__body {
            padding: 1.75rem;
        }

        .context-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .35rem .75rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.08);
            color: #1d4ed8;
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .context-hero__title {
            margin: 1rem 0 .55rem;
            color: #0f172a;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .context-hero__desc {
            max-width: 760px;
            margin: 0;
            color: #475569;
            font-size: .96rem;
        }

        .context-hero__chips {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            margin-top: 1rem;
        }

        .context-chip-text {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(148, 163, 184, 0.22);
            color: #334155;
            font-size: .86rem;
            font-weight: 600;
        }

        .context-panel {
            border: 0;
            border-radius: 1.35rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .context-panel__body {
            position: relative;
            padding: 1.4rem;
        }

        .context-loading {
            position: absolute;
            inset: 0;
            display: none;
            z-index: 20;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(2px);
        }

        .context-loading.active {
            display: block;
        }

        .context-stepper {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.25rem;
        }

        .context-step {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            padding: .65rem .85rem;
            border-radius: 1rem;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.16);
            color: #475569;
            font-size: .86rem;
            font-weight: 600;
        }

        .context-step__number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-size: .82rem;
            font-weight: 700;
        }

        .context-form-card,
        .context-summary-card {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.15rem;
            background: #fff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.04);
        }

        .context-form-card__header,
        .context-summary-card__header {
            padding: 1.1rem 1.1rem 0;
        }

        .context-form-card__title,
        .context-summary-card__title {
            margin-bottom: .25rem;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }

        .context-form-card__desc,
        .context-summary-card__desc {
            margin-bottom: 0;
            color: #64748b;
            font-size: .88rem;
        }

        .context-form-card__body,
        .context-summary-card__body {
            padding: 1.1rem;
        }

        .context-note {
            padding: .9rem 1rem;
            border-radius: 1rem;
            background: rgba(37, 99, 235, 0.06);
            border: 1px solid rgba(37, 99, 235, 0.12);
            color: #334155;
            font-size: .9rem;
        }

        .context-summary-list {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .context-summary-item {
            padding: .85rem .95rem;
            border-radius: .95rem;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .context-summary-item__label {
            display: block;
            margin-bottom: .2rem;
            color: #64748b;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .context-summary-item__value {
            color: #0f172a;
            font-size: .92rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .context-summary-empty {
            color: #94a3b8;
        }

        .context-special-card {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px dashed rgba(148, 163, 184, 0.28);
        }

        .context-special-card__title {
            margin-bottom: .25rem;
            color: #0f172a;
            font-size: .92rem;
            font-weight: 700;
        }

        .context-special-card__desc {
            margin-bottom: .85rem;
            color: #64748b;
            font-size: .85rem;
        }

        .context-submit {
            min-width: 220px;
            border-radius: .9rem;
        }

        .context-submit-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1.25rem;
            flex-wrap: wrap;
        }

        .context-submit-help {
            color: #64748b;
            font-size: .88rem;
        }

        .select2-container--bootstrap-5 .select2-selection {
            min-height: 42px;
        }

        body.dark-version .context-shell {
            color: rgba(226, 232, 240, 0.88);
        }

        body.dark-version .context-hero {
            border-color: rgba(148, 163, 184, 0.22);
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.24), transparent 34%),
                radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.14), transparent 30%),
                linear-gradient(135deg, #111c44 0%, #0b1637 100%);
            box-shadow: 0 24px 54px rgba(0, 0, 0, 0.28);
        }

        body.dark-version .context-hero__eyebrow {
            background: rgba(59, 130, 246, 0.18);
            color: #bfdbfe;
        }

        body.dark-version .context-hero__title,
        body.dark-version .context-form-card__title,
        body.dark-version .context-summary-card__title,
        body.dark-version .context-special-card__title,
        body.dark-version .context-summary-item__value {
            color: #f8fafc;
        }

        body.dark-version .context-hero__desc,
        body.dark-version .context-submit-help,
        body.dark-version .context-summary-empty,
        body.dark-version .context-form-card .text-muted,
        body.dark-version .context-summary-card .text-muted {
            color: #94a3b8 !important;
        }

        body.dark-version .context-form-card__desc,
        body.dark-version .context-summary-card__desc,
        body.dark-version .context-special-card__desc {
            color: #a8b3c7 !important;
            opacity: 1 !important;
        }

        body.dark-version .context-hero .context-chip-text,
        body.dark-version .context-step,
        body.dark-version .context-summary-item {
            background: rgba(15, 23, 42, 0.58);
            border-color: rgba(148, 163, 184, 0.20);
            color: #dbeafe;
        }

        body.dark-version .context-step__number {
            background: rgba(59, 130, 246, 0.20);
            color: #bfdbfe;
        }

        body.dark-version .context-panel,
        body.dark-version .context-form-card,
        body.dark-version .context-summary-card {
            border-color: rgba(148, 163, 184, 0.18);
            background: #111c44;
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.24);
        }

        body.dark-version .context-form-card,
        body.dark-version .context-summary-card {
            background: rgba(17, 28, 68, 0.86);
        }

        body.dark-version .context-loading {
            background: rgba(5, 17, 57, 0.78);
        }

        body.dark-version .context-loading .rounded-pill {
            background: #111c44 !important;
            border-color: rgba(148, 163, 184, 0.22) !important;
            color: #dbeafe;
        }

        body.dark-version .context-note {
            background: rgba(59, 130, 246, 0.14);
            border-color: rgba(96, 165, 250, 0.20);
            color: #cbd5e1;
        }

        body.dark-version .context-summary-item__label {
            color: #93c5fd;
        }

        body.dark-version .context-special-card {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.54) 0%, rgba(17, 28, 68, 0.82) 100%);
            border-color: rgba(148, 163, 184, 0.28);
        }

        body.dark-version .context-form-card .form-select {
            background-color: #0b1637;
            border-color: rgba(148, 163, 184, 0.28);
            color: #e2e8f0;
        }

        body.dark-version .context-form-card .form-select:disabled {
            background-color: rgba(15, 23, 42, 0.62);
            color: #94a3b8;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection {
            background-color: #0b1637;
            border-color: rgba(148, 163, 184, 0.28);
            color: #e2e8f0;
        }

        body.dark-version .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        body.dark-version .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: rgba(96, 165, 250, 0.82);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.18);
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        body.dark-version .select2-container--bootstrap-5 .select2-selection__choice,
        body.dark-version .select2-container--bootstrap-5 .select2-selection__clear {
            color: #e2e8f0;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection__placeholder {
            color: #94a3b8;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection__arrow b {
            border-color: #cbd5e1 transparent transparent transparent;
        }

        body.dark-version .select2-container--bootstrap-5.select2-container--open .select2-selection__arrow b {
            border-color: transparent transparent #cbd5e1 transparent;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-dropdown {
            background: #111c44;
            border-color: rgba(148, 163, 184, 0.28);
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.26);
        }

        body.dark-version .select2-container--bootstrap-5 .select2-search__field {
            background: #0b1637;
            border-color: rgba(148, 163, 184, 0.28);
            color: #e2e8f0;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-results__option {
            color: #cbd5e1;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-results__option--selected,
        body.dark-version .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
            background: rgba(59, 130, 246, 0.18);
            color: #f8fafc;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-results__option--highlighted,
        body.dark-version .select2-container--bootstrap-5 .select2-results__option--highlighted.select2-results__option--selectable {
            background: #2563eb;
            color: #fff;
        }

        @media (max-width: 991.98px) {
            .context-shell {
                margin-top: 24px;
            }

            .context-hero__title {
                font-size: 1.65rem;
            }

            .context-submit {
                width: 100%;
            }
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid px-3 px-lg-4">
        <div class="context-shell">
            <div class="context-hero mb-4">
                <div class="context-hero__body">
                    <span class="context-hero__eyebrow">
                        <i class="fa fa-id-badge"></i> Konteks Kerja
                    </span>
                    <h1 class="context-hero__title">Pilih konteks login sebelum masuk ke dashboard</h1>
                    <p class="context-hero__desc">
                        Admin Super perlu menentukan jabatan, instansi, dan unit kerja yang akan dipakai untuk sesi
                        saat ini. Setelah konteks tersimpan, dashboard dan menu akan menyesuaikan peran kerja yang dipilih.
                    </p>
                    <div class="context-hero__chips">
                        <span class="context-chip-text"><i class="fa fa-shield-halved"></i> Session aman dan spesifik per peran</span>
                        <span class="context-chip-text"><i class="fa fa-building"></i> Instansi dan unit kerja terarah</span>
                        <span class="context-chip-text"><i class="fa fa-gauge-high"></i> Dashboard menyesuaikan konteks aktif</span>
                    </div>
                </div>
            </div>

            <div class="card context-panel">
                <div class="context-panel__body">
                    <div id="ctxLoading" class="context-loading">
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <div class="d-flex align-items-center gap-2 rounded-pill border bg-white shadow-sm px-3 py-2">
                                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                                <span class="small text-muted">Memuat data konteks...</span>
                            </div>
                        </div>
                    </div>

                    <div class="context-stepper">
                        <div class="context-step"><span class="context-step__number">1</span>Pilih jabatan</div>
                        <div class="context-step"><span class="context-step__number">2</span>Tentukan instansi</div>
                        <div class="context-step"><span class="context-step__number">3</span>Pilih unit kerja</div>
                        <div class="context-step"><span class="context-step__number">4</span>Lengkapi user khusus bila diperlukan</div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-7">
                            <form method="POST" action="{{ route('login.post.store') }}" id="formContext">
                                @csrf

                                <div class="context-form-card">
                                    <div class="context-form-card__header">
                                        <h5 class="context-form-card__title">Form Konteks Login</h5>
                                        <p class="context-form-card__desc">Isi pilihan secara berurutan agar sistem dapat
                                            menampilkan opsi yang sesuai.</p>
                                    </div>
                                    <div class="context-form-card__body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Jabatan</label>
                                                <select name="jabatan_id" id="ctx_jabatan" class="form-select select2-basic"
                                                    data-placeholder="Pilih jabatan" required>
                                                    <option value=""></option>
                                                    @foreach ($jabatans as $j)
                                                        <option value="{{ $j['id'] }}"
                                                            data-special-user="{{ $j['special_user'] ?? '' }}">
                                                            {{ $j['nama'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Instansi</label>
                                                <select name="instansi_id" id="ctx_instansi" class="form-select select2-basic"
                                                    data-placeholder="Pilih instansi" disabled>
                                                    <option value=""></option>
                                                </select>
                                                <small id="ctx_instansi_hint" class="text-muted d-none">
                                                    Jabatan ini tidak memerlukan instansi.
                                                </small>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Unit Kerja</label>
                                                <select name="unit_kerja_id" id="ctx_unit" class="form-select select2-basic"
                                                    data-placeholder="Pilih unit kerja" disabled>
                                                    <option value=""></option>
                                                </select>
                                                <small id="ctx_unit_hint" class="text-muted d-none">
                                                    Jabatan ini tidak memerlukan unit kerja.
                                                </small>
                                            </div>
                                        </div>

                                        <div class="context-special-card" id="ctxSpecialCard">
                                            <div class="context-special-card__title">Pilihan User Khusus</div>
                                            <div class="context-special-card__desc">
                                                Hanya diperlukan untuk jabatan tertentu seperti PPTK atau BUD / Kuasa BUD.
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">User PPTK</label>
                                                    <select name="pptk_user_position_id" id="ctx_pptk" class="form-select select2-basic"
                                                        data-placeholder="Pilih PPTK" disabled>
                                                        <option value=""></option>
                                                    </select>
                                                    <small id="ctx_pptk_hint" class="text-muted d-none">
                                                        Tidak ada PPTK pada unit kerja ini.
                                                    </small>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">User BUD / Kuasa BUD</label>
                                                    <select name="bud_user_position_id" id="ctx_bud" class="form-select select2-basic"
                                                        data-placeholder="Pilih User BUD / Kuasa BUD" disabled>
                                                        <option value=""></option>
                                                    </select>
                                                    <small id="ctx_bud_hint" class="text-muted d-none">
                                                        Tidak ada User BUD / Kuasa BUD pada unit kerja ini.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="context-submit-wrap">
                                            <div class="context-submit-help">
                                                Pastikan konteks yang dipilih benar sebelum masuk dashboard.
                                            </div>
                                            <button id="btnSubmitContext" class="btn btn-primary context-submit">
                                                <i class="fa fa-sign-in-alt me-1"></i>
                                                Masuk Dashboard
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="col-lg-5">
                            <div class="context-summary-card">
                                <div class="context-summary-card__header">
                                    <h5 class="context-summary-card__title">Ringkasan Konteks Aktif</h5>
                                    <p class="context-summary-card__desc">Ringkasan ini membantu Anda memastikan pilihan
                                        sebelum konteks disimpan.</p>
                                </div>
                                <div class="context-summary-card__body">
                                    <div class="context-note mb-3">
                                        Pilihan akan diperbarui secara otomatis saat Anda mengganti jabatan, instansi,
                                        atau unit kerja.
                                    </div>

                                    <div class="context-summary-list">
                                        <div class="context-summary-item">
                                            <span class="context-summary-item__label">Jabatan</span>
                                            <div class="context-summary-item__value" id="summaryJabatan">
                                                <span class="context-summary-empty">Belum dipilih</span>
                                            </div>
                                        </div>
                                        <div class="context-summary-item">
                                            <span class="context-summary-item__label">Instansi</span>
                                            <div class="context-summary-item__value" id="summaryInstansi">
                                                <span class="context-summary-empty">Belum dipilih</span>
                                            </div>
                                        </div>
                                        <div class="context-summary-item">
                                            <span class="context-summary-item__label">Unit Kerja</span>
                                            <div class="context-summary-item__value" id="summaryUnit">
                                                <span class="context-summary-empty">Belum dipilih</span>
                                            </div>
                                        </div>
                                        <div class="context-summary-item">
                                            <span class="context-summary-item__label">User Khusus</span>
                                            <div class="context-summary-item__value" id="summarySpecialUser">
                                                <span class="context-summary-empty">Tidak diperlukan</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('additionals')
    <script>
        $(function() {
            function notify(status, message) {
                if (typeof notification === 'function') {
                    notification({
                        status: status,
                        message: message
                    });
                    return;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Info', message, status >= 500 ? 'error' : 'warning');
                    return;
                }

                alert(message);
            }

            $('.select2-basic').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true
            });

            const $jabatan = $('#ctx_jabatan');
            const $instansi = $('#ctx_instansi');
            const $unit = $('#ctx_unit');
            const $pptk = $('#ctx_pptk');
            const $bud = $('#ctx_bud');
            const $loader = $('#ctxLoading');
            const $submit = $('#btnSubmitContext');
            let loadingCount = 0;

            function renderSummaryValue(selector, value, emptyText = 'Belum dipilih') {
                $(selector).html(value ? value : `<span class="context-summary-empty">${emptyText}</span>`);
            }

            function updateContextSummary() {
                const jabatanText = ($jabatan.find('option:selected').text() || '').trim();
                const instansiText = ($instansi.find('option:selected').text() || '').trim();
                const unitText = ($unit.find('option:selected').text() || '').trim();
                const mode = selectedJabatanMode();
                let specialLabel = '';

                if (mode === 'pptk') {
                    specialLabel = ($pptk.find('option:selected').text() || '').trim();
                    renderSummaryValue('#summarySpecialUser', specialLabel, 'User PPTK belum dipilih');
                } else if (mode === 'bud') {
                    specialLabel = ($bud.find('option:selected').text() || '').trim();
                    renderSummaryValue('#summarySpecialUser', specialLabel, 'User BUD / Kuasa BUD belum dipilih');
                } else {
                    renderSummaryValue('#summarySpecialUser', '', 'Tidak diperlukan');
                }

                renderSummaryValue('#summaryJabatan', jabatanText);
                renderSummaryValue('#summaryInstansi', instansiText, 'Belum dipilih');
                renderSummaryValue('#summaryUnit', unitText, 'Belum dipilih');
            }

            function setLoading(active) {
                loadingCount += active ? 1 : -1;
                if (loadingCount < 0) loadingCount = 0;

                const isBusy = loadingCount > 0;
                $loader.toggleClass('active', isBusy);
                $submit.prop('disabled', isBusy);
                $submit.html(isBusy ?
                    '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...' :
                    '<i class="fa fa-sign-in-alt me-1"></i>Masuk Dashboard');
            }

            function selectedJabatanMode() {
                return ($jabatan.find('option:selected').data('special-user') || '').toString().trim() || null;
            }

            function resetInstansiUnit() {
                $instansi.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');

                $unit.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');

                $pptk.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');

                $bud.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');

                $('#ctx_instansi_hint, #ctx_unit_hint, #ctx_pptk_hint, #ctx_bud_hint').addClass('d-none');
                updateContextSummary();
            }

            resetInstansiUnit();

            $jabatan.on('change', function() {
                const jabatan_id = $(this).val();
                resetInstansiUnit();

                if (!jabatan_id) {
                    updateContextSummary();
                    return;
                }

                $.get(@json(route('login.post.options.instansi')), {
                        jabatan_id
                    })
                    .done(function(resp) {
                        if (resp.allow_null) {
                            $('#ctx_instansi_hint, #ctx_unit_hint').removeClass('d-none');
                            notify(200, 'Jabatan terpilih tidak memerlukan instansi dan unit kerja.');
                            updateContextSummary();
                            return;
                        }

                        (resp.options || []).forEach(o => {
                            $instansi.append(new Option(o.text, o.id));
                        });

                        if ((resp.options || []).length === 1) {
                            $instansi.val(resp.options[0].id);
                        }

                        $instansi.prop('disabled', false).trigger('change');
                        updateContextSummary();
                    })
                    .fail((xhr) => notify(xhr?.status || 500, xhr?.responseJSON?.message || 'Gagal memuat instansi.'))
                    .always(() => setLoading(false));

                setLoading(true);
                updateContextSummary();
            });

            $instansi.on('change', function() {
                const instansi_id = $(this).val();
                const jabatan_id = $jabatan.val();

                $unit.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');

                if (!instansi_id) {
                    updateContextSummary();
                    return;
                }

                $.get(@json(route('login.post.options.unit_kerja')), {
                        instansi_id,
                        jabatan_id
                    })
                    .done(function(resp) {
                        (resp.options || []).forEach(o => {
                            $unit.append(new Option(o.text, o.id));
                        });

                        if ((resp.options || []).length === 1) {
                            $unit.val(resp.options[0].id);
                        }

                        $unit.prop('disabled', false).trigger('change');
                        updateContextSummary();
                    })
                    .fail((xhr) => notify(xhr?.status || 500, xhr?.responseJSON?.message || 'Gagal memuat unit kerja.'))
                    .always(() => setLoading(false));

                setLoading(true);
                updateContextSummary();
            });

            $unit.on('change', function() {
                const unit_kerja_id = $(this).val();
                const jabatan_id = $jabatan.val();
                const mode = selectedJabatanMode();

                $pptk.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');
                $bud.empty().append('<option value=""></option>')
                    .prop('disabled', true).trigger('change');

                if (!unit_kerja_id) {
                    updateContextSummary();
                    return;
                }

                $('#ctx_pptk_hint, #ctx_bud_hint').addClass('d-none');

                if (mode === 'pptk') {
                    $.get(@json(route('login.post.options.special_users')), {
                            jabatan_id: jabatan_id,
                            unit_kerja_id: unit_kerja_id
                        })
                        .done(function(resp) {
                            if (!resp.length) {
                                $('#ctx_pptk_hint').removeClass('d-none');
                                updateContextSummary();
                                return;
                            }

                            resp.forEach(u => {
                                $pptk.append(new Option(u.nama, u.id));
                            });

                            if (resp.length === 1) {
                                $pptk.val(resp[0].id);
                            }

                            $pptk.prop('disabled', false).trigger('change');
                            updateContextSummary();
                        })
                        .fail((xhr) => notify(xhr?.status || 500, xhr?.responseJSON?.message || 'Gagal memuat data PPTK.'))
                        .always(() => setLoading(false));
                    setLoading(true);
                    return;
                }

                if (mode === 'bud') {
                    $.get(@json(route('login.post.options.special_users')), {
                            jabatan_id: jabatan_id,
                            unit_kerja_id: unit_kerja_id
                        })
                        .done(function(resp) {
                            if (!resp.length) {
                                $('#ctx_bud_hint').removeClass('d-none');
                                updateContextSummary();
                                return;
                            }

                            resp.forEach(u => {
                                $bud.append(new Option(u.nama, u.id));
                            });

                            if (resp.length === 1) {
                                $bud.val(resp[0].id);
                            }

                            $bud.prop('disabled', false).trigger('change');
                            updateContextSummary();
                        })
                        .fail((xhr) => notify(xhr?.status || 500, xhr?.responseJSON?.message || 'Gagal memuat data BUD / Kuasa BUD.'))
                        .always(() => setLoading(false));
                    setLoading(true);
                }

                updateContextSummary();
            });

            $pptk.on('change', updateContextSummary);
            $bud.on('change', updateContextSummary);

            $('#formContext').on('submit', function(e) {
                const mode = selectedJabatanMode();
                if (mode === 'pptk' && !$pptk.val()) {
                    e.preventDefault();
                    notify(422, 'User PPTK wajib dipilih untuk jabatan PPTK.');
                    return;
                }

                if (mode === 'bud' && !$bud.val()) {
                    e.preventDefault();
                    notify(422, 'User BUD / Kuasa BUD wajib dipilih.');
                    return;
                }

                setLoading(true);
            });

            @if ($errors->any())
                notify(422, {!! json_encode(implode("\n", $errors->all())) !!});
            @endif

            @if (session('status'))
                notify(200, {!! json_encode((string) session('status')) !!});
            @endif

            updateContextSummary();
        });
    </script>
@endsection
