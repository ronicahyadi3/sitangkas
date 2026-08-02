<script>
    (function() {
        const storageKey = 'darkMode';
        const darkValue = 'enabled';
        const lightValue = 'disabled';

        function storedTheme() {
            try {
                return localStorage.getItem(storageKey);
            } catch (error) {
                return null;
            }
        }

        function saveTheme(isDark) {
            try {
                localStorage.setItem(storageKey, isDark ? darkValue : lightValue);
            } catch (error) {
                // Keep the toggle working visually even when storage is unavailable.
            }
        }

        function updateThemeColor(isDark) {
            const themeMeta = document.querySelector('meta[name="theme-color"]');

            if (themeMeta) {
                themeMeta.setAttribute('content', isDark ? '#051139' : '#1b4f8c');
            }
        }

        function updateToggle(button, isDark) {
            const label = button.querySelector('[data-theme-toggle-text]');
            const actionText = isDark ? 'Mode Terang' : 'Mode Gelap';

            button.classList.toggle('is-dark', isDark);
            button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            button.setAttribute('aria-label', isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
            button.setAttribute('title', isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');

            if (label) {
                label.textContent = actionText;
            }
        }

        function applyTheme(isDark, shouldPersist) {
            document.documentElement.classList.toggle('app-dark-mode-pending', isDark);

            if (document.body) {
                document.body.classList.toggle('dark-version', isDark);
            }

            updateThemeColor(isDark);
            document.querySelectorAll('[data-public-theme-toggle]').forEach((button) => updateToggle(button, isDark));

            if (shouldPersist) {
                saveTheme(isDark);
            }
        }

        function toggleMarkup() {
            return `
                <span class="public-theme-toggle__icon" aria-hidden="true">
                    <i class="fa-solid fa-moon" data-theme-icon="dark"></i>
                    <i class="fa-solid fa-sun" data-theme-icon="light"></i>
                </span>
                <span class="public-theme-toggle__text" data-theme-toggle-text></span>
            `;
        }

        function bindToggle(button) {
            if (button.dataset.publicThemeToggleBound === '1') {
                return;
            }

            button.dataset.publicThemeToggleBound = '1';
            button.addEventListener('click', function() {
                applyTheme(!document.body.classList.contains('dark-version'), true);
            });
            updateToggle(button, document.body.classList.contains('dark-version'));
        }

        function buildToggle(variant) {
            const isStandalonePage = document.querySelector('.landing-wrapper, .about-wrapper, .auth-shell');

            if (!isStandalonePage) {
                return null;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = variant === 'icon' ?
                'public-theme-toggle public-theme-toggle--icon' :
                'public-theme-toggle public-theme-toggle--nav';
            button.setAttribute('data-public-theme-toggle', '');
            button.innerHTML = toggleMarkup();

            return button;
        }

        function createPublicToggle() {
            const mounts = document.querySelectorAll('[data-public-theme-toggle-mount]');

            if (mounts.length) {
                mounts.forEach((mount) => {
                    if (!mount.querySelector('[data-public-theme-toggle]')) {
                        const button = buildToggle(mount.dataset.publicThemeToggleVariant || 'nav');

                        if (button) {
                            mount.appendChild(button);
                        }
                    }
                });

                document.querySelectorAll('[data-public-theme-toggle]').forEach(bindToggle);
                return;
            }

            const button = buildToggle('nav');

            if (button) {
                button.classList.remove('public-theme-toggle--nav');
                button.classList.add('public-theme-toggle--floating');
                document.body.appendChild(button);
                bindToggle(button);
            }
        }

        const initialDarkMode = storedTheme() === darkValue;

        try {
            if (initialDarkMode) {
                document.documentElement.classList.add('app-dark-mode-pending');
            }
        } catch (error) {
            // Keep standalone pages usable when localStorage is unavailable.
        }

        document.addEventListener('DOMContentLoaded', function() {
            applyTheme(initialDarkMode, false);
            createPublicToggle();
        });
    })();
</script>

@include('inc.dark-mode-overrides')

<style>
    .public-theme-toggle-slot {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }

    .public-theme-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        min-height: 40px;
        padding: 0 !important;
        border: 1px solid rgba(15, 47, 87, 0.1);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.86);
        color: #1b4f8c;
        box-shadow: 0 .55rem 1.25rem rgba(15, 47, 87, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        font-size: .95rem;
        line-height: 1;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease, color .18s ease, border-color .18s ease;
    }

    .public-theme-toggle--floating {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 1095;
        max-width: calc(100vw - 2rem);
        width: 44px;
        height: 44px;
        min-height: 44px;
    }

    .public-theme-toggle--icon {
        width: 42px;
        height: 42px;
        min-height: 42px;
        border-radius: 14px;
    }

    .public-theme-toggle:hover {
        transform: translateY(-1px);
        border-color: rgba(47, 141, 243, 0.28);
        background: #fff;
        box-shadow: 0 .75rem 1.5rem rgba(15, 47, 87, 0.16);
    }

    .public-theme-toggle:focus-visible {
        outline: 3px solid rgba(47, 141, 243, 0.28);
        outline-offset: 3px;
    }

    .public-theme-toggle__icon {
        width: 100%;
        height: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: inherit;
    }

    .public-theme-toggle__text {
        display: none;
    }

    .public-theme-toggle [data-theme-icon] {
        display: none;
    }

    .public-theme-toggle:not(.is-dark) [data-theme-icon="dark"],
    .public-theme-toggle.is-dark [data-theme-icon="light"] {
        display: inline-block;
    }

    html.app-dark-mode-pending .public-theme-toggle,
    body.dark-version .public-theme-toggle {
        background: rgba(17, 28, 68, 0.9) !important;
        border-color: rgba(148, 163, 184, 0.22) !important;
        color: #e2e8f0 !important;
        box-shadow: 0 .75rem 1.75rem rgba(0, 0, 0, 0.28) !important;
    }

    html.app-dark-mode-pending .public-theme-toggle__icon,
    body.dark-version .public-theme-toggle__icon {
        color: #fde68a !important;
    }

    html.app-dark-mode-pending,
    body.dark-version {
        --standalone-dark-bg: #051139;
        --standalone-dark-surface: rgba(17, 28, 68, 0.92);
        --standalone-dark-surface-solid: #111c44;
        --standalone-dark-surface-soft: #0b1637;
        --standalone-dark-border: rgba(148, 163, 184, 0.18);
        --standalone-dark-text: #e2e8f0;
        --standalone-dark-heading: #f8fafc;
        --standalone-dark-muted: #a8b3c7;
        --standalone-dark-primary: #9fb0ff;
        color-scheme: dark;
    }

    html.app-dark-mode-pending body,
    body.dark-version {
        background:
            radial-gradient(circle at top left, rgba(47, 141, 243, 0.18), transparent 26%),
            radial-gradient(circle at bottom right, rgba(34, 181, 115, 0.12), transparent 24%),
            linear-gradient(135deg, #051139 0%, #081b45 52%, #06142f 100%) !important;
        color: var(--standalone-dark-text) !important;
    }

    html.app-dark-mode-pending .auth-shell,
    body.dark-version .auth-shell,
    html.app-dark-mode-pending .landing-wrapper,
    body.dark-version .landing-wrapper,
    html.app-dark-mode-pending .about-wrapper,
    body.dark-version .about-wrapper {
        background: rgba(17, 28, 68, 0.88) !important;
        border-color: var(--standalone-dark-border) !important;
        box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, 0.32) !important;
    }

    html.app-dark-mode-pending .soft-card,
    body.dark-version .soft-card,
    html.app-dark-mode-pending .app-mini-info,
    body.dark-version .app-mini-info,
    html.app-dark-mode-pending .feature-card,
    body.dark-version .feature-card,
    html.app-dark-mode-pending .showcase-card,
    body.dark-version .showcase-card,
    html.app-dark-mode-pending .app-window,
    body.dark-version .app-window,
    html.app-dark-mode-pending .info-glass-card,
    body.dark-version .info-glass-card,
    html.app-dark-mode-pending .mobile-nav-card,
    body.dark-version .mobile-nav-card,
    html.app-dark-mode-pending .metric-box,
    body.dark-version .metric-box,
    html.app-dark-mode-pending .section-surface,
    body.dark-version .section-surface,
    html.app-dark-mode-pending .outline-card,
    body.dark-version .outline-card,
    html.app-dark-mode-pending .document-card,
    body.dark-version .document-card,
    html.app-dark-mode-pending .mockup-card,
    body.dark-version .mockup-card,
    html.app-dark-mode-pending .mockup-frame,
    body.dark-version .mockup-frame,
    html.app-dark-mode-pending .mini-metric,
    body.dark-version .mini-metric,
    html.app-dark-mode-pending .hero-trust-item,
    body.dark-version .hero-trust-item,
    html.app-dark-mode-pending .timeline-item,
    body.dark-version .timeline-item,
    html.app-dark-mode-pending .contact-item,
    body.dark-version .contact-item,
    html.app-dark-mode-pending .dropdown-menu,
    body.dark-version .dropdown-menu,
    html.app-dark-mode-pending .modal-content,
    body.dark-version .modal-content {
        background: var(--standalone-dark-surface) !important;
        border-color: var(--standalone-dark-border) !important;
        color: var(--standalone-dark-text) !important;
        box-shadow: 0 1rem 2.75rem rgba(0, 0, 0, 0.26) !important;
    }

    html.app-dark-mode-pending .auth-left,
    body.dark-version .auth-left,
    html.app-dark-mode-pending .showcase-top,
    body.dark-version .showcase-top,
    html.app-dark-mode-pending .mockup-top,
    body.dark-version .mockup-top,
    html.app-dark-mode-pending .cta-panel,
    body.dark-version .cta-panel {
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.12), transparent 24%),
            linear-gradient(145deg, #071f4f 0%, #102f72 52%, #0b6c8f 100%) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    html.app-dark-mode-pending .landing-wrapper::before,
    body.dark-version .landing-wrapper::before,
    html.app-dark-mode-pending .about-wrapper::before,
    body.dark-version .about-wrapper::before {
        background: radial-gradient(circle, rgba(47, 141, 243, 0.16), transparent 70%) !important;
    }

    html.app-dark-mode-pending .landing-wrapper::after,
    body.dark-version .landing-wrapper::after,
    html.app-dark-mode-pending .about-wrapper::after,
    body.dark-version .about-wrapper::after {
        background: radial-gradient(circle, rgba(34, 181, 115, 0.14), transparent 70%) !important;
    }

    html.app-dark-mode-pending .login-heading,
    body.dark-version .login-heading,
    html.app-dark-mode-pending .brand-title,
    body.dark-version .brand-title,
    html.app-dark-mode-pending .hero-title,
    body.dark-version .hero-title,
    html.app-dark-mode-pending .feature-title,
    body.dark-version .feature-title,
    html.app-dark-mode-pending .info-title,
    body.dark-version .info-title,
    html.app-dark-mode-pending .section-title,
    body.dark-version .section-title,
    html.app-dark-mode-pending .card-title-custom,
    body.dark-version .card-title-custom,
    html.app-dark-mode-pending .timeline-title,
    body.dark-version .timeline-title,
    html.app-dark-mode-pending .mini-metric-value,
    body.dark-version .mini-metric-value,
    html.app-dark-mode-pending .metric-value,
    body.dark-version .metric-value,
    html.app-dark-mode-pending .contact-value,
    body.dark-version .contact-value,
    html.app-dark-mode-pending .contact-value a,
    body.dark-version .contact-value a,
    html.app-dark-mode-pending .dropdown-item,
    body.dark-version .dropdown-item,
    html.app-dark-mode-pending .modal-title,
    body.dark-version .modal-title,
    html.app-dark-mode-pending .text-dark,
    body.dark-version .text-dark {
        color: var(--standalone-dark-heading) !important;
    }

    html.app-dark-mode-pending .login-subheading,
    body.dark-version .login-subheading,
    html.app-dark-mode-pending .text-soft,
    body.dark-version .text-soft,
    html.app-dark-mode-pending .brand-subtitle,
    body.dark-version .brand-subtitle,
    html.app-dark-mode-pending .hero-desc,
    body.dark-version .hero-desc,
    html.app-dark-mode-pending .hero-lead,
    body.dark-version .hero-lead,
    html.app-dark-mode-pending .feature-text,
    body.dark-version .feature-text,
    html.app-dark-mode-pending .info-text,
    body.dark-version .info-text,
    html.app-dark-mode-pending .card-text-custom,
    body.dark-version .card-text-custom,
    html.app-dark-mode-pending .section-desc,
    body.dark-version .section-desc,
    html.app-dark-mode-pending .document-meta,
    body.dark-version .document-meta,
    html.app-dark-mode-pending .timeline-text,
    body.dark-version .timeline-text,
    html.app-dark-mode-pending .contact-label,
    body.dark-version .contact-label,
    html.app-dark-mode-pending .footer-mini,
    body.dark-version .footer-mini,
    html.app-dark-mode-pending .modal-body,
    body.dark-version .modal-body,
    html.app-dark-mode-pending .mobile-nav-link,
    body.dark-version .mobile-nav-link {
        color: var(--standalone-dark-muted) !important;
    }

    html.app-dark-mode-pending .login-chip,
    body.dark-version .login-chip,
    html.app-dark-mode-pending .chip,
    body.dark-version .chip,
    html.app-dark-mode-pending .mobile-chip,
    body.dark-version .mobile-chip,
    html.app-dark-mode-pending .hero-badge,
    body.dark-version .hero-badge,
    html.app-dark-mode-pending .mini-point,
    body.dark-version .mini-point,
    html.app-dark-mode-pending .hero-trust-item,
    body.dark-version .hero-trust-item,
    html.app-dark-mode-pending .mobile-nav-link,
    body.dark-version .mobile-nav-link,
    html.app-dark-mode-pending .mobile-nav-action,
    body.dark-version .mobile-nav-action {
        background: rgba(148, 163, 184, 0.12) !important;
        border-color: var(--standalone-dark-border) !important;
        color: var(--standalone-dark-text) !important;
    }

    html.app-dark-mode-pending .nav-mini-link,
    body.dark-version .nav-mini-link {
        color: var(--standalone-dark-muted) !important;
    }

    html.app-dark-mode-pending .nav-mini-link:hover,
    body.dark-version .nav-mini-link:hover,
    html.app-dark-mode-pending .nav-mini-link.active,
    body.dark-version .nav-mini-link.active,
    html.app-dark-mode-pending .dropdown-item:hover,
    body.dark-version .dropdown-item:hover,
    html.app-dark-mode-pending .mobile-nav-link:hover,
    body.dark-version .mobile-nav-link:hover,
    html.app-dark-mode-pending .mobile-nav-action:hover,
    body.dark-version .mobile-nav-action:hover {
        background: rgba(94, 114, 228, 0.18) !important;
        color: var(--standalone-dark-heading) !important;
    }

    html.app-dark-mode-pending .form-label,
    body.dark-version .form-label {
        color: var(--standalone-dark-text) !important;
    }

    html.app-dark-mode-pending .input-group-text,
    body.dark-version .input-group-text,
    html.app-dark-mode-pending .form-control,
    body.dark-version .form-control,
    html.app-dark-mode-pending .form-select,
    body.dark-version .form-select,
    html.app-dark-mode-pending .select2-container--bootstrap-5 .select2-selection,
    body.dark-version .select2-container--bootstrap-5 .select2-selection {
        background-color: var(--standalone-dark-surface-soft) !important;
        border-color: var(--standalone-dark-border) !important;
        color: var(--standalone-dark-heading) !important;
    }

    html.app-dark-mode-pending .form-control::placeholder,
    body.dark-version .form-control::placeholder {
        color: #7f8fa8 !important;
        opacity: 1 !important;
    }

    html.app-dark-mode-pending .toggle-password,
    body.dark-version .toggle-password {
        background-color: var(--standalone-dark-surface-soft) !important;
        color: var(--standalone-dark-muted) !important;
    }

    html.app-dark-mode-pending .btn-white,
    body.dark-version .btn-white {
        background: rgba(255, 255, 255, 0.12) !important;
        border-color: rgba(255, 255, 255, 0.18) !important;
        color: #fff !important;
    }

    html.app-dark-mode-pending .btn-close,
    body.dark-version .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    html.app-dark-mode-pending .divider-soft,
    body.dark-version .divider-soft,
    html.app-dark-mode-pending .modal-header,
    body.dark-version .modal-header,
    html.app-dark-mode-pending .modal-footer,
    body.dark-version .modal-footer,
    html.app-dark-mode-pending .timeline-wrap::before,
    body.dark-version .timeline-wrap::before {
        border-color: var(--standalone-dark-border) !important;
    }

    body.dark-version .select2-dropdown {
        background-color: var(--standalone-dark-surface-solid) !important;
        border-color: var(--standalone-dark-border) !important;
    }

    body.dark-version .select2-results__option {
        color: var(--standalone-dark-text) !important;
    }

    body.dark-version .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #5e72e4 !important;
        color: #fff !important;
    }

    @media (max-width: 575.98px) {
        .public-theme-toggle--floating {
            right: .85rem;
            bottom: .85rem;
        }
    }
</style>
