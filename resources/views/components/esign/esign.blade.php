<div class="modal fade" id="signModal" tabindex="-1" role="dialog" aria-labelledby="signModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="card card-plain">
                    <div class="card-header pb-0 text-left d-flex justify-content-between">
                        <div id="header-process">
                            <h3 class="font-weight-bolder h4 text-info text-gradient" id="signModalTitle">Tanda tangan elektronik </h3>
                        </div>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"
                            style="z-index: 2;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div id="content-pdf"></div>
                        <div class="fixed-plugin" id="button-floating-right-side">
                            <a class="fixed-plugin-button position-fixed btn bg-primary text-white rounded-3 h6 me-1 me-lg-3 trigger-create-pdf"
                                role="button" tabindex="0">
                                <i class="fas fa-file-signature" aria-hidden="true"></i> Sign
                            </a>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" tabindex="-1"><i
                                class="fas fa-times-circle"></i> Batal</button>
                        <button type="button" class="btn btn-primary trigger-create-pdf"><i
                                class="fas fa-file-signature" aria-hidden="true"></i>
                            Sign</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ConfirmSign" tabindex="-1" role="dialog" aria-labelledby="confirmSignTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="card card-plain">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <div>
                            <h3 class="font-weight-bolder text-info text-gradient" id="confirmSignTitle">Proses Tanda
                                Tangan</h3>
                            <p class="mb-0">Masukkan Keterangan dan Passprhase untuk melanjutkan</p>
                        </div>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"
                            style="z-index: 2;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <form role="form text-left">
                            <label>Keterangan</label>
                            <div class="input-group mb-2">
                                <textarea type="text" class="form-control" id="reason" name="reason" rows='3'>Dokumen telah disetujui dan ditandatangani secara elektronik</textarea>
                            </div>
                            @if (auth()->user()->activePosition->jabatan->id == 1)
                                <label>Nomor Induk Kependudukan (NIK)</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text"><i class="fa-solid fa-address-card"></i></span>
                                    <input type="number" class="form-control ps-1"
                                        placeholder="Nomor Induk Kependudukan (NIK)"
                                        aria-label="Nomor Induk Kependudukan (NIK)" id="Exchange" name="Exchange"
                                        autocomplete="off" inputmode="numeric">
                                </div>
                            @endif
                            <label>Passphrase</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                                <input type="password" class="form-control ps-1" placeholder="Passphrase"
                                    aria-label="Passphrase" id="Tokenize" name="Tokenize" autocomplete="off"
                                    autocapitalize="none" spellcheck="false">
                                <span class="input-group-text toggle-password" role="button" tabindex="0"
                                    data-target="#Tokenize" data-timeout="5000" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                            </div>
                            <div class="mt-4 text-center d-none" id="countdownWrapper">
                                <span>Tanda Tangan Selesai, Download Otomatis</span>
                                <div id="progressWrapper" class="progress" style="height: 9px;">
                                    <div id="countdownBar" class="progress-bar bg-primary" role="progressbar"
                                        style="width: 100%;">
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-danger font-weight-bold text-white text-center"
                                style="display: none;" role="alert" id="sign-notification">
                            </div>
                            <div class="row d-flex justify-content-center gx-3 mt-4" id="buttonRow">
                                <button id="SignButton" type="button" class="btn btn-info w-100">
                                    <span id="spinner" class="spinner-border spinner-border-sm me-2 d-none"></span>
                                    <span id="signText">Sign Now</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center pt-0 px-lg-2 px-1">
                        <p class="mb-4 text-sm mx-auto">
                            Butuh bantuan?
                            <a href="https://wa.me/6282131701177" target="_blank"
                                class="text-info text-gradient font-weight-bold">Hubungi
                                Admin</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ModalViewSignature" tabindex="-1" role="dialog" aria-labelledby="viewSignatureTitle">
    <div class="modal-dialog modal-danger modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="card card-plain">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <div>
                            <h3 class="font-weight-bolder text-info text-gradient" id="viewSignatureTitle">Status Dokumen
                            </h3>
                            <p id="sv-status" class="text-black">Loading...</p>
                        </div>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"
                            style="z-index: 2;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th
                                            class="text-uppercase text-secondary text-xxs text-center font-weight-bolder opacity-7">
                                            Nama</th>
                                        <th
                                            class="text-uppercase text-secondary text-xxs text-center font-weight-bolder opacity-7 ps-2">
                                            Tanggal Penandatanganan</th>
                                        <th
                                            class="text-center text-uppercase text-secondary text-xxs text-center font-weight-bolder opacity-7">
                                            Alasan</th>
                                    </tr>
                                </thead>
                                <tbody id="sv"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="button" class="btn btn-white" data-bs-dismiss="modal">Ok,
                            Got it</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<link rel="stylesheet" href="/assets/js/pdf/bundle.css" />
<script defer src="/assets/js/pdf/bundle.js?v=1.2"></script>
<script>
    let _doc, _urls, _status, _ids, _location, _load;
    let signModalValidationInProgress = false;

    function setSignContext($el) {
        _doc = $el.data('doc');
        _urls = $el.data('urls');
        _location = $el.data('location');
        _ids = $el.data('files');
        _status = $el.data('status');
    }

    function hasSignContext() {
        return Boolean(_doc);
    }

    function setSignModalLoading($btn, isLoading) {
        if (!$btn || !$btn.length) return;

        if (isLoading) {
            if ($btn.data('loading') === true) return;
            $btn.data('loading', true);
            $btn.data('origin-html', $btn.html());
            $btn.prop('disabled', true).addClass('disabled');
            $btn.html('<i class="fa-solid fa-gear fa-spin me-1"></i> Memvalidasi...');
            // $('.signModal').not($btn).prop('disabled', true).addClass('disabled');
            return;
        }

        const originalHtml = $btn.data('origin-html');
        if (originalHtml) {
            $btn.html(originalHtml);
        }
        $btn.prop('disabled', false).removeClass('disabled');
        $btn.data('loading', false);
        $('.signModal').prop('disabled', false).removeClass('disabled');
    }

    function handleCreatePdfAction() {
        if (!hasSignContext()) {
            notification({
                status: 400,
                message: 'Data dokumen tidak valid.'
            });
            return;
        }

        if (typeof CreatePdf === 'function') {
            CreatePdf();
            return;
        }

        notification({
            status: 500,
            message: 'Fungsi tanda tangan belum siap.'
        });
    }

    $(document).on('click', '.tte', function() {
        if (window.readOnlyUI?.guardAction()) return;
        setSignContext($(this));
    });

    $(document).on('click', '.trigger-create-pdf', function(e) {
        e.preventDefault();
        if (window.readOnlyUI?.guardAction()) return;
        handleCreatePdfAction();
    });

    $(document).on('keydown', '.trigger-create-pdf', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (window.readOnlyUI?.guardAction()) return;
            handleCreatePdfAction();
        }
    });

    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById('signModal');
        if (!modal) return;
        const floatingBtn = document.getElementById('button-floating-right-side');
        if (!modal || !floatingBtn) return;

        const scrollContainer = modal.querySelector('.modal-body') || modal;
        scrollContainer.addEventListener('scroll', function() {
            const scrollTop = scrollContainer.scrollTop;
            const scrollHeight = scrollContainer.scrollHeight;
            const clientHeight = scrollContainer.clientHeight;
            const threshold = 120;
            if (scrollTop + clientHeight >= scrollHeight - threshold) {
                floatingBtn.style.opacity = "0";
                floatingBtn.style.pointerEvents = "none";
            } else {
                floatingBtn.style.opacity = "1";
                floatingBtn.style.pointerEvents = "auto";
            }
        });
    });

    $(document).on('click', '.signModal', async function(e) {
        e.preventDefault();
        if (window.readOnlyUI?.guardAction()) return;

        if (signModalValidationInProgress) {
            notification({
                status: 403,
                message: 'File sedang proses validasi Bsre, Mohon tunggu hingga proses validasi selesai'
            });
            return;
        }

        const $trigger = $(this);
        if ($trigger.data('loading') === true) {
            notification({
                status: 403,
                message: 'File sedang proses validasi Bsre, Mohon tunggu hingga proses validasi selesai'
            });
            return;
        }

        setSignContext($trigger);
        if (!hasSignContext()) {
            notification({
                status: 400,
                message: 'Data dokumen tidak valid.'
            });
            return;
        }

        signModalValidationInProgress = true;
        setSignModalLoading($trigger, true);
        let openModalSign = false;
        try {
            openModalSign = await resetPdfFromUrl(_doc);
        } catch (err) {
            notification({
                status: 500,
                message: 'File gagal proses memuat PDF, Hubungi developer untuk bantuan'
            });
            openModalSign = false;
        }
        setSignModalLoading($trigger, false);
        signModalValidationInProgress = false;

        if (openModalSign) {
            modalSign.show();
        }
    });

    $('#ConfirmSign').on('hidden.bs.modal', function() {
        $('#Tokenize').val('');
        $('#reason').val('Dokumen telah disetujui dan ditandatangani secara elektronik');
        $('#Exchange').val('');
    });
</script>
