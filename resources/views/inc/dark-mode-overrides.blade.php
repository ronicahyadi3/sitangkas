<style>
    html.app-dark-mode-pending,
    body.dark-version {
        --app-dark-bg: #051139;
        --app-dark-surface: #111c44;
        --app-dark-surface-2: #0b1637;
        --app-dark-surface-3: #17224d;
        --app-dark-border: rgba(148, 163, 184, 0.18);
        --app-dark-border-strong: rgba(148, 163, 184, 0.3);
        --app-dark-text: #e2e8f0;
        --app-dark-heading: #f8fafc;
        --app-dark-muted: #a8b3c7;
        --app-dark-soft: #7f8fa8;
        --app-dark-primary: #8ea2ff;
    }

    body.dark-version,
    html.app-dark-mode-pending body {
        background-color: var(--app-dark-bg);
    }

    body.dark-version .main-content,
    body.dark-version .container-fluid.py-4 {
        color: var(--app-dark-text);
    }

    body.dark-version .min-height-300.bg-primary {
        background: linear-gradient(135deg, #10256f 0%, #0b5d74 55%, #0b3b5a 100%) !important;
    }

    body.dark-version :where(
        .card:not([class*="bg-gradient"]),
        .modal-content,
        .dropdown-menu,
        .offcanvas,
        .toast,
        .accordion-item,
        .accordion-button,
        .list-group-item,
        .shadow-lg_test
    ) {
        background-color: var(--app-dark-surface) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text);
        box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.22) !important;
    }

    body.dark-version .card.card-plain,
    body.dark-version .card.bg-transparent {
        background-color: transparent !important;
        box-shadow: none !important;
    }

    body.dark-version :where(
        .card .card-header,
        .card .card-footer,
        .modal-header,
        .modal-footer,
        .accordion-header,
        .accordion-body
    ) {
        background-color: transparent !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text);
    }

    body.dark-version .modal-body,
    body.dark-version .dropdown-item,
    body.dark-version .nav-link,
    body.dark-version .form-label,
    body.dark-version .form-control-label,
    body.dark-version legend,
    body.dark-version label {
        color: var(--app-dark-text) !important;
    }

    body.dark-version h1,
    body.dark-version h2,
    body.dark-version h3,
    body.dark-version h4,
    body.dark-version h5,
    body.dark-version h6,
    body.dark-version .h1,
    body.dark-version .h2,
    body.dark-version .h3,
    body.dark-version .h4,
    body.dark-version .h5,
    body.dark-version .h6,
    body.dark-version .text-body,
    body.dark-version .text-dark,
    body.dark-version .text-black,
    body.dark-version .fw-bold.text-dark,
    body.dark-version .font-weight-bolder.text-dark,
    body.dark-version .modal-title,
    body.dark-version .card-title,
    body.dark-version .dropdown-header {
        color: var(--app-dark-heading) !important;
    }

    body.dark-version :where(
        .text-muted,
        .text-secondary,
        .card p,
        .modal-body p,
        small,
        .small,
        .dropdown-item-text
    ) {
        color: var(--app-dark-muted) !important;
        opacity: 1 !important;
    }

    body.dark-version .text-primary:not(.text-gradient),
    body.dark-version a:not(.btn):not(.nav-link):not(.dropdown-item) {
        color: var(--app-dark-primary) !important;
    }

    body.dark-version .bg-white,
    body.dark-version .bg-gray-100,
    body.dark-version .bg-gray-200,
    body.dark-version .bg-light,
    body.dark-version .table-light,
    body.dark-version .badge.bg-light {
        background-color: rgba(148, 163, 184, 0.14) !important;
        color: var(--app-dark-text) !important;
        border-color: var(--app-dark-border) !important;
    }

    body.dark-version .border,
    body.dark-version .border-top,
    body.dark-version .border-end,
    body.dark-version .border-bottom,
    body.dark-version .border-start,
    body.dark-version .border-0.border,
    body.dark-version .rounded-3.border,
    body.dark-version .table-bordered,
    body.dark-version .table-bordered > :not(caption) > * {
        border-color: var(--app-dark-border) !important;
    }

    body.dark-version hr,
    body.dark-version .dropdown-divider,
    body.dark-version hr.horizontal.dark {
        background-image: none !important;
        background-color: var(--app-dark-border) !important;
        opacity: 1;
    }

    body.dark-version .table {
        --bs-table-bg: transparent;
        --bs-table-color: var(--app-dark-text);
        --bs-table-border-color: var(--app-dark-border);
        --bs-table-striped-bg: rgba(148, 163, 184, 0.08);
        --bs-table-striped-color: var(--app-dark-text);
        --bs-table-hover-bg: rgba(94, 114, 228, 0.12);
        --bs-table-hover-color: var(--app-dark-heading);
        color: var(--app-dark-text) !important;
        border-color: var(--app-dark-border) !important;
    }

    body.dark-version .table > :not(caption) > * > * {
        background-color: transparent !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text) !important;
    }

    body.dark-version .table thead th,
    body.dark-version .table .thead-light th,
    body.dark-version .table th {
        color: var(--app-dark-heading) !important;
        background-color: rgba(148, 163, 184, 0.12) !important;
        border-color: var(--app-dark-border) !important;
    }

    body.dark-version .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(148, 163, 184, 0.08) !important;
    }

    body.dark-version .table-hover > tbody > tr:hover > *,
    body.dark-version table.dataTable tbody tr:hover > * {
        background-color: rgba(94, 114, 228, 0.14) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .table-responsive,
    body.dark-version .dataTables_wrapper,
    body.dark-version .dt-container {
        color: var(--app-dark-text);
    }

    body.dark-version .dt-container .dt-info,
    body.dark-version .dt-container .dt-length label,
    body.dark-version .dt-container .dt-search label,
    body.dark-version .dataTables_info,
    body.dark-version .dataTables_length label,
    body.dark-version .dataTables_filter label {
        color: var(--app-dark-muted) !important;
    }

    body.dark-version .dt-container .dt-input,
    body.dark-version .dataTables_filter input,
    body.dark-version .dataTables_length select {
        background-color: var(--app-dark-surface-2) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .dt-container .dt-processing,
    body.dark-version .dataTables_processing {
        background: rgba(17, 28, 68, 0.92) !important;
        border: 1px solid var(--app-dark-border) !important;
        color: var(--app-dark-heading) !important;
        box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.28) !important;
    }

    body.dark-version .page-link,
    body.dark-version .pagination .page-link,
    body.dark-version .dt-container .dt-paging .dt-paging-button {
        background-color: var(--app-dark-surface-2) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text) !important;
    }

    body.dark-version .page-link:hover,
    body.dark-version .pagination .page-link:hover,
    body.dark-version .dt-container .dt-paging .dt-paging-button:hover {
        background-color: rgba(94, 114, 228, 0.24) !important;
        border-color: rgba(94, 114, 228, 0.45) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .page-item.active .page-link,
    body.dark-version .pagination .active > .page-link,
    body.dark-version .dt-container .dt-paging .dt-paging-button.current {
        background: linear-gradient(135deg, #5e72e4, #11cdef) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: 0 0.5rem 1rem rgba(94, 114, 228, 0.24);
    }

    body.dark-version .page-item.disabled .page-link,
    body.dark-version .dt-container .dt-paging .dt-paging-button.disabled {
        background-color: rgba(148, 163, 184, 0.08) !important;
        border-color: rgba(148, 163, 184, 0.12) !important;
        color: var(--app-dark-soft) !important;
    }

    body.dark-version .form-control,
    body.dark-version .form-select,
    body.dark-version textarea.form-control,
    body.dark-version input.form-control,
    body.dark-version select.form-select,
    body.dark-version .input-group-text {
        background-color: var(--app-dark-surface-2) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .form-control:focus,
    body.dark-version .form-select:focus,
    body.dark-version textarea.form-control:focus,
    body.dark-version input.form-control:focus,
    body.dark-version select.form-select:focus {
        background-color: #0e1b42 !important;
        border-color: rgba(94, 114, 228, 0.65) !important;
        color: var(--app-dark-heading) !important;
        box-shadow: 0 0 0 0.16rem rgba(94, 114, 228, 0.2) !important;
    }

    body.dark-version .form-control::placeholder,
    body.dark-version textarea.form-control::placeholder {
        color: var(--app-dark-soft) !important;
        opacity: 1 !important;
    }

    body.dark-version .form-control:disabled,
    body.dark-version .form-control[readonly],
    body.dark-version .form-select:disabled {
        background-color: rgba(148, 163, 184, 0.1) !important;
        color: var(--app-dark-soft) !important;
    }

    body.dark-version input:-webkit-autofill,
    body.dark-version input:-webkit-autofill:hover,
    body.dark-version input:-webkit-autofill:focus {
        -webkit-text-fill-color: var(--app-dark-heading);
        box-shadow: 0 0 0 1000px var(--app-dark-surface-2) inset !important;
        transition: background-color 9999s ease-in-out 0s;
    }

    body.dark-version input[type="file"]::file-selector-button {
        background-color: var(--app-dark-surface-3);
        border: 0;
        color: var(--app-dark-heading);
    }

    body.dark-version .form-check-input {
        background-color: var(--app-dark-surface-2);
        border-color: var(--app-dark-border-strong);
    }

    body.dark-version .form-check-input:checked {
        background-color: #5e72e4;
        border-color: #5e72e4;
    }

    body.dark-version .invalid-feedback,
    body.dark-version .text-danger {
        color: #ff9b9b !important;
    }

    body.dark-version .valid-feedback,
    body.dark-version .text-success {
        color: #82e6b6 !important;
    }

    body.dark-version .select2-container--bootstrap-5 .select2-selection,
    body.dark-version .select2-container .select2-selection--single,
    body.dark-version .select2-container .select2-selection--multiple {
        min-height: calc(1.5em + 1rem + 2px);
        background-color: var(--app-dark-surface-2) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    body.dark-version .select2-container--bootstrap-5.select2-container--open .select2-selection {
        border-color: rgba(94, 114, 228, 0.65) !important;
        box-shadow: 0 0 0 0.16rem rgba(94, 114, 228, 0.2) !important;
    }

    body.dark-version .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
    body.dark-version .select2-container .select2-selection--single .select2-selection__rendered,
    body.dark-version .select2-container--bootstrap-5 .select2-selection__placeholder,
    body.dark-version .select2-container .select2-selection__placeholder {
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice,
    body.dark-version .select2-container .select2-selection--multiple .select2-selection__choice {
        background-color: rgba(94, 114, 228, 0.22) !important;
        border-color: rgba(94, 114, 228, 0.35) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .select2-dropdown {
        background-color: var(--app-dark-surface) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text) !important;
        box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.32);
    }

    body.dark-version .select2-search--dropdown .select2-search__field {
        background-color: var(--app-dark-surface-2) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .select2-results__option {
        color: var(--app-dark-text) !important;
    }

    body.dark-version .select2-results__option--selected {
        background-color: rgba(94, 114, 228, 0.28) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .select2-container--bootstrap-5 .select2-results__option--highlighted,
    body.dark-version .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #5e72e4 !important;
        color: #fff !important;
    }

    body.dark-version .select2-container--disabled .select2-selection {
        background-color: rgba(148, 163, 184, 0.1) !important;
        color: var(--app-dark-soft) !important;
    }

    body.dark-version .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow b,
    body.dark-version .select2-container .select2-selection--single .select2-selection__arrow b {
        border-color: var(--app-dark-muted) transparent transparent transparent !important;
    }

    body.dark-version .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.78;
    }

    body.dark-version .close,
    body.dark-version .modal-header .fa-xmark {
        color: var(--app-dark-heading) !important;
        text-shadow: none;
    }

    body.dark-version .btn-light,
    body.dark-version .btn.btn-light,
    body.dark-version .btn-outline-dark,
    body.dark-version .btn-outline-secondary {
        background-color: rgba(148, 163, 184, 0.12) !important;
        border-color: var(--app-dark-border-strong) !important;
        color: var(--app-dark-text) !important;
    }

    body.dark-version .btn-light:hover,
    body.dark-version .btn.btn-light:hover,
    body.dark-version .btn-outline-dark:hover,
    body.dark-version .btn-outline-secondary:hover,
    body.dark-version .btn-outline-dark.active,
    body.dark-version .btn-outline-secondary.active,
    body.dark-version .show > .btn-outline-dark.dropdown-toggle,
    body.dark-version .show > .btn-outline-secondary.dropdown-toggle {
        background-color: rgba(94, 114, 228, 0.24) !important;
        border-color: rgba(94, 114, 228, 0.55) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .dropdown-item:hover,
    body.dark-version .dropdown-item:focus,
    body.dark-version .dropdown-item.active,
    body.dark-version .dropdown-item:active {
        background-color: rgba(94, 114, 228, 0.18) !important;
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .alert-light,
    body.dark-version .alert-secondary {
        background-color: rgba(148, 163, 184, 0.14) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text) !important;
    }

    body.dark-version .year-access-banner,
    body.dark-version .year-feedback-banner {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.18) 0%, rgba(245, 158, 11, 0.14) 100%) !important;
        border-color: rgba(245, 158, 11, 0.32) !important;
        color: #fde68a !important;
        box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.2) !important;
    }

    body.dark-version .year-access-banner__desc,
    body.dark-version .year-feedback-banner__desc {
        color: #fcdca5 !important;
    }

    body.dark-version .year-access-banner__pill,
    body.dark-version .year-feedback-banner__pill {
        background: rgba(251, 191, 36, 0.14) !important;
        border-color: rgba(251, 191, 36, 0.28) !important;
        color: #fde68a !important;
    }

    body.dark-version .bank-table-loading,
    body.dark-version .loading-overlay,
    body.dark-version .users-table-loading,
    body.dark-version .budget-table-loading {
        background: rgba(5, 17, 57, 0.72) !important;
        backdrop-filter: blur(4px);
    }

    body.dark-version .bank-table-loading-card,
    body.dark-version .loading-card,
    body.dark-version .users-loading-card,
    body.dark-version .budget-loading-card {
        background-color: var(--app-dark-surface) !important;
        border: 1px solid var(--app-dark-border) !important;
        color: var(--app-dark-text) !important;
        box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, 0.3) !important;
    }

    body.dark-version .is-loading::after,
    body.dark-version .loading-line {
        background: linear-gradient(90deg, rgba(148, 163, 184, 0.12), rgba(148, 163, 184, 0.28), rgba(148, 163, 184, 0.12)) !important;
    }

    body.dark-version .switch-hero {
        background: linear-gradient(135deg, rgba(17, 205, 239, 0.18), rgba(45, 206, 137, 0.16)) !important;
        border: 1px solid var(--app-dark-border);
    }

    body.dark-version .position-card {
        background-color: var(--app-dark-surface) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text);
    }

    body.dark-version .position-card:hover {
        border-color: rgba(94, 114, 228, 0.42) !important;
        box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.24) !important;
    }

    body.dark-version .position-card.is-active {
        border-color: rgba(45, 206, 137, 0.5) !important;
        box-shadow: 0 1rem 2rem rgba(45, 206, 137, 0.12) !important;
    }

    body.dark-version .position-meta-row span,
    body.dark-version .super-context-eyebrow,
    body.dark-version .super-context-item span,
    body.dark-version .super-context-special span,
    body.dark-version .super-context-item small,
    body.dark-version .super-context-special small {
        color: var(--app-dark-muted) !important;
    }

    body.dark-version .position-meta-row strong,
    body.dark-version .super-context-title,
    body.dark-version .super-context-item strong,
    body.dark-version .super-context-special strong {
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .position-chip,
    body.dark-version .super-context-special-meta span {
        background: rgba(148, 163, 184, 0.12) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text) !important;
    }

    body.dark-version .super-context-card,
    body.dark-version .super-context-item,
    body.dark-version .super-context-special,
    body.dark-version .empty-state {
        background-color: var(--app-dark-surface-2) !important;
        border-color: var(--app-dark-border) !important;
        color: var(--app-dark-text);
    }

    body.dark-version .super-context-desc {
        color: var(--app-dark-muted) !important;
    }

    body.dark-version .super-context-icon {
        background: rgba(94, 114, 228, 0.2) !important;
        color: #aebcff !important;
    }

    body.dark-version #pdf-status-text {
        color: #06111f !important;
    }

    body.dark-version #pdfViewer embed {
        background-color: var(--app-dark-surface-2);
        border: 1px solid var(--app-dark-border);
        border-radius: 0.75rem;
    }

    body.dark-version .swal2-popup {
        background: var(--app-dark-surface) !important;
        color: var(--app-dark-text) !important;
    }

    body.dark-version .swal2-title {
        color: var(--app-dark-heading) !important;
    }

    body.dark-version .swal2-html-container,
    body.dark-version .swal2-content {
        color: var(--app-dark-muted) !important;
    }

    @media (max-width: 767.98px) {
        body.dark-version :where(.modal-content, .card:not([class*="bg-gradient"])) {
            box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.22) !important;
        }
    }
</style>
