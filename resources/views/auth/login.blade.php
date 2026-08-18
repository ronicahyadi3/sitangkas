<!doctype html>
<html lang="id">

@php
    $recaptchaFacade = class_exists(\Lunaweb\RecaptchaV3\Facades\RecaptchaV3::class)
        ? \Lunaweb\RecaptchaV3\Facades\RecaptchaV3::class
        : null;
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SITANGKAS</title>
    <meta name="description" content="Login SITANGKAS - Aplikasi Tandatangan Keuangan dan Aset">
    <link rel="icon" type="image/png" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}">

    @if ($recaptchaFacade)
        {!! $recaptchaFacade::initJs() !!}
    @endif

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />

    <!-- Icon Libraries -->
    <link href="/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="/assets/css/nucleo-svg.css" rel="stylesheet" />

    <!-- Main Theme CSS -->
    <link id="pagestyle" href="/assets/css/argon-dashboard.css?v=2.0.7" rel="stylesheet" />

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/2a3e35f292.js" crossorigin="anonymous"></script>

    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        :root {
            --sitangkas-primary: #1b4f8c;
            --sitangkas-secondary: #2f8df3;
            --sitangkas-accent: #22b573;
            --sitangkas-dark: #102c4e;
            --sitangkas-soft: #f4f8ff;
        }

        html,
        body {
            min-height: 100%;
            font-family: 'Open Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47, 141, 243, 0.16), transparent 22%),
                radial-gradient(circle at bottom right, rgba(34, 181, 115, 0.10), transparent 18%),
                linear-gradient(135deg, #f8fbff 0%, #eef5ff 55%, #f9fcff 100%);
        }

        body {
            color: #344767;
        }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 1.5rem 0;
        }

        .auth-shell {
            width: 100%;
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(10px);
            box-shadow: 0 18px 50px rgba(15, 47, 87, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.75);
        }

        .auth-left {
            position: relative;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, .18), transparent 20%),
                linear-gradient(145deg, #0f2f57 0%, #1b4f8c 45%, #2f8df3 100%);
            color: #fff;
            min-height: 100%;
            padding: 2.25rem;
        }

        .auth-left::before {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
            top: -140px;
            right: -100px;
        }

        .auth-left::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
            bottom: -100px;
            left: -90px;
        }

        .auth-left>* {
            position: relative;
            z-index: 2;
        }

        .brand-badge {
            width: 62px;
            height: 62px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .18);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .08);
            margin-bottom: 1rem;
        }

        .brand-badge i {
            font-size: 1.5rem;
            color: #fff;
        }

        .auth-title {
            font-weight: 700;
            line-height: 1.15;
            color: #fff;
            margin-bottom: 1rem;
        }

        .auth-desc {
            color: rgba(255, 255, 255, .84);
            line-height: 1.8;
            margin-bottom: 1.5rem;
            max-width: 560px;
        }

        .auth-feature {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            padding: .9rem 1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, .10);
            border: 1px solid rgba(255, 255, 255, .12);
            backdrop-filter: blur(6px);
            margin-bottom: .9rem;
        }

        .auth-feature-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .14);
            color: #fff;
            flex-shrink: 0;
        }

        .auth-feature h6 {
            color: #fff;
            font-weight: 700;
            margin-bottom: .25rem;
        }

        .auth-feature p {
            color: rgba(255, 255, 255, .78);
            margin-bottom: 0;
            font-size: .9rem;
            line-height: 1.6;
        }

        .auth-footer-note {
            margin-top: 1.25rem;
            color: rgba(255, 255, 255, .68);
            font-size: .82rem;
        }

        .auth-right {
            padding: 2rem;
            background: transparent;
        }

        .login-card {
            max-width: 520px;
            margin: 0 auto;
        }

        .login-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .85rem;
            border-radius: 999px;
            background: rgba(47, 141, 243, 0.10);
            color: var(--sitangkas-primary);
            font-size: .8rem;
            font-weight: 700;
            margin-bottom: .9rem;
        }

        .login-heading {
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: .35rem;
        }

        .login-subheading {
            color: #67748e;
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }

        .soft-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 14px 32px rgba(15, 47, 87, 0.08);
            border: 1px solid rgba(227, 232, 239, .8);
        }

        .soft-card .card-body {
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #344767;
            margin-bottom: .45rem;
        }

        .input-group-text {
            border-right: 0;
            background: #fff;
            color: #8392ab;
        }

        .form-control,
        .form-select,
        .select2-container--bootstrap-5 .select2-selection {
            border-color: #d2d6da;
            min-height: 48px;
        }

        .input-group .form-control {
            border-left: 0;
        }

        .form-control:focus,
        .form-select:focus,
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--sitangkas-secondary);
            box-shadow: 0 0 0 0.2rem rgba(47, 141, 243, 0.15);
        }

        .toggle-password {
            cursor: pointer;
            background: #fff;
            border-left: 0;
            color: #8392ab;
        }

        .btn-sitangkas {
            background: linear-gradient(135deg, var(--sitangkas-primary), var(--sitangkas-secondary));
            border: 0;
            color: #fff;
            box-shadow: 0 10px 22px rgba(27, 79, 140, 0.18);
        }

        .btn-sitangkas:hover {
            color: #fff;
            transform: translateY(-1px);
        }

        .helper-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .text-soft {
            color: #8392ab;
        }

        .login-footer {
            margin-top: 1rem;
            text-align: center;
            color: #8392ab;
            font-size: .9rem;
        }

        .alert {
            border: 0;
            border-radius: 16px;
        }

        .app-mini-info {
            display: flex;
            gap: .75rem;
            align-items: center;
            padding: .9rem 1rem;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px solid #e9f2ff;
        }

        .app-mini-info i {
            color: var(--sitangkas-primary);
        }

        @media (max-width: 991.98px) {
            .auth-left {
                padding: 1.5rem;
            }

            .auth-right {
                padding: 1.25rem;
            }

            .soft-card .card-body {
                padding: 1.25rem;
            }
        }
    </style>
    @include('inc.standalone-dark-mode')
</head>

<body>
    @php
        $currentYear = (int) date('Y');
        $selectedYear = old('tahun', $currentYear);
    @endphp

    <div class="container auth-wrapper">
        <div class="auth-shell">
            <div class="row g-0">
                <div class="col-lg-6">
                    <div class="auth-left h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="">
                                <img src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}" alt="Logo SITANGKAS"
                                    style="width: 4rem;padding-bottom: 1rem;">
                                <img src="{{ asset('assets/img/sitangkas_logo_only_white.png') }}" alt="Logo SITANGKAS"
                                    style="width: 6rem;">
                            </div>

                            <div class="mb-4">
                                <h2 class="auth-title">
                                    SITANGKAS
                                </h2>
                                <p class="mb-1 fw-bold text-white">Aplikasi Tandatangan Keuangan dan Aset</p>
                                <p class="auth-desc mb-0">
                                    Mendukung proses pengelolaan dokumen keuangan dan aset yang lebih efisien,
                                    terstruktur, aman, dan akuntabel dalam satu sistem terintegrasi.
                                </p>
                            </div>

                            <div class="auth-feature">
                                <div class="auth-feature-icon">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div>
                                    <h6>Keamanan & Validasi</h6>
                                    <p>Setiap proses dokumen dikelola dengan alur yang jelas dan kontrol akses yang
                                        terjaga.</p>
                                </div>
                            </div>

                            <div class="auth-feature">
                                <div class="auth-feature-icon">
                                    <i class="fa-solid fa-arrow-trend-up"></i>
                                </div>
                                <div>
                                    <h6>Alur Persetujuan Terstruktur</h6>
                                    <p>Dokumen dipantau dari tahap validasi, persetujuan, hingga arsip digital secara
                                        rapi.</p>
                                </div>
                            </div>

                            <div class="auth-feature mb-0">
                                <div class="auth-feature-icon">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <div>
                                    <h6>Administrasi Lebih Efisien</h6>
                                    <p>Membantu percepatan proses kerja agar lebih mudah ditelusuri, terdokumentasi, dan
                                        profesional.</p>
                                </div>
                            </div>
                        </div>

                        <div class="auth-footer-note">
                            &copy; {{ date('Y') }} •
                            <span dir="rtl">بِإِذْنِ اللّٰهِ</span> •
                            Aplikasi ini terwujud atas izin Allah.
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="auth-right h-100 d-flex align-items-center">
                        <div class="login-card w-100">
                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <span class="login-chip mb-0">
                                    <i class="fa-solid fa-lock"></i>
                                    Akses Sistem
                                </span>

                                <span class="public-theme-toggle-slot" data-public-theme-toggle-mount
                                    data-public-theme-toggle-variant="icon"></span>
                            </div>

                            <h3 class="login-heading">Masuk ke akun Anda</h3>
                            <p class="login-subheading">
                                Gunakan NIK atau email yang terdaftar untuk melanjutkan ke dashboard SITANGKAS.
                            </p>

                            @if (session('status'))
                                <div class="alert alert-info text-white bg-gradient-info" role="alert">
                                    <i class="fa-solid fa-circle-info me-2"></i>{{ session('status') }}
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger text-white bg-gradient-danger" role="alert">
                                    <div class="d-flex">
                                        <div class="me-2">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block mb-1">Terjadi kesalahan pada form login</strong>
                                            <ul class="mb-0 ps-3">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="card soft-card border-0">
                                <div class="card-body">
                                    <form method="POST" action="{{ route('login.store') }}">
                                        @csrf
                                        @if ($recaptchaFacade)
                                            {!! $recaptchaFacade::field('login') !!}
                                        @endif
                                        <input type="hidden" id="client_timezone" name="client_timezone"
                                            value="{{ old('client_timezone') }}">

                                        <div class="mb-3">
                                            <label for="nik" class="form-label">NIK atau Email</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fa-solid fa-user"></i>
                                                </span>
                                                <input type="text" id="nik" name="nik"
                                                    class="form-control @error('nik') is-invalid @enderror"
                                                    placeholder="Masukkan NIK atau email" value="{{ old('nik') }}"
                                                    autofocus required>
                                            </div>
                                            @error('nik')
                                                <small class="text-danger d-block mt-1">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fa-solid fa-lock"></i>
                                                </span>
                                                <input type="password" id="password" name="password"
                                                    class="form-control @error('password') is-invalid @enderror"
                                                    placeholder="Masukkan password" required>
                                                <span class="input-group-text toggle-password" id="togglePassword">
                                                    <i class="fa-regular fa-eye"></i>
                                                </span>
                                            </div>
                                            @error('password')
                                                <small class="text-danger d-block mt-1">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="tahun" class="form-label">Tahun Anggaran</label>
                                            <select id="tahun" name="tahun"
                                                class="form-select @error('tahun') is-invalid @enderror" required>
                                                @for ($y = $currentYear; $y >= $currentYear - 5; $y--)
                                                    <option value="{{ $y }}"
                                                        {{ (string) $selectedYear === (string) $y ? 'selected' : '' }}>
                                                        {{ $y }}
                                                    </option>
                                                @endfor
                                            </select>
                                            @error('tahun')
                                                <small class="text-danger d-block mt-1">{{ $message }}</small>
                                            @enderror
                                        </div>

                                        <div class="helper-row mb-4">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" value="1"
                                                    id="remember" name="remember"
                                                    {{ old('remember') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="remember">
                                                    Ingat saya
                                                </label>
                                            </div>

                                            @if ($recaptchaFacade)
                                                <span class="text-soft small">
                                                    <i class="fa-solid fa-shield-heart me-1"></i>
                                                    Login dilindungi reCAPTCHA
                                                </span>
                                            @endif
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-sitangkas btn-lg mb-0">
                                                <i class="fa-solid fa-right-to-bracket me-2"></i>
                                                Masuk ke Sistem
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="app-mini-info mt-3">
                                <i class="fa-solid fa-circle-check fa-lg"></i>
                                <div>
                                    <div class="fw-bold text-dark">Akses cepat dan terverifikasi</div>
                                    <small class="text-soft">
                                        Pastikan akun dan tahun anggaran dipilih dengan benar sebelum masuk ke sistem.
                                    </small>
                                </div>
                            </div>

                            <div class="login-footer">
                                SITANGKAS membantu pengelolaan dokumen keuangan dan aset menjadi lebih tertib dan
                                efisien.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
        crossorigin="anonymous"></script>

    <!-- Bootstrap Core -->
    <script src="/assets/js/core/popper.min.js"></script>
    <script src="/assets/js/core/bootstrap.min.js"></script>

    <!-- Dashboard Argon -->
    <script src="/assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="/assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="/assets/js/argon-dashboard.min.js?v=2.0.5" type="text/javascript"></script>

    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            const timezoneInput = document.getElementById('client_timezone');

            if (
                timezoneInput &&
                window.Intl &&
                typeof window.Intl.DateTimeFormat === 'function'
            ) {
                const timezone = window.Intl.DateTimeFormat().resolvedOptions().timeZone;

                if (typeof timezone === 'string' && timezone.length <= 100) {
                    timezoneInput.value = timezone;
                }
            }

            $('#tahun').select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumResultsForSearch: Infinity,
                dropdownParent: $('.soft-card')
            });

            $('#togglePassword').on('click', function() {
                const input = $('#password');
                const icon = $(this).find('i');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });
    </script>
</body>

</html>
