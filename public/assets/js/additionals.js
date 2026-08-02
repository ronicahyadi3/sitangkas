////////////////////////////////////////////////////////////////////////////////////////////////////////////
document.addEventListener("DOMContentLoaded", () => {
    if (navigator.platform.includes("Win")) {
        const el = document.getElementById("sidenav-scrollbar");
        if (el && window.Scrollbar) {
            Scrollbar.init(el, { damping: 0.5 });
        }
    }
});
// Light Mode / Dark Mode
function darkMode(el) {
    const body = document.getElementsByTagName("body")[0];
    const hr = document.querySelectorAll("div:not(.sidenav) > hr");
    const sidebar = document.querySelector(".sidenav");
    const sidebarWhite = document.querySelectorAll(".sidenav.bg-white");
    const hr_card = document.querySelectorAll("div:not(.bg-gradient-dark) hr");
    const text_btn = document.querySelectorAll("button:not(.btn) > .text-dark");
    const text_span = document.querySelectorAll(
        "span.text-dark, .breadcrumb .text-dark",
    );
    const text_span_white = document.querySelectorAll("span.text-white");
    const text_strong = document.querySelectorAll("strong.text-dark");
    const text_strong_white = document.querySelectorAll("strong.text-white");
    const text_nav_link = document.querySelectorAll("a.nav-link.text-dark");
    const secondary = document.querySelectorAll(".text-secondary");
    const bg_gray_100 = document.querySelectorAll(".bg-gray-100");
    const bg_gray_600 = document.querySelectorAll(".bg-gray-600");
    const btn_text_dark = document.querySelectorAll(
        ".btn.btn-link.text-dark, .btn .ni.text-dark",
    );
    const btn_text_white = document.querySelectorAll(
        ".btn.btn-link.text-white, .btn .ni.text-white",
    );
    const card_border = document.querySelectorAll(".card.border");
    const card_border_dark = document.querySelectorAll(
        ".card.border.border-dark",
    );
    const svg = document.querySelectorAll("g");
    const navbarBrand = document.querySelector(".navbar-brand-img");
    const navbarBrandImg = navbarBrand ? navbarBrand.src : "";
    const navLinks = document.querySelectorAll(
        ".navbar-main .nav-link, .navbar-main .breadcrumb-item, .navbar-main .breadcrumb-item a, .navbar-main h6",
    );
    const cardNavLinksIcons = document.querySelectorAll(
        ".card .nav .nav-link i",
    );
    const cardNavSpan = document.querySelectorAll(".card .nav .nav-link span");

    if (!el.getAttribute("checked")) {
        body.classList.add("dark-version");
        if (navbarBrandImg.includes("logo-ct-dark.png")) {
            var navbarBrandImgNew = navbarBrandImg.replace(
                "logo-ct-dark",
                "logo-ct",
            );
            navbarBrand.src = navbarBrandImgNew;
        }
        for (var i = 0; i < cardNavLinksIcons.length; i++) {
            if (cardNavLinksIcons[i].classList.contains("text-dark")) {
                cardNavLinksIcons[i].classList.remove("text-dark");
                cardNavLinksIcons[i].classList.add("text-white");
            }
        }
        for (var i = 0; i < cardNavSpan.length; i++) {
            if (cardNavSpan[i].classList.contains("text-sm")) {
                cardNavSpan[i].classList.add("text-white");
            }
        }
        for (var i = 0; i < hr.length; i++) {
            if (hr[i].classList.contains("dark")) {
                hr[i].classList.remove("dark");
                hr[i].classList.add("light");
            }
        }
        for (var i = 0; i < hr_card.length; i++) {
            if (hr_card[i].classList.contains("dark")) {
                hr_card[i].classList.remove("dark");
                hr_card[i].classList.add("light");
            }
        }
        for (var i = 0; i < text_btn.length; i++) {
            if (text_btn[i].classList.contains("text-dark")) {
                text_btn[i].classList.remove("text-dark");
                text_btn[i].classList.add("text-white");
            }
        }
        for (var i = 0; i < text_span.length; i++) {
            if (text_span[i].classList.contains("text-dark")) {
                text_span[i].classList.remove("text-dark");
                text_span[i].classList.add("text-white");
            }
        }
        for (var i = 0; i < text_strong.length; i++) {
            if (text_strong[i].classList.contains("text-dark")) {
                text_strong[i].classList.remove("text-dark");
                text_strong[i].classList.add("text-white");
            }
        }
        for (var i = 0; i < text_nav_link.length; i++) {
            if (text_nav_link[i].classList.contains("text-dark")) {
                text_nav_link[i].classList.remove("text-dark");
                text_nav_link[i].classList.add("text-white");
            }
        }
        for (var i = 0; i < secondary.length; i++) {
            if (secondary[i].classList.contains("text-secondary")) {
                secondary[i].classList.remove("text-secondary");
                secondary[i].classList.add("text-white");
                secondary[i].classList.add("opacity-8");
            }
        }
        for (var i = 0; i < bg_gray_100.length; i++) {
            if (bg_gray_100[i].classList.contains("bg-gray-100")) {
                bg_gray_100[i].classList.remove("bg-gray-100");
                bg_gray_100[i].classList.add("bg-gray-600");
            }
        }
        for (var i = 0; i < btn_text_dark.length; i++) {
            btn_text_dark[i].classList.remove("text-dark");
            btn_text_dark[i].classList.add("text-white");
        }
        for (var i = 0; i < sidebarWhite.length; i++) {
            sidebarWhite[i].classList.remove("bg-white");
        }
        for (var i = 0; i < svg.length; i++) {
            if (svg[i].hasAttribute("fill")) {
                svg[i].setAttribute("fill", "#fff");
            }
        }
        for (var i = 0; i < card_border.length; i++) {
            card_border[i].classList.add("border-dark");
        }
        el.setAttribute("checked", "true");

        // simpan state
        localStorage.setItem("darkMode", "enabled");
    } else {
        body.classList.remove("dark-version");
        if (sidebar) sidebar.classList.add("bg-white");
        if (navbarBrandImg.includes("logo-ct.png")) {
            var navbarBrandImgNew = navbarBrandImg.replace(
                "logo-ct",
                "logo-ct-dark",
            );
            navbarBrand.src = navbarBrandImgNew;
        }
        for (var i = 0; i < navLinks.length; i++) {
            if (navLinks[i].classList.contains("text-dark")) {
                navLinks[i].classList.add("text-white");
                navLinks[i].classList.remove("text-dark");
            }
        }
        for (var i = 0; i < cardNavLinksIcons.length; i++) {
            if (cardNavLinksIcons[i].classList.contains("text-white")) {
                cardNavLinksIcons[i].classList.remove("text-white");
                cardNavLinksIcons[i].classList.add("text-dark");
            }
        }
        for (var i = 0; i < cardNavSpan.length; i++) {
            if (cardNavSpan[i].classList.contains("text-white")) {
                cardNavSpan[i].classList.remove("text-white");
            }
        }
        for (var i = 0; i < hr.length; i++) {
            if (hr[i].classList.contains("light")) {
                hr[i].classList.add("dark");
                hr[i].classList.remove("light");
            }
        }
        for (var i = 0; i < hr_card.length; i++) {
            if (hr_card[i].classList.contains("light")) {
                hr_card[i].classList.add("dark");
                hr_card[i].classList.remove("light");
            }
        }
        for (var i = 0; i < text_btn.length; i++) {
            if (text_btn[i].classList.contains("text-white")) {
                text_btn[i].classList.remove("text-white");
                text_btn[i].classList.add("text-dark");
            }
        }
        for (var i = 0; i < text_span_white.length; i++) {
            if (
                text_span_white[i].classList.contains("text-white") &&
                !text_span_white[i].closest(".sidenav") &&
                !text_span_white[i].closest(".card.bg-gradient-dark")
            ) {
                text_span_white[i].classList.remove("text-white");
                text_span_white[i].classList.add("text-dark");
            }
        }
        for (var i = 0; i < text_strong_white.length; i++) {
            if (text_strong_white[i].classList.contains("text-white")) {
                text_strong_white[i].classList.remove("text-white");
                text_strong_white[i].classList.add("text-dark");
            }
        }
        for (var i = 0; i < secondary.length; i++) {
            if (secondary[i].classList.contains("text-white")) {
                secondary[i].classList.remove("text-white");
                secondary[i].classList.remove("opacity-8");
                secondary[i].classList.add("text-dark");
            }
        }
        for (var i = 0; i < bg_gray_600.length; i++) {
            if (bg_gray_600[i].classList.contains("bg-gray-600")) {
                bg_gray_600[i].classList.remove("bg-gray-600");
                bg_gray_600[i].classList.add("bg-gray-100");
            }
        }
        for (var i = 0; i < svg.length; i++) {
            if (svg[i].hasAttribute("fill")) {
                svg[i].setAttribute("fill", "#252f40");
            }
        }
        for (var i = 0; i < btn_text_white.length; i++) {
            if (!btn_text_white[i].closest(".card.bg-gradient-dark")) {
                btn_text_white[i].classList.remove("text-white");
                btn_text_white[i].classList.add("text-dark");
            }
        }
        for (var i = 0; i < card_border_dark.length; i++) {
            card_border_dark[i].classList.remove("border-dark");
        }
        el.removeAttribute("checked");

        // simpan state
        localStorage.setItem("darkMode", "disabled");
    }
}

function releaseInitialLayoutBoot() {
    document.documentElement.classList.remove("app-dark-mode-pending");

    if (document.body) {
        document.body.classList.remove("app-layout-booting");
    }
}

// Restore state saat load
document.addEventListener("DOMContentLoaded", function () {
    const darkSwitch = document.getElementById("darkModeSwitch");

    try {
        const darkModeSetting = localStorage.getItem("darkMode");

        if (darkModeSetting === "enabled") {
            if (darkSwitch && !darkSwitch.hasAttribute("checked")) {
                darkMode(darkSwitch);
                darkSwitch.checked = true;
            }
        } else {
            if (darkSwitch) {
                darkSwitch.removeAttribute("checked");
                darkSwitch.checked = false;
            }
        }
    } finally {
        window.requestAnimationFrame(releaseInitialLayoutBoot);
    }
});

window.addEventListener("load", releaseInitialLayoutBoot, { once: true });

// Fungsi helper untuk set nama file ke elemen teks
function bindFileName(inputSelector, labelSelector) {
    $(inputSelector).on("change", function () {
        let fileName = "";

        // lebih aman: pakai this.files kalau tersedia
        if (this.files && this.files.length > 0) {
            fileName = this.files[0].name;
        } else {
            // fallback kalau browser lama
            fileName = $(this).val().split("\\").pop();
        }
        $(labelSelector).text(fileName);
    });
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////
// Fungsi untuk membaca file PDF dan mengisi select box
async function readFilePDF(event, selectId, loadingId) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.type !== "application/pdf") {
        notification({
            status: 422,
            message: "File harus berformat PDF.",
        });
        event.target.value = ""; // reset input biar bisa pilih ulang
        return;
    }

    const loadingEl = document.getElementById(loadingId);
    const selectEl = document.getElementById(selectId);

    const formData = new FormData();
    formData.append("file_pdf", file);

    // ambil CSRF token sekali saja
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta
        ? csrfTokenMeta.getAttribute("content")
        : "";

    try {
        // TAMPILKAN loading
        if (loadingEl) loadingEl.style.display = "block";

        const response = await fetch("/pdf/read", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": csrfToken,
            },
            body: formData,
        });

        if (!response.ok) {
            let message = "Gagal memproses file PDF.";
            try {
                const errJson = await response.json();
                message = errJson?.message || message;
            } catch (_) {
                // gunakan default message
            }
            throw new Error(message);
        }

        const data = await response.json();

        if (!Array.isArray(data) || !selectEl) {
            notification({
                status: 422,
                message: "Format respons pembacaan PDF tidak valid.",
            });
            return;
        }

        // Kosongkan select
        selectEl.innerHTML = "";

        const fragment = document.createDocumentFragment();

        // Helper buat nambah option
        const addOption = (value) => {
            const option = document.createElement("option");
            option.value = value;
            option.textContent = value;
            option.className = "text-center";
            fragment.appendChild(option);
        };

        // ===== PARSE NOMOR DOKUMEN SIPD (UNIVERSAL) =====
        const text = data.join(" ");

        // regex universal: SPP, SPM, SP2D, SPTJM, LS, GU, UP, TU, KKPD, dll
        const regexNomorDokumen =
            /\d{2}\.\d{2}\s*\/\s*\d{2}\.\d\s*\/\s*\d{6}\s*\/\s*[A-Z0-9-]+\s*\/\s*[0-9.]+\s*\/\s*M\s*\/\s*\d\s*\/\s*\d{4}/g;

        const matches = text.match(regexNomorDokumen) || [];

        // normalisasi + unique
        const uniqueNumbers = [
            ...new Set(matches.map((m) => m.replace(/\s+/g, ""))),
        ];

        // render ke select
        uniqueNumbers.forEach(addOption);

        // Masukkan semua option ke DOM sekali saja
        selectEl.appendChild(fragment);

        if (uniqueNumbers.length > 0) {
            notification({
                status: 200,
                message: `Berhasil membaca PDF. Ditemukan ${uniqueNumbers.length} nomor dokumen.`,
            });
        } else {
            notification({
                status: 422,
                message: "PDF terbaca, tetapi nomor dokumen belum ditemukan.",
            });
        }
    } catch (error) {
        console.error(error);
        notification({
            status: 500,
            message: error?.message || "Terjadi kesalahan saat memproses PDF.",
        });
    } finally {
        // SEMBUNYIKAN loading
        if (loadingEl) loadingEl.style.display = "none";
    }
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////
//AutoNumeric Init
const COptions = {
    digitGroupSeparator: ".",
    decimalCharacter: ",",
    decimalPlaces: 2,
    decimalPlacesRawValue: 2,
    allowDecimalPadding: true,
    roundingMethod: "S",
    maximumValue: "99999999999999",
    minimumValue: "0",
    currencySymbol: "Rp. ",
    currencySymbolPlacement: "p",
    unformatOnSubmit: true,
};
//////////////////////////////////////////////////////////////////////////////////////////////////////////
//Sweetalert2 Toast
const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
});

const SWAL_ICONS = ["success", "error", "warning", "info", "question"];

function showToast(icon, title) {
    if (!title && typeof icon === "string" && icon.length > 50) {
        title = icon;
        icon = "info";
    }

    if (typeof icon !== "string") {
        console.warn("showToast(): icon bukan string — menerima:", icon);
        if (icon && typeof icon === "object") {
            const maybeStatus = Number(icon.status);
            // map status ke icon
            if (maybeStatus === 200) icon = "success";
            else if (maybeStatus >= 500) icon = "error";
            else icon = "warning";

            if (!title) {
                let msg =
                    icon.message ?? icon.msg ?? icon.error ?? icon.message;
                if (typeof msg === "object") {
                    msg = Array.isArray(msg)
                        ? msg.join(", ")
                        : Object.values(msg).join(" ");
                }
                title = msg || "Informasi";
            }
        } else {
            icon = "info";
        }
    }

    if (!SWAL_ICONS.includes(icon)) {
        console.warn(
            `showToast(): icon tidak valid ("${icon}"), fallback ke "info"`,
        );
        icon = "info";
    }

    if (typeof title === "object") {
        title = Array.isArray(title)
            ? title.join(", ")
            : Object.values(title).join(" ");
    }
    title = String(title ?? "");

    try {
        Toast.fire({ icon, title });
    } catch (e) {
        console.error("showToast() gagal memanggil Toast.fire:", e, {
            icon,
            title,
        });
        alert(title || "Informasi");
    }
}

function notification(response) {
    const status = Number(response?.status) || 500;
    let message =
        response?.message ??
        response?.msg ??
        "Terjadi kesalahan yang tidak diketahui";

    if (typeof message === "object") {
        if (message.errors && typeof message.errors === "object") {
            message = Object.values(message.errors).flat().join("<br>");
        } else {
            message = Array.isArray(message)
                ? message.join(", ")
                : Object.values(message).join("<br>");
        }
    }

    message = String(message).trim();

    if (status >= 200 && status < 300) {
        showToast("success", message || "Sukses");
        return;
    }

    if (status === 401 || status === 403) {
        showToast("error", message || "Akses ditolak");
        return;
    }

    if (status < 500) {
        showToast("warning", message || "Peringatan");
        return;
    }

    showToast("error", message || "Terjadi kesalahan server");
}

document.addEventListener("show.bs.modal", function (event) {
    const modal = event.target;
    const openModals = document.querySelectorAll(".modal.show").length;
    const zIndex = 1050 + openModals * 20;
    modal.style.zIndex = zIndex;
    setTimeout(() => {
        const backdrop = document.querySelector(
            ".modal-backdrop:not(.stacked)",
        );
        if (backdrop) {
            backdrop.style.zIndex = zIndex - 10;
            backdrop.classList.add("stacked");
        }
    });
});

document.addEventListener("hidden.bs.modal", function (event) {
    const modal = event.target;
    modal.style.zIndex = "";
    const openModals = document.querySelectorAll(".modal.show");
    if (openModals.length > 0) {
        document.body.classList.add("modal-open");
    }
});

(function () {
    const activeTimers = new WeakMap();

    function togglePassword(toggleEl) {
        const input = document.querySelector(toggleEl.dataset.target);
        if (!input) return;

        const icon = toggleEl.querySelector("i");
        const isHidden = input.type === "password";

        input.type = isHidden ? "text" : "password";

        icon.classList.toggle("fa-eye", isHidden);
        icon.classList.toggle("fa-eye-slash", !isHidden);

        toggleEl.classList.toggle("active", isHidden);

        if (activeTimers.has(toggleEl)) {
            clearTimeout(activeTimers.get(toggleEl));
            activeTimers.delete(toggleEl);
        }

        const timeout = parseInt(toggleEl.dataset.timeout, 10);
        if (isHidden && timeout > 0) {
            const timer = setTimeout(() => {
                input.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
                toggleEl.classList.remove("active");
                activeTimers.delete(toggleEl);
            }, timeout);

            activeTimers.set(toggleEl, timer);
        }
    }

    document.addEventListener("click", function (e) {
        const toggle = e.target.closest(".toggle-password");
        if (!toggle) return;
        togglePassword(toggle);
    });

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Enter" && e.key !== " ") return;
        const toggle = e.target.closest(".toggle-password");
        if (!toggle) return;
        e.preventDefault();
        togglePassword(toggle);
    });
})();

document.addEventListener("DOMContentLoaded", function () {
    const currentPath = window.location.pathname;

    // semua nav-link
    const navLinks = document.querySelectorAll(".navbar-nav a.nav-link");

    navLinks.forEach((link) => {
        const href = link.getAttribute("href");

        if (!href || href === "#") return;

        // cocokkan path
        if (currentPath === href || currentPath.startsWith(href + "/")) {
            link.classList.add("active");

            // cari parent collapse
            const collapse = link.closest(".collapse");
            if (collapse) {
                collapse.classList.add("show");

                // aktifkan trigger collapse (menu induk)
                const trigger = document.querySelector(
                    `a[href="#${collapse.id}"]`,
                );

                if (trigger) {
                    trigger.classList.add("active");
                    trigger.setAttribute("aria-expanded", "true");
                }
            }
        }
    });
});

document.addEventListener("DOMContentLoaded", () => {
    /* ========= UX (Laravel) ========= */
    const UX = {
        icon: document.getElementById("ux-icon"),
        latency: document.getElementById("ux-latency"),
        status: document.getElementById("ux-status"),
        interval: null,
        idleTimer: null,
        INTERVAL: 2000,
        IDLE: 10000,
    };

    const setUX = (state, ms = "") => {
        UX.latency.textContent = ms ? `${ms} ms` : "–";
        UX.status.textContent = state;

        const map = {
            Stabil: "success",
            Normal: "warning",
            Lemot: "danger",
            Offline: "danger",
        };

        const color = map[state] || "secondary";

        [UX.icon, UX.latency, UX.status].forEach((el) => {
            el.className = el.className.replace(/text-\w+/g, "");
            el.classList.add(`text-${color}`);
        });
        UX.icon.classList.add(`text-sm`);

        UX.icon.classList.toggle("fa-wifi-slash", state === "Offline");
        UX.icon.classList.toggle("fa-signal", state !== "Offline");
    };

    const pingUX = async () => {
        const t = performance.now();
        try {
            await fetch("/ping", { method: "HEAD", cache: "no-store" });
            const ms = Math.round(performance.now() - t);
            if (ms < 200) setUX("Stabil", ms);
            else if (ms < 500) setUX("Normal", ms);
            else setUX("Lemot", ms);
        } catch {
            setUX("Offline");
        }
    };

    const startUX = () => {
        if (UX.interval) return;
        pingUX();
        UX.interval = setInterval(pingUX, UX.INTERVAL);
    };

    const stopUX = () => {
        clearInterval(UX.interval);
        UX.interval = null;
    };

    const resetIdle = () => {
        clearTimeout(UX.idleTimer);
        UX.idleTimer = setTimeout(stopUX, UX.IDLE);
    };

    /* ========= INFRA (Native PHP) ========= */
    const INFRA = {
        icon: document.getElementById("infra-icon"),
        latency: document.getElementById("infra-latency"),
        status: document.getElementById("infra-status"),
        interval: null,
        INTERVAL: 2000,
    };

    const setInfra = (state, ms = "") => {
        INFRA.latency.textContent = ms ? `${ms} ms` : "–";
        INFRA.status.textContent = state;

        const map = {
            Stabil: "success",
            Normal: "warning",
            Lemot: "danger",
            Down: "danger",
        };

        const color = map[state] || "secondary";

        [INFRA.icon, INFRA.latency, INFRA.status].forEach((el) => {
            el.className = el.className.replace(/text-\w+/g, "");
            el.classList.add(`text-${color}`);
        });
        INFRA.icon.classList.add(`text-sm`);
    };

    const pingInfra = async () => {
        const t = performance.now();
        try {
            await fetch("/ping-native.php", {
                method: "HEAD",
                cache: "no-store",
            });
            const ms = Math.round(performance.now() - t);
            if (ms < 50) setInfra("Stabil", ms);
            else if (ms < 150) setInfra("Normal", ms);
            else setInfra("Lemot", ms);
        } catch {
            setInfra("Down");
        }
    };

    const startInfra = () => {
        if (INFRA.interval) return;
        pingInfra();
        INFRA.interval = setInterval(pingInfra, INFRA.INTERVAL);
    };

    const stopInfra = () => {
        clearInterval(INFRA.interval);
        INFRA.interval = null;
    };

    /* ========= EVENTS ========= */
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
            startUX();
            startInfra();
            resetIdle();
        } else {
            stopUX();
            stopInfra();
        }
    });

    ["mousemove", "keydown", "scroll", "click"].forEach((e) =>
        document.addEventListener(
            e,
            () => {
                resetIdle();
                startUX();
            },
            { passive: true },
        ),
    );

    startUX();
    startInfra();
    resetIdle();
});
