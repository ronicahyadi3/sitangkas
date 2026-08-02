<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | SITANGKAS</title>
    <meta name="author" content="Achmad Roni Cahyadi & Darfian Ardiansyah">
    <meta name="description"
        content="Tentang SITANGKAS - Aplikasi Tandatangan Keuangan dan Aset untuk mendukung proses pengajuan pencairan APBD yang lebih cepat, aman, tertib, dan transparan.">
    <meta name="theme-color" content="#1b4f8c">

    <link rel="icon" type="image/png" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            --sitangkas-muted: #67748e;
            --sitangkas-border: rgba(15, 47, 87, 0.08);
            --sitangkas-shadow: 0 18px 50px rgba(15, 47, 87, 0.12);
        }

        html,
        body {
            font-family: 'Open Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47, 141, 243, 0.18), transparent 25%),
                radial-gradient(circle at bottom right, rgba(34, 181, 115, 0.12), transparent 22%),
                linear-gradient(135deg, #f8fbff 0%, #eef5ff 55%, #f7fbff 100%);
            color: var(--sitangkas-text);
            scroll-behavior: smooth;
        }

        body {
            overflow-x: hidden;
        }

        .page-shell {
            min-height: 100vh;
            padding: 1rem;
        }

        .about-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
            box-shadow: var(--sitangkas-shadow);
            border: 1px solid rgba(255, 255, 255, 0.7);
        }

        .about-wrapper::before {
            content: "";
            position: absolute;
            width: 440px;
            height: 440px;
            top: -160px;
            right: -120px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(47, 141, 243, 0.16), transparent 70%);
            z-index: 0;
        }

        .about-wrapper::after {
            content: "";
            position: absolute;
            width: 360px;
            height: 360px;
            bottom: -140px;
            left: -100px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(34, 181, 115, 0.12), transparent 70%);
            z-index: 0;
        }

        .content-layer {
            position: relative;
            z-index: 2;
        }

        .topbar-main {
            min-height: 78px;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: .9rem;
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
            width: 5.7rem;
            height: auto;
        }

        .brand-title {
            margin-bottom: 0;
            font-weight: 700;
            letter-spacing: .5px;
            color: var(--sitangkas-primary);
        }

        .brand-subtitle {
            margin-bottom: 0;
            color: #8392ab;
            font-size: .85rem;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .52rem .95rem;
            border-radius: 999px;
            background: rgba(47, 141, 243, 0.10);
            color: var(--sitangkas-primary);
            font-size: .8rem;
            font-weight: 700;
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

        .login-btn-responsive,
        .mobile-login-btn,
        .mobile-menu-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
        }

        .mobile-login-btn,
        .mobile-menu-btn {
            width: 42px;
            height: 42px;
            padding: 0 !important;
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(15, 47, 87, 0.08);
        }

        .mobile-nav-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--sitangkas-border);
            border-radius: 22px;
            padding: 1rem;
            box-shadow: 0 12px 28px rgba(15, 47, 87, .08);
            backdrop-filter: blur(10px);
        }

        .mobile-nav-list {
            display: flex;
            flex-direction: column;
            gap: .45rem;
        }

        .mobile-nav-link {
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
        }

        .mobile-nav-link:hover {
            color: var(--sitangkas-primary);
            background: #f8fbff;
            border-color: #dbeafe;
        }

        .hero-section {
            padding: 2.2rem 0 1.25rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .6rem 1rem;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(27, 79, 140, .10), rgba(47, 141, 243, .10));
            color: var(--sitangkas-primary);
            font-weight: 700;
            font-size: .84rem;
            box-shadow: 0 10px 22px rgba(27, 79, 140, 0.06);
        }

        .hero-title {
            font-size: clamp(2.25rem, 4.4vw, 4rem);
            line-height: 1.03;
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: .9rem;
        }

        .hero-title .highlight {
            background: linear-gradient(135deg, var(--sitangkas-primary), var(--sitangkas-secondary), var(--sitangkas-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-lead {
            font-size: 1.06rem;
            font-weight: 700;
            color: var(--sitangkas-primary);
            margin-bottom: .9rem;
        }

        .hero-desc {
            font-size: 1rem;
            line-height: 1.82;
            color: #5e72a4;
            max-width: 720px;
            margin-bottom: 1.3rem;
        }

        .hero-action-group {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
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

        .hero-trust {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.25rem;
        }

        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .75rem .95rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, .82);
            border: 1px solid rgba(15, 47, 87, 0.06);
            box-shadow: 0 10px 24px rgba(15, 47, 87, 0.04);
            color: var(--sitangkas-text);
            font-size: .88rem;
            font-weight: 600;
        }

        .hero-trust-item i {
            color: var(--sitangkas-primary);
        }

        .mockup-card {
            border: 0;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(145deg, #0f2f57 0%, #1b4f8c 48%, #2f8df3 100%);
            color: #fff;
            box-shadow: 0 18px 38px rgba(15, 47, 87, 0.18);
        }

        .mockup-top {
            padding: 1rem 1rem 0;
        }

        .mockup-body {
            padding: 1rem;
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

        .mockup-frame {
            position: relative;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 20px;
            padding: 1rem;
            backdrop-filter: blur(6px);
        }

        .mockup-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .18);
            color: #fff;
            font-size: .74rem;
            font-weight: 700;
            padding: .45rem .7rem;
            border-radius: 999px;
        }

        .mockup-image {
            width: 100%;
            height: auto;
            border-radius: 16px;
            display: block;
            object-fit: cover;
            background: rgba(255, 255, 255, .08);
        }

        .mockup-fallback {
            background: rgba(255, 255, 255, .08);
            border-radius: 16px;
            padding: 1.25rem;
        }

        .mini-metric {
            border-radius: 16px;
            background: rgba(255, 255, 255, .10);
            padding: .9rem 1rem;
            border: 1px solid rgba(255, 255, 255, .10);
        }

        .mini-metric-label {
            font-size: .76rem;
            color: rgba(255, 255, 255, .72);
            margin-bottom: .2rem;
        }

        .mini-metric-value {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0;
        }

        .section-block {
            padding: 1.35rem 0;
        }

        .section-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .45rem .85rem;
            border-radius: 999px;
            background: rgba(27, 79, 140, 0.08);
            color: var(--sitangkas-primary);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .2px;
            margin-bottom: .85rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: .65rem;
        }

        .section-desc {
            color: var(--sitangkas-muted);
            line-height: 1.78;
            margin-bottom: 1.4rem;
            max-width: 780px;
        }

        .section-surface {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(245, 249, 255, 0.98));
            border: 1px solid rgba(15, 47, 87, 0.06);
            border-radius: 28px;
            box-shadow: 0 14px 30px rgba(15, 47, 87, 0.06);
        }

        .soft-card {
            background: rgba(255, 255, 255, .94);
            border: 1px solid rgba(15, 47, 87, 0.06);
            border-radius: 24px;
            box-shadow: 0 10px 24px rgba(15, 47, 87, 0.05);
            height: 100%;
        }

        .outline-card {
            background: #fff;
            border: 1px solid rgba(27, 79, 140, 0.10);
            border-radius: 24px;
            box-shadow: 0 8px 18px rgba(15, 47, 87, 0.04);
            height: 100%;
        }

        .document-card {
            background: #fff;
            border-radius: 24px;
            border: 1px solid rgba(15, 47, 87, 0.08);
            border-left: 5px solid var(--sitangkas-primary);
            box-shadow: 0 10px 22px rgba(15, 47, 87, 0.05);
            height: 100%;
        }

        .soft-card .card-body,
        .outline-card .card-body,
        .document-card .card-body {
            padding: 1.3rem;
        }

        .feature-icon,
        .goal-icon,
        .user-icon,
        .contact-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(47, 141, 243, .12), rgba(34, 181, 115, .10));
            color: var(--sitangkas-primary);
            font-size: 1.1rem;
            margin-bottom: .9rem;
        }

        .card-title-custom {
            font-size: 1rem;
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: .45rem;
        }

        .card-text-custom {
            font-size: .9rem;
            line-height: 1.75;
            color: var(--sitangkas-muted);
            margin-bottom: 0;
        }

        .document-meta {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            font-size: .78rem;
            color: #5e72a4;
            font-weight: 700;
            margin-top: .8rem;
        }

        .timeline-wrap {
            position: relative;
            margin-top: .35rem;
        }

        .timeline-wrap::before {
            content: "";
            position: absolute;
            left: 18px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: linear-gradient(to bottom, var(--sitangkas-primary), rgba(47, 141, 243, 0.20));
        }

        .timeline-item {
            position: relative;
            padding-left: 58px;
            margin-bottom: 1.45rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: 8px;
            top: 4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--sitangkas-primary), var(--sitangkas-secondary));
            border: 4px solid rgba(47, 141, 243, 0.15);
        }

        .timeline-date {
            font-size: .82rem;
            font-weight: 700;
            color: var(--sitangkas-primary);
            margin-bottom: .2rem;
        }

        .timeline-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--sitangkas-dark);
            margin-bottom: .25rem;
        }

        .timeline-text {
            color: var(--sitangkas-muted);
            line-height: 1.7;
            margin-bottom: 0;
        }

        .cta-panel {
            border-radius: 28px;
            background: linear-gradient(145deg, #0f2f57 0%, #1b4f8c 55%, #2f8df3 100%);
            color: #fff;
            box-shadow: 0 18px 38px rgba(15, 47, 87, 0.18);
            overflow: hidden;
        }

        .cta-panel .card-body {
            padding: 1.5rem;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            padding: .95rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, .12);
        }

        .contact-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .contact-icon {
            margin-bottom: 0;
            background: rgba(255, 255, 255, .14);
            color: #fff;
        }

        .contact-label {
            font-size: .82rem;
            color: rgba(255, 255, 255, .72);
            margin-bottom: .2rem;
        }

        .contact-value {
            color: #fff;
            font-weight: 600;
            margin-bottom: 0;
            word-break: break-word;
        }

        .contact-value a {
            color: #fff;
            text-decoration: none;
        }

        .contact-value a:hover {
            text-decoration: underline;
        }

        .footer-mini {
            font-size: .82rem;
            color: #8392ab;
        }

        @media (max-width: 1199.98px) {
            .page-shell {
                padding: .75rem;
            }

            .about-wrapper {
                border-radius: 24px;
            }

            .brand-title {
                font-size: 1.15rem;
            }

            .brand-subtitle {
                font-size: .78rem;
            }
        }

        @media (max-width: 991.98px) {
            .page-shell {
                padding: .5rem;
            }

            .about-wrapper {
                border-radius: 20px;
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
                font-size: 2rem;
                line-height: 1.1;
            }

            .hero-lead,
            .hero-desc {
                font-size: .95rem;
            }

            .hero-action-group {
                flex-direction: column;
            }

            .hero-action-group .btn {
                width: 100%;
            }

            .hero-trust {
                flex-direction: column;
            }

            .footer-mini {
                flex-direction: column;
                text-align: center;
                gap: .35rem !important;
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

            .chip {
                font-size: .72rem;
                padding: .45rem .75rem;
            }

            .hero-badge {
                font-size: .76rem;
            }

            .hero-title {
                font-size: 1.72rem;
            }

            .hero-desc,
            .section-desc,
            .card-text-custom,
            .timeline-text {
                font-size: .9rem;
            }

            .section-title {
                font-size: 1.5rem;
            }
        }

        .nav-mini-link:focus-visible,
        .mobile-nav-link:focus-visible,
        .btn:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(47, 141, 243, 0.25);
            outline-offset: 3px;
        }

        html {
            scroll-padding-top: 16px;
        }

        .mockup-image {
            aspect-ratio: 16 / 10;
        }

        .hero-badge-premium {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            padding: .75rem 1.1rem;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(27, 79, 140, .14), rgba(47, 141, 243, .10));
            border: 1px solid rgba(47, 141, 243, .14);
            box-shadow: 0 10px 24px rgba(27, 79, 140, .08);
        }

        .hero-badge-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--sitangkas-primary), var(--sitangkas-secondary));
            color: #fff;
            flex-shrink: 0;
        }

        .hero-badge-text {
            font-size: 1rem;
            font-weight: 800;
            color: var(--sitangkas-dark);
            letter-spacing: .3px;
        }
    </style>
    @include('inc.standalone-dark-mode')
</head>

<body>
    <div class="page-shell">
        <div class="about-wrapper">
            <div class="content-layer">

                <!-- Topbar -->
                <div class="container-fluid px-4 px-lg-5 section-block" id="menu">
                    <div class="topbar-main d-flex justify-content-between align-items-center gap-3">
                        <div class="topbar-brand">
                            <div class="brand-logos">
                                <img src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}"
                                    alt="Logo Pemerintah Kota Malang" class="logo-pemkot" loading="lazy">
                                <img src="{{ asset('assets/img/sitangkas_logo_only.png') }}" alt="Logo SITANGKAS"
                                    class="logo-sitangkas" loading="lazy">
                            </div>
                            <div>
                                <h4 class="brand-title">SITANGKAS</h4>
                                <p class="brand-subtitle">Aplikasi Tandatangan Keuangan dan Aset</p>
                            </div>
                        </div>

                        <!-- Desktop -->
                        <div class="d-none d-lg-flex align-items-center gap-3 flex-wrap justify-content-end">
                            <span class="chip">
                                <i class="fa-solid fa-circle-info"></i>
                                Tentang Aplikasi
                            </span>

                            <nav class="d-flex align-items-center gap-3 flex-wrap"
                                aria-label="Navigasi halaman Tentang">
                                <a href="{{ route('landing') }}" class="nav-mini-link">Beranda</a>
                                <a href="#tentang" class="nav-mini-link js-section-link" data-target="tentang"
                                    aria-current="page">Tentang</a>
                                <a href="#fitur" class="nav-mini-link js-section-link" data-target="fitur">Fitur</a>
                                <a href="#pengguna" class="nav-mini-link js-section-link"
                                    data-target="pengguna">Pengguna</a>
                                <a href="#dasar-hukum" class="nav-mini-link js-section-link"
                                    data-target="dasar-hukum">Dasar Hukum</a>
                                <a href="#kontak" class="nav-mini-link js-section-link" data-target="kontak">Kontak</a>
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
                            <div class="mobile-nav-list" aria-label="Navigasi mobile halaman Tentang">
                                <a href="{{ route('landing') }}" class="mobile-nav-link">Beranda</a>
                                <a href="#tentang" class="mobile-nav-link js-mobile-section-link"
                                    data-target="tentang">Tentang</a>
                                <a href="#fitur" class="mobile-nav-link js-mobile-section-link"
                                    data-target="fitur">Fitur</a>
                                <a href="#pengguna" class="mobile-nav-link js-mobile-section-link"
                                    data-target="pengguna">Pengguna</a>
                                <a href="#dasar-hukum" class="mobile-nav-link js-mobile-section-link"
                                    data-target="dasar-hukum">Dasar Hukum</a>
                                <a href="#kontak" class="mobile-nav-link js-mobile-section-link"
                                    data-target="kontak">Kontak</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hero -->
                <section class="container-fluid px-4 px-lg-5 hero-section" id="tentang">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <span class="hero-badge hero-badge-premium mb-3">
                                <span class="hero-badge-icon">
                                    <i class="fa-solid fa-bolt"></i>
                                </span>
                                <span class="hero-badge-text">Cair.. Cair.. Cair</span>
                            </span>

                            <h1 class="hero-title">
                                Tentang <span class="highlight">SITANGKAS</span>
                            </h1>

                            <div class="hero-lead">
                                Kelola proses pengajuan pencairan APBD dengan lebih cepat, aman, tertib, dan transparan.
                            </div>

                            <p class="hero-desc">
                                SITANGKAS hadir sebagai Aplikasi Tandatangan Keuangan dan Aset yang digunakan
                                di lingkungan Pemerintah Kota Malang untuk mendukung proses penandatanganan,
                                pengiriman, validasi, pemantauan, dan penyimpanan dokumen pencairan belanja daerah
                                secara digital.
                            </p>

                            <div class="hero-action-group">
                                <a href="{{ url('/login') }}" class="btn btn-sitangkas btn-lg mb-0">
                                    <i class="fa-solid fa-right-to-bracket me-2"></i>
                                    Masuk ke Aplikasi
                                </a>
                                <a href="#dasar-hukum" class="btn btn-white btn-lg mb-0 shadow-sm">
                                    <i class="fa-solid fa-scale-balanced me-2 text-primary"></i>
                                    Lihat Dasar Hukum
                                </a>
                            </div>

                            <div class="hero-trust">
                                <div class="hero-trust-item">
                                    <i class="fa-solid fa-building-columns"></i>
                                    Digunakan oleh SKPD / Unit Kerja
                                </div>
                                <div class="hero-trust-item">
                                    <i class="fa-solid fa-handshake"></i>
                                    Kolaborasi BKAD dan Diskominfo Kota Malang
                                </div>
                                <div class="hero-trust-item">
                                    <i class="fa-solid fa-calendar-check"></i>
                                    Implementasi resmi sejak 2024
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="mockup-card">
                                <div class="mockup-top">
                                    <h5 class="text-white mb-1">SITANGKAS Overview</h5>
                                    <p class="text-white-50 mb-0 small">Profil aplikasi dan dukungan tata kelola
                                        digital</p>
                                </div>
                                <div class="mockup-body">
                                    <div class="mockup-frame">
                                        <div class="window-dots">
                                            <span></span><span></span><span></span>
                                        </div>

                                        <span class="mockup-badge">APBD DIGITAL</span>

                                        @php
                                            $mockupPath = 'assets/img/mockup-sitangkas-about.png';
                                            $mockupExists = file_exists(public_path($mockupPath));
                                        @endphp

                                        @if ($mockupExists)
                                            <img src="{{ asset($mockupPath) }}" alt="Mockup aplikasi SITANGKAS"
                                                class="mockup-image">
                                        @else
                                            <div class="mockup-fallback">
                                                <div class="row g-3">
                                                    <div class="col-6">
                                                        <div class="mini-metric">
                                                            <div class="mini-metric-label">Keamanan</div>
                                                            <p class="mini-metric-value">Verified</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="mini-metric">
                                                            <div class="mini-metric-label">Arsip</div>
                                                            <p class="mini-metric-value">Digital</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="mini-metric">
                                                            <div class="mini-metric-label">Fokus Sistem</div>
                                                            <p class="mini-metric-value">
                                                                Pengajuan pencairan APBD yang cepat, aman, tertib, dan
                                                                transparan
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="row g-3 mt-2">
                                        <div class="col-6">
                                            <div class="mini-metric">
                                                <div class="mini-metric-label">Monitoring</div>
                                                <p class="mini-metric-value">Real Time</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mini-metric">
                                                <div class="mini-metric-label">Workflow</div>
                                                <p class="mini-metric-value">Terstruktur</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Overview -->
                <section class="container-fluid px-4 px-lg-5 section-block">
                    <div class="section-surface p-4 p-lg-5">
                        <div class="section-eyebrow">
                            <i class="fa-solid fa-circle-info"></i>
                            Profil Aplikasi
                        </div>
                        <div class="row g-4 align-items-center">
                            <div class="col-lg-8">
                                <h2 class="section-title mb-3">Gambaran Umum SITANGKAS</h2>
                                <p class="section-desc mb-0">
                                    SITANGKAS merupakan aplikasi digital yang mendukung proses penandatanganan,
                                    pengiriman, validasi, pelacakan, dan penyimpanan arsip dokumen pencairan belanja
                                    daerah.
                                    Aplikasi ini digunakan oleh SKPD dan unit kerja di lingkungan Pemerintah Kota Malang
                                    untuk mendukung tata kelola dokumen yang lebih efisien, tertib, mudah ditelusuri,
                                    dan selaras dengan kebutuhan akuntabilitas pengelolaan keuangan daerah.
                                </p>
                            </div>
                            <div class="col-lg-4">
                                <div class="outline-card">
                                    <div class="card-body">
                                        <div class="goal-icon">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </div>
                                        <div class="card-title-custom">Tagline Resmi</div>
                                        <p class="card-text-custom mb-3">Cepat, Aman, dan Terstruktur</p>

                                        <hr class="horizontal dark my-3">

                                        <div class="card-title-custom">Fokus Utama</div>
                                        <p class="card-text-custom">
                                            Mendukung proses pengajuan pencairan APBD yang lebih cepat, aman,
                                            tertib, dan transparan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Goals -->
                <section class="container-fluid px-4 px-lg-5 section-block">
                    <div class="section-eyebrow">
                        <i class="fa-solid fa-bullseye"></i>
                        Tujuan Sistem
                    </div>
                    <h2 class="section-title">Tujuan Utama SITANGKAS</h2>
                    <p class="section-desc">
                        SITANGKAS dikembangkan untuk mendukung percepatan, ketertiban, dan efisiensi pengelolaan
                        dokumen pencairan belanja daerah di lingkungan Pemerintah Kota Malang.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <div class="soft-card">
                                <div class="card-body">
                                    <div class="goal-icon"><i class="fa-solid fa-bolt"></i></div>
                                    <h6 class="card-title-custom">Mempercepat Proses Pencairan</h6>
                                    <p class="card-text-custom">
                                        Mendukung pengajuan dan penanganan dokumen pencairan keuangan secara lebih cepat
                                        dan efisien.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="soft-card">
                                <div class="card-body">
                                    <div class="goal-icon"><i class="fa-solid fa-folder-tree"></i></div>
                                    <h6 class="card-title-custom">Menertibkan Dokumen</h6>
                                    <p class="card-text-custom">
                                        Menjaga keteraturan pengelolaan dokumen keuangan dan aset dalam satu alur kerja
                                        digital.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="soft-card">
                                <div class="card-body">
                                    <div class="goal-icon"><i class="fa-solid fa-database"></i></div>
                                    <h6 class="card-title-custom">Arsip Digital</h6>
                                    <p class="card-text-custom">
                                        Menyimpan arsip dokumen dalam bentuk digital agar lebih aman dan mudah diakses.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="soft-card">
                                <div class="card-body">
                                    <div class="goal-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                                    <h6 class="card-title-custom">Pencarian Dokumen</h6>
                                    <p class="card-text-custom">
                                        Memudahkan pencarian dan penelusuran dokumen sesuai kebutuhan pekerjaan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="soft-card">
                                <div class="card-body">
                                    <div class="goal-icon"><i class="fa-solid fa-chart-line"></i></div>
                                    <h6 class="card-title-custom">Monitoring Proses</h6>
                                    <p class="card-text-custom">
                                        Mendukung pemantauan status dan progres dokumen secara lebih jelas dan terukur.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="soft-card">
                                <div class="card-body">
                                    <div class="goal-icon"><i class="fa-solid fa-globe"></i></div>
                                    <h6 class="card-title-custom">Kerja Real Time</h6>
                                    <p class="card-text-custom">
                                        Mendukung pelaksanaan pekerjaan secara real time dan dari berbagai lokasi sesuai
                                        kebutuhan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Features -->
                <section class="container-fluid px-4 px-lg-5 section-block" id="fitur">
                    <div class="section-eyebrow">
                        <i class="fa-solid fa-grid-2"></i>
                        Fitur Utama
                    </div>
                    <h2 class="section-title">Fitur yang Mendukung Alur Kerja</h2>
                    <p class="section-desc">
                        Fitur SITANGKAS dirancang untuk mendukung alur kerja dokumen pencairan secara lebih jelas,
                        terkontrol, dan terdokumentasi.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <div class="outline-card">
                                <div class="card-body">
                                    <div class="feature-icon"><i class="fa-solid fa-right-to-bracket"></i></div>
                                    <h6 class="card-title-custom">Login Pengguna</h6>
                                    <p class="card-text-custom">Akses sistem dilakukan sesuai akun dan kewenangan
                                        masing-masing pengguna.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="outline-card">
                                <div class="card-body">
                                    <div class="feature-icon"><i class="fa-solid fa-circle-check"></i></div>
                                    <h6 class="card-title-custom">Validasi Dokumen</h6>
                                    <p class="card-text-custom">Mendukung pemeriksaan dan validasi dokumen sesuai
                                        tahapan proses yang berlaku.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="outline-card">
                                <div class="card-body">
                                    <div class="feature-icon"><i class="fa-solid fa-route"></i></div>
                                    <h6 class="card-title-custom">Tracking Status</h6>
                                    <p class="card-text-custom">Memudahkan pemantauan posisi dan progres dokumen secara
                                        lebih real time.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="outline-card">
                                <div class="card-body">
                                    <div class="feature-icon"><i class="fa-solid fa-folder-open"></i></div>
                                    <h6 class="card-title-custom">Arsip Dokumen Digital</h6>
                                    <p class="card-text-custom">Dokumen tersimpan lebih tertib dan mudah ditelusuri
                                        sesuai kebutuhan organisasi.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="outline-card">
                                <div class="card-body">
                                    <div class="feature-icon"><i class="fa-solid fa-file-signature"></i></div>
                                    <h6 class="card-title-custom">Tandatangan Elektronik Terverifikasi</h6>
                                    <p class="card-text-custom">Mendukung proses penandatanganan dokumen secara digital
                                        dalam alur kerja resmi.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-4">
                            <div class="outline-card">
                                <div class="card-body">
                                    <div class="feature-icon"><i class="fa-solid fa-gauge-high"></i></div>
                                    <h6 class="card-title-custom">Dashboard</h6>
                                    <p class="card-text-custom">Menyajikan gambaran ringkas proses, status, dan
                                        informasi utama dalam satu tampilan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Users & manager -->
                <section class="container-fluid px-4 px-lg-5 section-block" id="pengguna">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="section-eyebrow">
                                <i class="fa-solid fa-users"></i>
                                Pengguna Aplikasi
                            </div>
                            <h2 class="section-title">Siapa yang Menggunakan SITANGKAS</h2>
                            <p class="section-desc">
                                SITANGKAS digunakan oleh SKPD dan unit kerja di lingkungan Pemerintah Kota Malang,
                                serta pejabat dan pengelola keuangan sesuai kewenangan masing-masing.
                            </p>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="soft-card">
                                        <div class="card-body">
                                            <div class="user-icon"><i class="fa-solid fa-building-user"></i></div>
                                            <h6 class="card-title-custom">SKPD & Unit Kerja</h6>
                                            <p class="card-text-custom">
                                                Digunakan oleh perangkat daerah dan unit kerja di lingkungan Pemerintah
                                                Kota Malang.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="soft-card">
                                        <div class="card-body">
                                            <div class="user-icon"><i class="fa-solid fa-user-check"></i></div>
                                            <h6 class="card-title-custom">Pejabat Penandatangan</h6>
                                            <p class="card-text-custom">
                                                Mendukung pejabat yang memiliki kewenangan penandatanganan dokumen
                                                secara digital.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="soft-card">
                                        <div class="card-body">
                                            <div class="user-icon"><i class="fa-solid fa-user-shield"></i></div>
                                            <h6 class="card-title-custom">Verifikator</h6>
                                            <p class="card-text-custom">
                                                Membantu proses verifikasi dan kontrol dokumen dalam tahapan pencairan.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="soft-card">
                                        <div class="card-body">
                                            <div class="user-icon"><i class="fa-solid fa-wallet"></i></div>
                                            <h6 class="card-title-custom">Pengelola Keuangan</h6>
                                            <p class="card-text-custom">
                                                Mencakup bendahara, pejabat penatausahaan keuangan, dan peran terkait
                                                lainnya.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="section-eyebrow">
                                <i class="fa-solid fa-landmark"></i>
                                Pengelola dan Kolaborasi
                            </div>
                            <h2 class="section-title">BKAD dan Diskominfo Kota Malang</h2>
                            <p class="section-desc">
                                SITANGKAS dikelola oleh BKAD Kota Malang dan didukung dalam kolaborasi bersama
                                Dinas Komunikasi dan Informatika Kota Malang untuk mendukung keberlangsungan layanan
                                aplikasi,
                                tata kelola dokumen, dan pemanfaatan sistem secara digital.
                            </p>

                            <div class="section-surface p-4">
                                <div class="d-flex align-items-start gap-3 mb-4">
                                    <div class="goal-icon mb-0">
                                        <i class="fa-solid fa-landmark"></i>
                                    </div>
                                    <div>
                                        <div class="card-title-custom">BKAD Kota Malang</div>
                                        <p class="card-text-custom">
                                            Berperan dalam pengelolaan aplikasi untuk mendukung tata kelola proses,
                                            akuntabilitas, dan ketertiban dokumen pencairan APBD.
                                        </p>
                                    </div>
                                </div>

                                <div class="d-flex align-items-start gap-3">
                                    <div class="goal-icon mb-0">
                                        <i class="fa-solid fa-laptop-code"></i>
                                    </div>
                                    <div>
                                        <div class="card-title-custom">Diskominfo Kota Malang</div>
                                        <p class="card-text-custom">
                                            Mendukung kolaborasi pengembangan, pemanfaatan, dan keberlangsungan layanan
                                            aplikasi
                                            sebagai bagian dari implementasi sistem digital di lingkungan Pemerintah
                                            Kota Malang.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Legal -->
                <section class="container-fluid px-4 px-lg-5 section-block" id="dasar-hukum">
                    <div class="section-eyebrow">
                        <i class="fa-solid fa-scale-balanced"></i>
                        Dasar Hukum
                    </div>
                    <h2 class="section-title">Dasar Hukum dan Pedoman Penggunaan</h2>
                    <p class="section-desc">
                        Penggunaan SITANGKAS didukung oleh dokumen dan pedoman resmi sebagai dasar penerapan aplikasi
                        dalam proses dokumen pencairan belanja daerah di lingkungan Pemerintah Kota Malang.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="document-card">
                                <div class="card-body">
                                    <h6 class="card-title-custom mb-2">Keputusan Wali Kota Malang Nomor 237 Tahun 2024
                                    </h6>
                                    <p class="card-text-custom">
                                        Penetapan penggunaan SITANGKAS sebagai aplikasi tanda tangan digital
                                        dalam dokumen pencairan belanja daerah.
                                    </p>
                                    <div class="document-meta">
                                        <i class="fa-solid fa-file-lines"></i>
                                        Penetapan Penggunaan
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="document-card">
                                <div class="card-body">
                                    <h6 class="card-title-custom mb-2">Keputusan Wali Kota Malang Nomor 238 Tahun 2024
                                    </h6>
                                    <p class="card-text-custom">
                                        Penetapan pengguna aplikasi SITANGKAS beserta peran penggunaan
                                        sesuai jabatan dan kewenangan.
                                    </p>
                                    <div class="document-meta">
                                        <i class="fa-solid fa-user-check"></i>
                                        Penetapan Pengguna
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="document-card">
                                <div class="card-body">
                                    <h6 class="card-title-custom mb-2">Surat Edaran Wali Kota Malang Nomor 16 Tahun
                                        2024</h6>
                                    <p class="card-text-custom">
                                        Pedoman umum penggunaan SITANGKAS pada proses penandatanganan,
                                        pengiriman, dan penyimpanan arsip dokumen pencairan belanja daerah.
                                    </p>
                                    <div class="document-meta">
                                        <i class="fa-solid fa-book-open"></i>
                                        Pedoman Penggunaan
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="document-card">
                                <div class="card-body">
                                    <h6 class="card-title-custom mb-2">Surat Implementasi SITANGKAS</h6>
                                    <p class="card-text-custom">
                                        Menjelaskan penggunaan live aplikasi, akses resmi, dan implementasi tahap awal
                                        di lingkungan Pemerintah Kota Malang.
                                    </p>
                                    <div class="document-meta">
                                        <i class="fa-solid fa-rocket"></i>
                                        Implementasi
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Timeline -->
                <section class="container-fluid px-4 px-lg-5 section-block">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="section-eyebrow">
                                <i class="fa-solid fa-timeline"></i>
                                Perjalanan Implementasi
                            </div>
                            <h2 class="section-title">Timeline Implementasi SITANGKAS</h2>
                            <p class="section-desc">
                                Implementasi SITANGKAS dilakukan melalui tahapan penetapan, pedoman penggunaan,
                                hingga penggunaan live dan penerapan bertahap di lingkungan Pemerintah Kota Malang.
                            </p>
                        </div>

                        <div class="col-lg-7">
                            <div class="section-surface p-4 p-lg-5">
                                <div class="timeline-wrap">
                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-date">1 Oktober 2024</div>
                                        <div class="timeline-title">Penetapan Penggunaan dan Pengguna</div>
                                        <p class="timeline-text">
                                            Penetapan penggunaan SITANGKAS dan penetapan pengguna aplikasi dituangkan
                                            dalam keputusan resmi Wali Kota Malang.
                                        </p>
                                    </div>

                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-date">17 Oktober 2024</div>
                                        <div class="timeline-title">Surat Edaran Penggunaan</div>
                                        <p class="timeline-text">
                                            Surat Edaran diterbitkan sebagai pedoman umum penggunaan SITANGKAS.
                                        </p>
                                    </div>

                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-date">22 Oktober 2024</div>
                                        <div class="timeline-title">Implementasi Tahap Awal</div>
                                        <p class="timeline-text">
                                            Surat implementasi SITANGKAS disampaikan kepada perangkat daerah terkait.
                                        </p>
                                    </div>

                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-date">28 Oktober 2024</div>
                                        <div class="timeline-title">Penggunaan Live</div>
                                        <p class="timeline-text">
                                            SITANGKAS mulai digunakan secara live pada tahap awal untuk proses pengajuan
                                            tertentu.
                                        </p>
                                    </div>

                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-date">Des 2024 – Jan 2025</div>
                                        <div class="timeline-title">Implementasi Bertahap</div>
                                        <p class="timeline-text">
                                            Penerapan SITANGKAS dilakukan bertahap hingga cakupan penggunaan menjadi
                                            lebih luas.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Contact + closing -->
                <section class="container-fluid px-4 px-lg-5 section-block pb-5" id="kontak">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="section-eyebrow">
                                <i class="fa-solid fa-headset"></i>
                                Kontak Bantuan
                            </div>
                            <h2 class="section-title">Butuh Bantuan Penggunaan?</h2>
                            <p class="section-desc">
                                Untuk informasi dan bantuan penggunaan aplikasi SITANGKAS, silakan menghubungi kanal
                                bantuan berikut.
                            </p>

                            <div class="cta-panel">
                                <div class="card-body">
                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-building"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">Pengelola</div>
                                            <p class="contact-value">BKAD Kota Malang</p>
                                        </div>
                                    </div>

                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-laptop-code"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">Kolaborasi</div>
                                            <p class="contact-value">Dinas Komunikasi dan Informatika Kota Malang</p>
                                        </div>
                                    </div>

                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-envelope"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">Email Helpdesk</div>
                                            <p class="contact-value">
                                                <a
                                                    href="mailto:bid.statistikmalang@gmail.com">bid.statistikmalang@gmail.com</a>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-phone"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">WA / Helpdesk Prosedur</div>
                                            <p class="contact-value">
                                                <a href="https://wa.me/6281217406516" target="_blank"
                                                    rel="noopener noreferrer">
                                                    +62 812-1740-6516
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-phone"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">WA / Helpdesk Teknis</div>
                                            <p class="contact-value">
                                                <a href="https://wa.me/6281277886070" target="_blank"
                                                    rel="noopener noreferrer">
                                                    +62 821-3170-1177
                                                </a>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-circle-info"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">Lingkup Layanan</div>
                                            <p class="contact-value">
                                                Bantuan akses, penggunaan aplikasi, dan kendala operasional.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="contact-item">
                                        <div class="contact-icon">
                                            <i class="fa-solid fa-map-location-dot"></i>
                                        </div>
                                        <div>
                                            <div class="contact-label">General Office</div>
                                            <p class="contact-value">
                                                <a href="https://maps.app.goo.gl/qLwXScECQUNH2zv78" target="_blank"
                                                    rel="noopener noreferrer">
                                                    Jl. Tugu No.1, Kiduldalem, Kec. Klojen, Kota Malang, Jawa Timur
                                                    65119
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="section-surface p-4 p-lg-5 h-100">
                                <div class="section-eyebrow">
                                    <i class="fa-solid fa-flag-checkered"></i>
                                    Penutup
                                </div>
                                <h2 class="section-title">SITANGKAS untuk Tata Kelola yang Lebih Baik</h2>
                                <p class="section-desc">
                                    SITANGKAS hadir sebagai bagian dari upaya penguatan tata kelola dokumen pencairan
                                    belanja daerah
                                    yang lebih cepat, aman, tertib, dan transparan. Melalui pendekatan digital, aplikasi
                                    ini
                                    mendukung efisiensi proses kerja, mempermudah monitoring, serta menjaga ketertiban
                                    arsip
                                    dokumen di lingkungan Pemerintah Kota Malang.
                                </p>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="outline-card h-100">
                                            <div class="card-body">
                                                <div class="goal-icon"><i class="fa-solid fa-eye"></i></div>
                                                <div class="card-title-custom">Transparan</div>
                                                <p class="card-text-custom">
                                                    Mendukung pemantauan proses dokumen secara lebih jelas dan terukur.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="outline-card h-100">
                                            <div class="card-body">
                                                <div class="goal-icon"><i class="fa-solid fa-bolt"></i></div>
                                                <div class="card-title-custom">Efisien</div>
                                                <p class="card-text-custom">
                                                    Membantu proses kerja menjadi lebih cepat dan mudah dijalankan.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="outline-card h-100">
                                            <div class="card-body">
                                                <div class="goal-icon"><i class="fa-solid fa-lock"></i></div>
                                                <div class="card-title-custom">Aman</div>
                                                <p class="card-text-custom">
                                                    Mendukung kontrol akses dan proses dokumen yang lebih tertib.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="outline-card h-100">
                                            <div class="card-body">
                                                <div class="goal-icon"><i class="fa-solid fa-folder-tree"></i></div>
                                                <div class="card-title-custom">Tertib Arsip</div>
                                                <p class="card-text-custom">
                                                    Mendorong pengelolaan arsip digital yang lebih rapi dan mudah
                                                    ditelusuri.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 d-flex flex-wrap gap-3">
                                    <a href="{{ url('/login') }}" class="btn btn-sitangkas btn-lg mb-0">
                                        <i class="fa-solid fa-right-to-bracket me-2"></i>
                                        Masuk ke SITANGKAS
                                    </a>
                                    <a href="{{ url('/') }}" class="btn btn-white btn-lg mb-0 shadow-sm">
                                        <i class="fa-solid fa-arrow-left me-2 text-primary"></i>
                                        Kembali ke Beranda
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Footer -->
                <div class="container-fluid px-4 px-lg-5 pb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 footer-mini">
                        <span>
                            © {{ date('Y') }} <strong>SITANGKAS</strong> - Aplikasi Tandatangan Keuangan dan Aset
                        </span>
                        <div class="auth-footer-note">•
                            <span dir="rtl">بِإِذْنِ اللّٰهِ</span> •
                            Aplikasi ini terwujud atas izin Allah.
                        </div>
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

                document.querySelectorAll('#mobileMenuCollapse .mobile-nav-link').forEach(function(item) {
                    item.addEventListener('click', function() {
                        mobileMenu.hide();
                    });
                });
            }

            const sectionLinks = document.querySelectorAll('.js-section-link');
            const mobileSectionLinks = document.querySelectorAll('.js-mobile-section-link');
            const sections = document.querySelectorAll('section[id]');

            function setActiveLink(id) {
                sectionLinks.forEach(link => {
                    const isActive = link.dataset.target === id;
                    link.classList.toggle('active', isActive);
                    if (isActive) {
                        link.setAttribute('aria-current', 'page');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });

                mobileSectionLinks.forEach(link => {
                    const isActive = link.dataset.target === id;
                    link.classList.toggle('active', isActive);
                });
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setActiveLink(entry.target.id);
                    }
                });
            }, {
                rootMargin: '-35% 0px -45% 0px',
                threshold: 0.1
            });

            sections.forEach(section => observer.observe(section));

            if (window.location.hash) {
                const currentId = window.location.hash.replace('#', '');
                setActiveLink(currentId);
            } else {
                setActiveLink('tentang');
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const topThreshold = 140;
            let isNavigating = false;

            function handleScroll() {
                const scrollTop = window.scrollY || window.pageYOffset;
                const currentUrl = new URL(window.location.href);
                const targetUrl = `${currentUrl.origin}${currentUrl.pathname}#menu`;

                if (
                    scrollTop <= topThreshold &&
                    currentUrl.pathname === '/about' &&
                    currentUrl.hash !== '#menu' &&
                    !isNavigating
                ) {
                    isNavigating = true;
                    window.open(targetUrl, '_self');
                    return;
                }

                if (scrollTop > topThreshold) {
                    isNavigating = false;
                }
            }

            window.addEventListener('scroll', handleScroll, {
                passive: true
            });
            handleScroll();
        });
    </script>
</body>

</html>
