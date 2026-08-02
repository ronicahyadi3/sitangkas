<script>
    const fieldBmdHtml = `
        <label class="form-control-label pe-2">Pilih Jenis Belanja </label>
    <div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" id="belanja1" name="belanja[]" type="checkbox" value="1">
            <label class="custom-control-label" for="belanja1">Belanja Modal</label>
        </div>

        <div class="form-check form-check-inline">
            <input class="form-check-input" id="belanja2" name="belanja[]" type="checkbox" value="2">
            <label class="custom-control-label" for="belanja2">Belanja Persediaan</label>
        </div>

        <div class="form-check form-check-inline">
            <input class="form-check-input" id="belanja3" name="belanja[]" type="checkbox" value="3">
            <label class="custom-control-label" for="belanja3">Belanja Lainnya</label>
        </div>
        <div class="input-group">
            <span class="input-group-text pe-3"><i class="fa-solid fa-file-pdf fa-lg"></i></span>
            <input type="text" class="form-control" id="name_bmd" name="name_bmd" readonly>
            <label class="btn btn-primary mb-0" for="file_bmd" id="uploadBtn">Upload</label>
            <input type="file" id="file_bmd" name="file_bmd" accept="application/pdf" class="d-none" />
        </div>
        <small class="w-100 ml-2">Nama file : <span id="fileName_bmd">Belum ada file yang
                dipilih</span></small>
    </div>
    `;

    $('#fieldBmd').html(fieldBmdHtml);
</script>

<script>
    const belanjaModal = $('#belanja1');
    const belanjaPersediaan = $('#belanja2');
    const belanjaLainnya = $('#belanja3');
    const fileInput = $('#file_bmd');
    const uploadButton = $('#uploadBtn');
    const nameBmd = $('#name_bmd');
    const fileNameBmd = $('#fileName_bmd');
    window.bmdFieldState = window.bmdFieldState || {
        hasExistingFile: false,
        originalRequiresFile: false,
    };
    bindFileName('#file_bmd', '#name_bmd');


    function toggleFileInput() {
        const isModalChecked = belanjaModal.prop('checked');
        const isPersediaanChecked = belanjaPersediaan.prop('checked');
        if (isModalChecked || isPersediaanChecked) {
            fileInput.prop('disabled', false);
            uploadButton.removeClass('disabled bg-secondary').addClass('bg-primary text-white');
        } else {
            fileInput.prop('disabled', true);
            uploadButton.addClass('disabled bg-secondary').removeClass('bg-primary text-white');
        }
    }
    belanjaModal.on('change', toggleFileInput);
    belanjaPersediaan.on('change', toggleFileInput);
    belanjaLainnya.on('change', toggleFileInput);
    $(document).ready(toggleFileInput);

    function isBmdRequiredByBelanja() {
        return belanjaModal.prop('checked') || belanjaPersediaan.prop('checked');
    }

    function setBmdExistingFile(hasExistingFile) {
        window.bmdFieldState.hasExistingFile = Boolean(hasExistingFile);

        if (fileInput[0]?.files?.length > 0) {
            return;
        }

        if (window.bmdFieldState.hasExistingFile) {
            nameBmd.val('Kosongkan jika file tidak diganti');
            fileNameBmd.text('Kosongkan jika file tidak diganti');
            return;
        }

        nameBmd.val('');
        fileNameBmd.text('Belum ada file yang dipilih');
    }

    function setBmdOriginalRequirement(requiresFile) {
        window.bmdFieldState.originalRequiresFile = Boolean(requiresFile);
    }

    function clearSelectedBmdFile() {
        fileInput.val('');
        setBmdExistingFile(window.bmdFieldState.hasExistingFile);
    }

    function resetBmdState() {
        window.bmdFieldState.hasExistingFile = false;
        window.bmdFieldState.originalRequiresFile = false;
        clearSelectedBmdFile();
        toggleFileInput();
    }

    function validateBmdRequirement(options = {}) {
        const allowExisting = Boolean(options.allowExisting);
        const enforceTransitionUpload = Boolean(options.enforceTransitionUpload);
        const message = options.message ||
            'File BMD harus diupload jika memilih Belanja Modal atau Belanja Persediaan.';
        const hasSelectedFile = (fileInput[0]?.files?.length ?? 0) > 0;
        const hasExistingFile = allowExisting && window.bmdFieldState.hasExistingFile;
        const isTransitioningToRequired = !window.bmdFieldState.originalRequiresFile && isBmdRequiredByBelanja();

        if (!isBmdRequiredByBelanja()) {
            return true;
        }

        if (enforceTransitionUpload && isTransitioningToRequired && !hasSelectedFile) {
            notification({
                status: 422,
                message: 'File BMD wajib diupload karena belanja diubah ke Belanja Modal atau Belanja Persediaan.'
            });
            return false;
        }

        if (hasSelectedFile || hasExistingFile) {
            return true;
        }

        notification({
            status: 422,
            message
        });
        return false;
    }

    window.isBmdRequiredByBelanja = isBmdRequiredByBelanja;
    window.setBmdExistingFile = setBmdExistingFile;
    window.setBmdOriginalRequirement = setBmdOriginalRequirement;
    window.resetBmdState = resetBmdState;
    window.validateBmdRequirement = validateBmdRequirement;

    function checkBelanjaAndResetFile() {
        const checkedValues = $('input[name="belanja[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (checkedValues.length === 1 && checkedValues.includes('3')) {
            clearSelectedBmdFile();
        }
    }
    $('input[name="belanja[]"]').on('change', checkBelanjaAndResetFile);
    fileInput.on('change', function() {
        if (fileInput[0]?.files?.length > 0) {
            window.bmdFieldState.hasExistingFile = true;
            fileNameBmd.text(fileInput[0].files[0]?.name ?? 'Belum ada file yang dipilih');
            return;
        }

        setBmdExistingFile(window.bmdFieldState.hasExistingFile);
    });
</script>
