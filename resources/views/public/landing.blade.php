<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SITANGKAS - Aplikasi Tandatangan Keuangan dan Aset</title>
    <meta name="author" content="Achmad Roni Cahyadi & Darfian Ardiansyah">
    <meta name="description"
        content="SITANGKAS adalah aplikasi tandatangan keuangan dan aset untuk mendukung tata kelola dokumen yang lebih efisien, aman, dan terstruktur.">
    <meta name="theme-color" content="#1b4f8c">

    <link rel="icon" type="image/png" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}">

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <link href="/assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="/assets/css/nucleo-svg.css" rel="stylesheet">
    <link id="pagestyle" href="/assets/css/argon-dashboard.css?v=2.0.7" rel="stylesheet">

        <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <style>
        :root {
            --sitangkas-primary: #1b4f8c;
            --sitangkas-secondary: #2f8df3;
            --sitangkas-accent: #22b573;
            --sitangkas-dark: #0f2f57;
            --sitangkas-soft: #f5f9ff;
            --sitangkas-text: #344767;
            --sitangkas-border: rgba(15, 47, 87, 0.08);
        }

        html,
        body {
            height: 100%;
            font-family: 'Open Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47, 141, 243, 0.18), transparent 25%),
                radial-gradient(circle at bottom right, rgba(34, 181, 115, 0.12), transparent 22%),
                linear-gradient(135deg, #f8fbff 0%, #eef5ff 55%, #f7fbff 100%);
            color: var(--sitangkas-text);
        }

        body {
            overflow: hidden;
        }

        .page-shell {
            min-height: 100vh;
            padding: 1rem;
        }

        .landing-wrapper {
            height: calc(100vh - 2rem);
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(10px);
            box-shadow: 0 18px 50px rgba(15, 47, 87, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.65);
            position: relative;
        }

        .landing-wrapper::before {
            content: "";
            position: absolute;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(47, 141, 243, .15), transparent 70%);
            top: -110px;
            right: -100px;
            z-index: 0;
        }

        .landing-wrapper::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(34, 181, 115, .12), transparent 70%);
            bottom: -120px;
            left: -90px;
            z-index: 0;
        }

        .content-layer {
            position: relative;
            z-index: 2;
            height: 100%;
        }

        .topbar-main {
            min-height: 72px;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: .85rem;
            min-width: 0;
        }

        .brand-logos {
            display: flex;
            align-items: flex-end;
            gap: .55rem;
            flex-shrink: 0;
        }

        .logo-pemkot {
            width: 3.2rem;
            height: auto;
            padding-bottom: .4rem;
        }

        .logo-sitangkas {
            width: 5.5rem;
            height: auto;
        }

        .brand-copy {
            min-width: 0;
        }

        .brand-title {
            margin-bottom: 0;
            font-weight: 700;
            letter-spacing: .6px;
            color: var(--sitangkas-primary);
        }

        .brand-subtitle {
            margin-bottom: 0;
            color: #8392ab;
            font-size: .85rem;
        }

        .chip,
        .mobile-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .5rem .95rem;
            border-radius: 999px;
            background: rgba(47, 141, 243, 0.1);
            color: var(--sitangkas-primary);
            font-size: .8rem;
            font-weight: 700;
        }

        .landing-nav {
            row-gap: .5rem;
        }

        .nav-mini-link {
            position: relative;
            color: #5e72a4;
            text-decoration: none;
            font-size: .92rem;
            font-weight: 600;
            transition: all .2s ease;
            padding: .2rem 0;
        }

        .nav-mini-link:hover,
        .nav-mini-link.active {
            color: var(--sitangkas-primary);
        }

        .nav-mini-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -3px;
            width: 0;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--sitangkas-primary), var(--sitangkas-secondary));
            transition: width .25s ease;
        }

        .nav-mini-link:hover::after,
        .nav-mini-link.active::after {
            width: 100%;
        }

        .nav-dropdown .dropdown-toggle::after {
            display: none !important;
        }

        .nav-dropdown-toggle {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
        }

        .dropdown-arrow {
            font-size: .72rem;
            transition: transform .25s ease, opacity .25s ease;
            opacity: .75;
        }

        .nav-dropdown.show .dropdown-arrow {
            transform: rotate(180deg);
            opacity: 1;
        }

        .dropdown-menu {
            min-width: 220px;
        }

        .dropdown-item {
            font-size: .92rem;
            font-weight: 600;
            color: #344767;
        }

        .dropdown-item:hover {
            background: #f8fbff;
            color: var(--sitangkas-primary);
        }

        .login-btn-responsive {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 40px;
        }

        .mobile-login-btn,
        .mobile-menu-btn {
            width: 42px;
            height: 42px;
            padding: 0 !important;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(15, 47, 87, 0.08);
        }

        .mobile-nav-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--sitangkas-border);
            border-radius: 20px;
            padding: 1rem;
            box-shadow: 0 12px 28px rgba(15, 47, 87, .08);
            backdrop-filter: blur(10px);
        }

        .mobile-nav-list {
            display: flex;
            flex-direction: column;
            gap: .45rem;
        }

        .mobile-nav-link,
        .mobile-nav-action {
            display: flex;
            align-items: center;
            width: 100%;
            padding: .9rem 1rem;
            border-radius: 16px;
            text-decoration: none;
            color: #344767;
            font-weight: 600;
            transition: all .2s ease;
            background: #fff;
            border: 1px solid #edf2f7;
            text-align: left;
        }

        .mobile-nav-link:hover,
        .mobile-nav-action:hover {
            color: var(--sitangkas-primary);
            background: #f8fbff;
            border-color: #dbeafe;
        }

        .hero-title {
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.08;
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: 1rem;
        }

        .hero-title .highlight {
            background: linear-gradient(135deg, var(--sitangkas-primary), var(--sitangkas-secondary), var(--sitangkas-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 1rem;
            line-height: 1.75;
            color: #5e72a4;
            max-width: 850px;
            margin-bottom: 1.25rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.25rem;
        }

        .btn-sitangkas {
            background: linear-gradient(135deg, var(--sitangkas-primary), var(--sitangkas-secondary));
            border: none;
            color: #fff;
            box-shadow: 0 12px 24px rgba(27, 79, 140, .18);
        }

        .btn-sitangkas:hover {
            color: #fff;
            transform: translateY(-1px);
        }

        .mini-point {
            display: flex;
            align-items: center;
            gap: .65rem;
            font-size: .9rem;
            color: #67748e;
            margin-bottom: .75rem;
        }

        .mini-point i {
            color: var(--sitangkas-accent);
            font-size: 1rem;
        }

        .divider-soft {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(131, 146, 171, .35), transparent);
            margin: .8rem 0 1rem;
        }

        .feature-card {
            border: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 10px 24px rgba(15, 47, 87, 0.08);
            height: 100%;
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(47, 141, 243, .12), rgba(34, 181, 115, .10));
            color: var(--sitangkas-primary);
            font-size: 1.1rem;
            margin-bottom: .9rem;
        }

        .feature-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: .35rem;
        }

        .feature-text {
            font-size: .88rem;
            line-height: 1.6;
            color: #67748e;
            margin-bottom: 0;
        }

        .showcase-card {
            border: 0;
            border-radius: 24px;
            overflow: hidden;
            background: linear-gradient(145deg, #0f2f57 0%, #1b4f8c 45%, #2f8df3 100%);
            color: #fff;
            box-shadow: 0 18px 38px rgba(15, 47, 87, 0.18);
            min-height: 100%;
        }

        .showcase-top {
            position: relative;
            padding: 1.25rem 1.25rem .5rem 1.25rem;
        }

        .showcase-top::after {
            content: "";
            position: absolute;
            inset: auto -40px -60px auto;
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
        }

        .app-window {
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 18px;
            padding: 1rem;
            backdrop-filter: blur(6px);
        }

        .window-dots {
            display: flex;
            gap: .4rem;
            margin-bottom: .8rem;
        }

        .window-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background: rgba(255, 255, 255, .65);
        }

        .metric-box {
            border-radius: 18px;
            background: rgba(255, 255, 255, .13);
            padding: .9rem 1rem;
            border: 1px solid rgba(255, 255, 255, .10);
        }

        .metric-label {
            font-size: .8rem;
            color: rgba(255, 255, 255, .75);
            margin-bottom: .2rem;
        }

        .metric-value {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .info-glass-card {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 18px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .info-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            flex-shrink: 0;
        }

        .info-icon-cyan {
            background: rgba(82, 196, 255, 0.18);
            color: #7dd3fc;
        }

        .info-icon-green {
            background: rgba(34, 197, 94, 0.18);
            color: #86efac;
        }

        .info-title {
            color: #ffffff;
            font-size: .98rem;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .info-text {
            color: rgba(255, 255, 255, 0.88);
            font-size: .88rem;
            line-height: 1.6;
        }

        .footer-mini {
            font-size: .82rem;
            color: #8392ab;
        }

        .modal-content {
            border: 0;
            border-radius: 20px;
        }

        .modal-header {
            border-bottom: 1px solid #edf2f7;
        }

        .modal-footer {
            border-top: 1px solid #edf2f7;
        }

        @media (max-width: 1399.98px) {
            .hero-title {
                font-size: clamp(1.9rem, 3.3vw, 3rem);
            }

            .landing-nav {
                gap: 1rem !important;
            }
        }

        @media (max-width: 1199.98px) {
            .page-shell {
                padding: .75rem;
            }

            .landing-wrapper {
                border-radius: 24px;
            }

            .brand-title {
                font-size: 1.15rem;
            }

            .brand-subtitle {
                font-size: .78rem;
            }

            .hero-desc {
                max-width: 100%;
            }
        }

        @media (max-width: 991.98px) {

            html,
            body {
                height: auto;
                overflow-x: hidden;
                overflow-y: auto;
            }

            body {
                overflow: auto;
            }

            .page-shell {
                padding: .5rem;
            }

            .landing-wrapper {
                height: auto;
                min-height: calc(100vh - 1rem);
                border-radius: 20px;
            }

            .content-layer {
                height: auto;
            }

            .topbar-brand {
                gap: .75rem;
                flex: 1 1 auto;
            }

            .logo-pemkot {
                width: 2.5rem;
            }

            .logo-sitangkas {
                width: 4.5rem;
            }

            .brand-title {
                font-size: 1.05rem;
                line-height: 1.2;
            }

            .brand-subtitle {
                font-size: .72rem;
                line-height: 1.35;
            }

            .container-fluid.px-4,
            .container-fluid.px-lg-5 {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            .hero-title {
                font-size: 1.9rem;
                line-height: 1.18;
                margin-bottom: .85rem;
            }

            .hero-desc {
                font-size: .95rem;
                line-height: 1.7;
                margin-bottom: 1rem;
            }

            .hero-actions {
                flex-direction: column;
                gap: .75rem;
            }

            .hero-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .mini-point {
                font-size: .88rem;
                margin-bottom: .5rem;
            }

            .divider-soft {
                margin: 1rem 0;
            }

            .feature-card .card-body {
                padding: 1rem !important;
            }

            .showcase-card {
                margin-top: .25rem;
                border-radius: 20px;
            }

            .showcase-top {
                padding: 1rem 1rem .35rem 1rem;
            }

            .app-window {
                padding: .85rem;
            }

            .metric-box {
                padding: .8rem .85rem;
                border-radius: 16px;
            }

            .metric-label {
                font-size: .75rem;
            }

            .metric-value {
                font-size: 1rem;
            }

            .info-glass-card {
                padding: 1rem !important;
            }

            .footer-mini {
                flex-direction: column;
                text-align: center;
                gap: .35rem !important;
                padding-top: .5rem;
            }
        }

        @media (max-width: 575.98px) {
            .topbar-main {
                gap: .75rem;
            }

            .brand-logos {
                gap: .4rem;
            }

            .logo-pemkot {
                width: 2.1rem;
            }

            .logo-sitangkas {
                width: 3.9rem;
            }

            .brand-title {
                font-size: .98rem;
                margin-bottom: .1rem;
            }

            .brand-subtitle {
                font-size: .68rem;
            }

            .chip,
            .mobile-chip {
                font-size: .72rem;
                padding: .45rem .75rem;
            }

            .hero-title {
                font-size: 1.65rem;
            }

            .hero-desc {
                font-size: .9rem;
            }

            .showcase-top .badge {
                font-size: .72rem;
                padding: .5rem .7rem !important;
            }

            .window-dots span {
                width: 8px;
                height: 8px;
            }

            .metric-value {
                font-size: .95rem;
            }

            .feature-title,
            .info-title {
                font-size: .92rem;
            }

            .feature-text,
            .info-text {
                font-size: .83rem;
            }
        }
    </style>
    @include('inc.standalone-dark-mode')
</head>

<body>
    <div class="page-shell">
        <div class="landing-wrapper" id="beranda">
            <div class="content-layer d-flex flex-column">

                <!-- Topbar -->
                <div class="container-fluid px-4 px-lg-5 pt-4 pb-2">
                    <div class="topbar-main d-flex justify-content-between align-items-center gap-3">
                        <div class="topbar-brand">
                            <div class="brand-logos">
                                <img src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}"
                                    alt="Logo Pemerintah Kota Malang" class="logo-pemkot" loading="lazy">
                                <img src="{{ asset('assets/img/sitangkas_logo_only.png') }}" alt="Logo SITANGKAS"
                                    class="logo-sitangkas" loading="lazy">
                            </div>
                            <div class="brand-copy">
                                <h4 class="brand-title">SITANGKAS</h4>
                                <p class="brand-subtitle">Aplikasi Tandatangan Keuangan dan Aset</p>
                            </div>
                        </div>

                        <!-- Desktop -->
                        <div class="d-none d-lg-flex align-items-center gap-3 flex-wrap justify-content-end">
                            <span class="chip">
                                <i class="fa-solid fa-shield-halved"></i>
                                Aman & Terverifikasi
                            </span>

                            <nav class="landing-nav d-flex align-items-center gap-3 flex-wrap">
                                <a href="{{ url('/') }}" class="nav-mini-link active">Beranda</a>
                                <a href="{{ url('/about') }}#menu" class="nav-mini-link">Tentang</a>
                                <a href="{{ url('/about') }}#fitur" class="nav-mini-link">Fitur</a>

                                <div class="dropdown nav-dropdown">
                                    <a class="nav-mini-link nav-dropdown-toggle" href="#" role="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <span>Lainnya</span>
                                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                                    </a>

                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2">
                                        <li>
                                            <a class="dropdown-item rounded-3 py-2" href="{{ url('/about') }}#pengguna">
                                                <i class="fa-solid fa-users me-2 text-primary"></i>
                                                Pengguna
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item rounded-3 py-2"
                                                href="{{ url('/about') }}#dasar-hukum">
                                                <i class="fa-solid fa-scale-balanced me-2 text-primary"></i>
                                                Dasar Hukum
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item rounded-3 py-2" href="{{ url('/about') }}#kontak">
                                                <i class="fa-solid fa-phone me-2 text-primary"></i>
                                                Kontak
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </nav>

                            <a href="{{ url('/login') }}"
                                class="btn btn-outline-primary btn-sm mb-0 px-3 login-btn-responsive"
                                aria-label="Login">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span class="d-none d-lg-inline ms-1">Login</span>
                            </a>

                            <span class="public-theme-toggle-slot" data-public-theme-toggle-mount></span>
                        </div>

                        <!-- Mobile -->
                        <div class="d-flex d-lg-none align-items-center gap-2">
                            <a href="{{ url('/login') }}" class="btn btn-primary btn-sm mb-0 mobile-login-btn"
                                aria-label="Login">
                                <i class="fa-solid fa-right-to-bracket"></i>
                            </a>

                            <span class="public-theme-toggle-slot public-theme-toggle-slot--mobile"
                                data-public-theme-toggle-mount data-public-theme-toggle-variant="icon"></span>

                            <button class="btn btn-white btn-sm mb-0 mobile-menu-btn" type="button"
                                data-bs-toggle="collapse" data-bs-target="#mobileMenuCollapse" aria-expanded="false"
                                aria-controls="mobileMenuCollapse" aria-label="Buka menu navigasi">
                                <i class="fa-solid fa-bars"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile Collapse -->
                    <div class="collapse d-lg-none mt-3" id="mobileMenuCollapse">
                        <div class="mobile-nav-card">
                            <div class="mobile-chip mb-3">
                                <i class="fa-solid fa-shield-halved"></i>
                                Aman & Terverifikasi
                            </div>

                            <div class="mobile-nav-list">
                                <a href="{{ url('/') }}" class="mobile-nav-link">Beranda</a>
                                <a href="{{ url('/about') }}#menu" class="mobile-nav-link">Tentang</a>
                                <a href="{{ url('/about') }}#fitur" class="mobile-nav-link">Fitur</a>
                                <a href="{{ url('/about') }}#pengguna" class="mobile-nav-link">Pengguna</a>
                                <a href="{{ url('/about') }}#dasar-hukum" class="mobile-nav-link">Dasar Hukum</a>
                                <a href="{{ url('/about') }}#kontak" class="mobile-nav-link">Kontak</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main -->
                <main class="container-fluid px-4 px-lg-5 pb-4 flex-grow-1 d-flex align-items-center">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7" id="tentang">
                            <span class="chip mb-3">
                                <i class="fa-solid fa-building-columns"></i>
                                Aplikasi Tandatangan Keuangan dan Aset
                            </span>

                            <h1 class="hero-title">
                                Kelola proses <span class="highlight">pengajuan pencairan APBD</span>
                                lebih cepat, aman, tertib dan transparan.
                            </h1>

                            <p class="hero-desc">
                                SITANGKAS hadir untuk mendukung tata kelola dokumen keuangan dan aset secara efisien,
                                aman, terstruktur dan transparan. Mulai dari validasi, proses persetujuan, pemantauan
                                status,
                                hingga arsip digital dalam satu sistem terpadu.
                            </p>

                            <div class="hero-actions">
                                <a href="{{ url('/login') }}" class="btn btn-sitangkas btn-lg mb-0">
                                    <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>
                                    Masuk ke Aplikasi
                                </a>

                                <a href="/about#fitur" class="btn btn-white btn-lg mb-0 shadow-sm">
                                    <i class="fa-solid fa-list-check me-2 text-primary"></i>
                                    Pelajari Fitur
                                </a>
                            </div>

                            <div class="row g-2 g-lg-3 mt-1">
                                <div class="col-md-6">
                                    <div class="mini-point">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Proses tandatangan lebih efisien dan terukur
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mini-point">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Monitoring status dokumen secara real-time
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mini-point">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Arsip digital rapi dan mudah ditelusuri
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mini-point">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Mendukung tata kelola yang akuntabel
                                    </div>
                                </div>
                            </div>

                            <div class="divider-soft"></div>

                            <div class="row g-3" id="fitur">
                                <div class="col-md-4">
                                    <div class="card feature-card">
                                        <div class="card-body py-3 px-3">
                                            <div class="feature-icon">
                                                <i class="fa-solid fa-file-signature"></i>
                                            </div>
                                            <h6 class="feature-title">Tandatangan Terintegrasi</h6>
                                            <p class="feature-text">
                                                Alur penandatanganan dokumen lebih ringkas, jelas, dan terdokumentasi.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card feature-card">
                                        <div class="card-body py-3 px-3">
                                            <div class="feature-icon">
                                                <i class="fa-solid fa-coins"></i>
                                            </div>
                                            <h6 class="feature-title">Fokus Keuangan & Aset</h6>
                                            <p class="feature-text">
                                                Dirancang untuk kebutuhan administrasi keuangan dan pengelolaan aset.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="card feature-card">
                                        <div class="card-body py-3 px-3">
                                            <div class="feature-icon">
                                                <i class="fa-solid fa-chart-line"></i>
                                            </div>
                                            <h6 class="feature-title">Cepat & Informatif</h6>
                                            <p class="feature-text">
                                                Menyajikan status, progres, dan kontrol dokumen secara modern.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="card showcase-card mb-0">
                                <div class="showcase-top">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="text-white mb-1">SITANGKAS Workspace</h5>
                                            <p class="text-white-50 mb-0 small">Digital, aman, dan profesional</p>
                                        </div>
                                        <span class="badge bg-white text-primary border-0 px-3 py-2">
                                            <i class="fa-solid fa-lock me-1"></i> Secure
                                        </span>
                                    </div>

                                    <div class="app-window">
                                        <div class="window-dots">
                                            <span></span><span></span><span></span>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <div class="metric-box">
                                                    <div class="metric-label">Dokumen Aktif</div>
                                                    <p class="metric-value">128</p>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="metric-box">
                                                    <div class="metric-label">Menunggu Validasi</div>
                                                    <p class="metric-value">17</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-12">
                                                <div class="metric-box">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center mb-2">
                                                        <div>
                                                            <div class="metric-label">Proses Dokumen</div>
                                                            <p class="metric-value mb-0">Persetujuan Berjalan</p>
                                                        </div>
                                                        <i class="fa-solid fa-file-circle-check fa-xl text-white"></i>
                                                    </div>

                                                    <div class="progress bg-white bg-opacity-25" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: 82%;">
                                                        </div>
                                                    </div>

                                                    <small class="d-block mt-2 text-white-50">
                                                        82% dokumen telah diproses dengan baik
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-6">
                                                <div class="metric-box">
                                                    <div class="metric-label">Keamanan</div>
                                                    <p class="metric-value">Verified</p>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="metric-box">
                                                    <div class="metric-label">Arsip</div>
                                                    <p class="metric-value">Digital Ready</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mt-2">
                                        <div class="col-md-6">
                                            <div class="info-glass-card p-3 h-100">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="info-icon info-icon-cyan me-2">
                                                        <i class="fa-solid fa-folder-tree"></i>
                                                    </div>
                                                    <h6 class="info-title mb-0">Alur Terstruktur</h6>
                                                </div>
                                                <p class="info-text mb-0">
                                                    Dokumen masuk, diverifikasi, diproses, dan tersimpan dengan rapi.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="info-glass-card p-3 h-100">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="info-icon info-icon-green me-2">
                                                        <i class="fa-solid fa-user-shield"></i>
                                                    </div>
                                                    <h6 class="info-title mb-0">Kontrol Akses</h6>
                                                </div>
                                                <p class="info-text mb-0">
                                                    Akses sistem lebih terjaga melalui peran dan otorisasi pengguna.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-4 pb-4 pt-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <small class="text-white-50">
                                            Solusi digital untuk tata kelola yang cepat, akuntabel, dan modern.
                                        </small>
                                        <a href="{{ url('/login') }}" class="btn btn-sm btn-white mb-0">
                                            Mulai Sekarang
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- Footer -->
                <footer class="container-fluid px-4 px-lg-5 pb-3 ">
                    <div class="footer-mini d-flex justify-content-between align-items-center flex-wrap gap-2">

                        <!-- Kiri -->
                        <div class="footer-mini__left">
                            <strong>SITANGKAS</strong>
                            <span class="d-none d-sm-inline">— Aplikasi Tandatangan Keuangan dan Aset</span>
                        </div>

                        <!-- Kanan -->
                        <div class="footer-mini__right">
                            <span class="footer-year">
                                &copy; {{ date('Y') }}
                            </span>

                            <span class="footer-separator">•</span>

                            <span dir="rtl" class="footer-arabic">بِإِذْنِ اللّٰهِ</span>

                            <span class="footer-separator d-none d-sm-inline">•</span>

                            <span class="d-none d-sm-inline footer-note">
                                Terwujud atas izin Allah
                            </span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Modal: Hubungi Kami -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Hubungi Kami</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 fw-bold">Layanan bantuan aplikasi SITANGKAS</p>
                    <p class="mb-0 text-sm">
                        Silakan hubungi admin aplikasi melalui kanal resmi instansi Anda.
                        Ganti bagian ini dengan email, nomor telepon, atau helpdesk resmi.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Bantuan -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">Bantuan</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <ul class="mb-0 ps-3">
                        <li>Gunakan akun yang telah terdaftar.</li>
                        <li>Pastikan proses login dilakukan melalui halaman resmi.</li>
                        <li>Hubungi admin apabila akses atau data tidak sesuai.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Kebijakan -->
    <div class="modal fade" id="policyModal" tabindex="-1" aria-labelledby="policyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="policyModalLabel">Kebijakan</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-sm">
                        Konten kebijakan dapat diisi dengan kebijakan penggunaan sistem, keamanan akun,
                        dan perlindungan data sesuai standar instansi Anda.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: FAQ -->
    <div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="faqModalLabel">FAQ</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="fw-bold">Siapa yang dapat mengakses SITANGKAS?</div>
                        <div class="text-sm">Pengguna yang telah memiliki akun dan otorisasi resmi.</div>
                    </div>
                    <div class="mb-3">
                        <div class="fw-bold">Apakah proses dokumen dapat dipantau?</div>
                        <div class="text-sm">Ya, status dokumen dapat dimonitor sesuai hak akses pengguna.</div>
                    </div>
                    <div>
                        <div class="fw-bold">Bagaimana jika mengalami kendala login?</div>
                        <div class="text-sm">Silakan hubungi administrator atau helpdesk resmi instansi Anda.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuEl = document.getElementById('mobileMenuCollapse');

            if (mobileMenuEl) {
                const mobileMenu = bootstrap.Collapse.getOrCreateInstance(mobileMenuEl, {
                    toggle: false
                });

                document.querySelectorAll(
                        '#mobileMenuCollapse .mobile-nav-link, #mobileMenuCollapse .mobile-nav-action')
                    .forEach(function(item) {
                        item.addEventListener('click', function() {
                            mobileMenu.hide();
                        });
                    });
            }
        });
    </script>
</body>

</html>
