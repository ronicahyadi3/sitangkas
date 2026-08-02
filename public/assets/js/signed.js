const modalSign = new bootstrap.Modal(document.getElementById("signModal"));
const BtnExecuted = document.getElementById("SignButton");
const ConfirmSign = new bootstrap.Modal(document.getElementById("ConfirmSign"));
const signUiState = {
    requestInFlight: false,
    locked: false,
    notificationAnimating: false,
    notificationTimer: null,
    countdownInterval: null,
};

function setSignControlsDisabled(disabled) {
    $("#SignButton").prop("disabled", disabled);
    $("#Exchange").prop("disabled", disabled);
    $("#category").prop("disabled", disabled);
    $("#Tokenize").prop("disabled", disabled);
}

function setSignLocked(locked) {
    signUiState.locked = locked;
    setSignControlsDisabled(
        signUiState.requestInFlight || locked || signUiState.notificationAnimating
    );
}

function isSignInteractionLocked() {
    return signUiState.requestInFlight || signUiState.locked || signUiState.notificationAnimating;
}

function SignProcess() {
    if (isSignInteractionLocked()) {
        return false;
    }

    signUiState.requestInFlight = true;
    setSignLocked(false);
    $("#spinner").removeClass("d-none");
    $("#signText").text("Signing...");
    return true;
}

function SignSuccess(data) {
    signUiState.requestInFlight = false;
    $("#spinner").addClass("d-none");
    $("#signText").text("Signed");
    setSignLocked(true);
    startCountdown(3);
}

function SignFailed(message) {
    signUiState.requestInFlight = false;
    $("#spinner").addClass("d-none");
    $("#signText").text("Sign Failed");
    signUiState.notificationAnimating = true;
    setSignControlsDisabled(true);
    jQuery("#sign-notification").text(message);
    jQuery("#sign-notification").show();
    showNotification("sign-notification", 3000, function () {
        signUiState.notificationAnimating = false;
        SignReset();
    });
}

function SignReset() {
    $("#spinner").addClass("d-none");
    $("#signText").text("Sign Now");
    signUiState.requestInFlight = false;
    signUiState.locked = false;
    setSignControlsDisabled(false);
}

function showNotification(id, duration = 5000, onDone = null) {
    const element = document.getElementById(id);
    if (!element) return;

    if (signUiState.notificationTimer) {
        clearTimeout(signUiState.notificationTimer);
        signUiState.notificationTimer = null;
    }

    $("#" + id).stop(true, true);
    element.style.opacity = "1";
    element.style.display = "block";

    signUiState.notificationTimer = window.setTimeout(function () {
        $("#" + id)
            .fadeTo(500, 0)
            .slideUp(500, function () {
                $(this).hide();
                signUiState.notificationTimer = null;
                if (typeof onDone === "function") {
                    onDone();
                } else if (typeof SignReset === "function") {
                    SignReset();
                }
            });
    }, duration);
}

function startCountdown(duration) {
    if (signUiState.countdownInterval) {
        clearInterval(signUiState.countdownInterval);
    }

    $("#countdownBar").css("width", 100 + "%");
    const interval = 100;
    const totalSteps = (duration * 1000) / interval;
    let currentStep = 0;

    $("#progressWrapper").removeClass("d-none");
    $("#countdownWrapper").removeClass("d-none");
    setSignLocked(true);

    signUiState.countdownInterval = setInterval(function () {
        currentStep++;

        let percent = 100 - (currentStep / totalSteps) * 100;
        percent = Math.max(0, percent);

        $("#countdownBar").css("width", percent + "%");

        if (currentStep >= totalSteps) {
            mainTable.ajax.reload();
            tteDocumentTable.ajax.reload();
            ConfirmSign.hide();
            modalSign.hide();
            clearInterval(signUiState.countdownInterval);
            signUiState.countdownInterval = null;
            $("#progressWrapper").addClass("d-none");
            $("#countdownWrapper").addClass("d-none");
            SignReset();
        }
    }, interval);
}

const ModalViewSignature = new bootstrap.Modal(
    document.getElementById("ModalViewSignature")
);

$("#check-signature").click(function () {
    ModalViewSignature.show();
});

function getOffset(el) {
    const rect = el.getBoundingClientRect();
    return {
        left: rect.left + window.scrollX,
        top: rect.top
    };
}

$('#signModal').on('shown.bs.modal', function () {
    const $scroll_modal_top = getOffset(document.getElementById('header-process')).top;
    const $width_val = $(window).width();
    if ($scroll_modal_top >= 0 && $width_val >= 992) {
        $('.fixed-scroll').css({
            position: 'fixed',
            top: $scroll_modal_top,
            left: 0,
            'background-color': 'unset'
        });
    }

    $('#signModal').scroll(function () {
        const $scroll_modal_top = getOffset(document.getElementById('header-process')).top;
        const $width_val = $(window).width();

        if ($scroll_modal_top >= 0 && $width_val >= 992) {
            $('.fixed-scroll').css({
                position: 'fixed',
                top: $scroll_modal_top,
                left: 0,
                'z-index': 1,
                'background-color': 'unset'
            });
        } else if ($scroll_modal_top >= 0 && $width_val <= 992) {
            $('.fixed-scroll').css({
                position: 'static',
                top: 0,
                left: 0,
                'z-index': 1,
                'background-color': 'unset'
            });
        } else {
            $('.fixed-scroll').css({
                position: 'fixed',
                top: 0,
                left: 0,
                'z-index': 1,
                'background-color': 'aliceblue'
            });
        }
    });
});

$('#signModal').scroll();
