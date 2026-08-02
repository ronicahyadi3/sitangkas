@auth
    @php
        $navbarContext = app(\App\Services\Auth\CurrentUserContext::class);
        $navbarRequest = request();
        $navbarActor = $navbarContext->activePosition($navbarRequest);
        $navbarRealActor = $navbarContext->realActivePosition($navbarRequest);
        $navbarIsRealAdminSuper = $navbarRealActor
            ? $navbarContext->isAdminSuperPosition($navbarRealActor)
            : false;
        $navbarIsActingContext = $navbarContext->effectiveContextIsActing($navbarRequest);

        $navbarRoleId = (int) ($navbarActor?->jabatan_id ?? 0);

        $navbarContextRouteName = $navbarIsRealAdminSuper && \Illuminate\Support\Facades\Route::has('login.post')
            ? 'login.post'
            : (\Illuminate\Support\Facades\Route::has('login.context') ? 'login.context' : null);
        $navbarContextUrl = $navbarContextRouteName ? route($navbarContextRouteName) : '#';
        $navbarProfileUrl = \Illuminate\Support\Facades\Route::has('users.index')
            ? route('users.index')
            : $navbarContextUrl;
        $navbarPasswordUrl = \Illuminate\Support\Facades\Route::has('password.change')
            ? route('password.change')
            : $navbarContextUrl;

        $navbarProfileLabel = in_array($navbarRoleId, [12, 13], true) ? 'Konteks Akun' : 'Profile';

        $navbarActiveYear = $navbarContext->activeYear($navbarRequest);
        $navbarYearContext = $navbarActiveYear
            ? [
                'tahun_aktif' => $navbarActiveYear,
                'can_write' => true,
            ]
            : [];

        $navbarRoleLabel = $navbarActor?->jabatan?->nama ?? session('active_role') ?? 'Konteks Aktif';
        $navbarContextActionLabel = $navbarIsRealAdminSuper
            ? ($navbarIsActingContext ? 'Ganti Acting Context' : 'Pilih Acting Context')
            : 'Ganti Jabatan';

        $navbarUserName = auth()->user()?->nama ?? 'User';
        $navbarNameParts = preg_split('/\s+/', trim($navbarUserName)) ?: [];
        $navbarInitials = collect($navbarNameParts)
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->implode('');
        $navbarInitials = mb_strtoupper($navbarInitials ?: 'U');

        $navbarCanWrite = (bool) ($navbarYearContext['can_write'] ?? false);
        $navbarAccessLabel = $navbarCanWrite ? 'Tulis' : 'Lihat';
        $navbarAccessClass = $navbarCanWrite ? 'is-write' : 'is-read';

        $navbarRouteName = $navbarRequest->route()?->getName() ?? '';
        $navbarBreadcrumbMap = [
            'dashboard' => ['Dashboard'],
            'dashboard.anggaran.index' => ['Dashboard', 'Anggaran'],
            'users.index' => ['Users'],
            'positions.page' => ['Akun', 'Ganti Jabatan'],
            'password.change' => ['Akun', 'Ganti Password'],
            'bank.sp2d.index' => ['Bank', 'SP2D'],
        ];

        $navbarBreadcrumbLabels = $navbarBreadcrumbMap[$navbarRouteName] ?? null;

        if (!$navbarBreadcrumbLabels) {
            foreach ([
                'dashboard.anggaran.' => ['Dashboard', 'Anggaran'],
                'users.positions.' => ['Users', 'Jabatan'],
                'users.' => ['Users'],
                'bank.sp2d.' => ['Bank', 'SP2D'],
                'ls-gaji.' => ['Pembayaran', 'LS Gaji'],
                'gu-skpd.' => ['Pembayaran', 'GU SKPD'],
                'gu-uk.' => ['Pembayaran', 'GU UK'],
                'kkpd.' => ['Pembayaran', 'KKPD'],
                'ls.' => ['Pembayaran', 'LS'],
                'tu.' => ['Pembayaran', 'TU'],
                'up.' => ['Pembayaran', 'UP'],
            ] as $prefix => $labels) {
                if (str_starts_with($navbarRouteName, $prefix)) {
                    $navbarBreadcrumbLabels = $labels;
                    break;
                }
            }
        }

        $navbarBreadcrumbLabels ??= collect($navbarRequest->segments())
            ->map(fn ($segment) => str($segment)->replace(['-', '_'], ' ')->title()->toString())
            ->values()
            ->all();

        if (empty($navbarBreadcrumbLabels)) {
            $navbarBreadcrumbLabels = ['Dashboard'];
        }
    @endphp

    <style>
        .navbar-context-card {
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: .35rem .78rem;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            transition: all .2s ease;
        }

        .navbar-context-card:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-1px);
        }

        .context-chip {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .context-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .bg-info-soft {
            background: rgba(13, 202, 240, 0.16);
        }

        .bg-success-soft {
            background: rgba(25, 135, 84, 0.16);
        }

        .bg-warning-soft {
            background: rgba(255, 193, 7, 0.18);
        }

        .context-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .context-text small {
            font-size: .64rem;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: .18rem;
        }

        .context-text span {
            font-size: .78rem;
            font-weight: 700;
            color: #fff;
        }

        .context-divider {
            width: 1px;
            height: 28px;
            background: rgba(255, 255, 255, 0.14);
        }

        .navbar-breadcrumb {
            min-width: 0;
        }

        .navbar-breadcrumb .breadcrumb {
            align-items: center;
            gap: .35rem;
        }

        .navbar-breadcrumb .breadcrumb-item {
            display: inline-flex;
            align-items: center;
            max-width: 180px;
            color: rgba(255, 255, 255, 0.72);
        }

        .navbar-breadcrumb .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.45);
        }

        .navbar-breadcrumb-home {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.12);
        }

        .navbar-breadcrumb-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .navbar-breadcrumb-current {
            font-weight: 700;
            color: #fff;
        }

        .navbar-icon-btn {
            background: transparent;
            border: 0;
            padding: 0;
            color: #fff;
        }

        .navbar-icon-btn:focus {
            outline: none;
            box-shadow: none;
        }

        body.app-layout-booting .navbar-main .nav-item.dropdown>.dropdown-menu {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            animation: none !important;
        }

        .navbar-main .nav-item.dropdown>.dropdown-menu:not(.show) {
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            animation: none !important;
            transform: translate3d(0, 8px, 0) !important;
        }

        .navbar-account-toggle {
            display: inline-flex !important;
            align-items: center;
            gap: .55rem;
            min-height: 38px;
            padding: .25rem .55rem .25rem .3rem !important;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            transition: background .2s ease, border-color .2s ease, transform .2s ease;
        }

        .navbar-account-toggle:hover,
        .navbar-account-toggle.show {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.22);
            color: #fff;
            transform: translateY(-1px);
        }

        .navbar-account-toggle:focus {
            box-shadow: 0 0 0 .2rem rgba(255, 255, 255, 0.16);
            outline: none;
        }

        .navbar-account-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, rgba(94, 114, 228, 0.95), rgba(17, 205, 239, 0.95));
            color: #fff;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
        }

        .navbar-account-copy {
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.05;
            min-width: 0;
        }

        .navbar-account-name,
        .navbar-account-role {
            display: block;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .navbar-account-name {
            font-size: .78rem;
            font-weight: 700;
            color: #fff;
        }

        .navbar-account-role {
            margin-top: .16rem;
            font-size: .64rem;
            color: rgba(255, 255, 255, 0.72);
        }

        .navbar-account-caret {
            font-size: .65rem;
            opacity: .72;
            transition: transform .2s ease;
        }

        .navbar-account-toggle.show .navbar-account-caret {
            transform: rotate(180deg);
        }

        .navbar-account-menu {
            min-width: 18rem;
            padding: .55rem;
            border: 1px solid rgba(131, 146, 171, 0.16);
            box-shadow: 0 16px 44px rgba(20, 20, 20, 0.14), 0 4px 12px rgba(20, 20, 20, 0.08);
            cursor: default;
        }

        .navbar-main .dropdown:not(.dropdown-hover) .navbar-account-menu {
            margin-top: .75rem !important;
        }

        .navbar-main .dropdown .navbar-account-menu {
            top: 100% !important;
        }

        .navbar-main.bg-white .navbar-account-toggle {
            background: rgba(52, 71, 103, 0.08);
            border-color: rgba(52, 71, 103, 0.12);
            color: #344767;
        }

        .navbar-main.bg-white .navbar-account-toggle:hover,
        .navbar-main.bg-white .navbar-account-toggle.show {
            background: rgba(52, 71, 103, 0.12);
            border-color: rgba(52, 71, 103, 0.18);
            color: #344767;
        }

        .navbar-main.bg-white .navbar-account-avatar {
            color: #fff;
        }

        .navbar-main.bg-white .navbar-account-name {
            color: #344767;
        }

        .navbar-main.bg-white .navbar-account-role,
        .navbar-main.bg-white .navbar-account-caret {
            color: #67748e;
        }

        .navbar-main.bg-white .navbar-context-card {
            background: rgba(52, 71, 103, 0.08);
            border-color: rgba(52, 71, 103, 0.12);
        }

        .navbar-main.bg-white .navbar-context-card:hover {
            background: rgba(52, 71, 103, 0.12);
        }

        .navbar-main.bg-white .context-text small,
        .navbar-main.bg-white .context-text span {
            color: #344767;
        }

        .navbar-main.bg-white .context-divider {
            background: rgba(52, 71, 103, 0.12);
        }

        .navbar-main.bg-white .navbar-breadcrumb .breadcrumb-item,
        .navbar-main.bg-white .navbar-breadcrumb-current {
            color: #344767;
        }

        .navbar-main.bg-white .navbar-breadcrumb .breadcrumb-item::before {
            color: rgba(52, 71, 103, 0.38);
        }

        .navbar-main.bg-white .navbar-breadcrumb-home {
            background: rgba(52, 71, 103, 0.08);
            color: #344767 !important;
        }

        .navbar-account-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .7rem .65rem .75rem;
            margin-bottom: .4rem;
            border-radius: .65rem;
            background: linear-gradient(135deg, rgba(94, 114, 228, 0.1), rgba(17, 205, 239, 0.08));
        }

        .navbar-account-avatar--lg {
            width: 42px;
            height: 42px;
            font-size: .9rem;
        }

        .navbar-account-header-copy {
            min-width: 0;
            flex: 1;
        }

        .navbar-account-header-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: .88rem;
            font-weight: 700;
            color: #344767;
            line-height: 1.2;
        }

        .navbar-account-header-role {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-top: .15rem;
            font-size: .72rem;
            color: #8392ab;
            line-height: 1.2;
        }

        .navbar-account-status-row {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .45rem;
        }

        .navbar-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 24px;
            padding: .2rem .5rem;
            border-radius: 999px;
            background: #fff;
            color: #67748e;
            font-size: .68rem;
            font-weight: 700;
            border: 1px solid rgba(131, 146, 171, 0.16);
        }

        .navbar-status-dot {
            width: .45rem;
            height: .45rem;
            border-radius: 50%;
            background: #8392ab;
            flex-shrink: 0;
        }

        .navbar-status-dot.is-write,
        .navbar-status-pill.is-write .navbar-status-dot {
            background: #2dce89;
        }

        .navbar-status-dot.is-read,
        .navbar-status-pill.is-read .navbar-status-dot {
            background: #fb6340;
        }

        .navbar-menu-group-label {
            padding: .55rem .55rem .25rem;
            color: #8392ab;
            font-size: .66rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .navbar-dropdown-item {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-height: 42px;
            padding: .45rem .55rem;
            border-radius: .5rem;
            color: #344767;
            font-weight: 600;
        }

        .navbar-dropdown-item:hover,
        .navbar-dropdown-item:focus {
            background: #f5f7fb;
            color: #344767;
        }

        .navbar-menu-icon {
            width: 1.8rem;
            height: 1.8rem;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: rgba(94, 114, 228, 0.12);
            color: #5e72e4;
        }

        .navbar-theme-toggle {
            cursor: pointer;
            user-select: none;
        }

        .navbar-theme-toggle .navbar-theme-switch {
            width: 42px;
            min-width: 42px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding-left: 0;
        }

        .navbar-theme-toggle #darkModeSwitch {
            width: 42px;
            height: 22px;
            margin: 0;
            float: none;
            position: relative;
            border: 0;
            border-radius: 999px;
            cursor: pointer;
            background-color: rgba(52, 71, 103, 0.18);
            box-shadow: inset 0 0 0 1px rgba(52, 71, 103, 0.08);
        }

        .navbar-theme-toggle #darkModeSwitch:focus {
            box-shadow: inset 0 0 0 1px rgba(94, 114, 228, 0.18), 0 0 0 .16rem rgba(94, 114, 228, 0.14);
        }

        .navbar-theme-toggle #darkModeSwitch:after {
            width: 18px;
            height: 18px;
            top: 50%;
            left: 2px;
            transform: translate3d(0, -50%, 0);
            box-shadow: 0 3px 8px rgba(20, 20, 20, 0.16);
        }

        .navbar-theme-toggle #darkModeSwitch:checked {
            background-color: rgba(94, 114, 228, 0.95);
        }

        .navbar-theme-toggle #darkModeSwitch:checked:after {
            transform: translate3d(20px, -50%, 0);
        }

        .dark-version .navbar-theme-toggle #darkModeSwitch {
            background-color: rgba(94, 114, 228, 0.95);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .theme-icon-sun,
        .theme-label-light {
            display: none;
        }

        .dark-version .theme-icon-moon,
        .dark-version .theme-label-dark {
            display: none;
        }

        .dark-version .theme-icon-sun,
        .dark-version .theme-label-light {
            display: inline;
        }

        .navbar-logout-item {
            color: #f5365c !important;
        }

        .navbar-logout-item .navbar-menu-icon {
            background: rgba(245, 54, 92, 0.12);
            color: #f5365c;
        }

        .dark-version .navbar-account-header {
            background: rgba(255, 255, 255, 0.06);
        }

        .dark-version .navbar-account-header-name,
        .dark-version .navbar-dropdown-item {
            color: #fff;
        }

        .dark-version .navbar-account-header-role,
        .dark-version .navbar-menu-group-label {
            color: rgba(255, 255, 255, 0.62);
        }

        .dark-version .navbar-status-pill {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.78);
        }

        .dark-version .navbar-dropdown-item:hover,
        .dark-version .navbar-dropdown-item:focus {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .dark-version .navbar-menu-icon {
            background: rgba(94, 114, 228, 0.18);
        }

        .dark-version .navbar-logout-item .navbar-menu-icon {
            background: rgba(245, 54, 92, 0.18);
        }

        @media (max-width: 1199.98px) {
            .navbar-context-card {
                padding: .42rem .65rem;
                gap: .5rem;
            }

            .context-text span {
                font-size: .74rem;
            }

            .navbar-account-name,
            .navbar-account-role {
                max-width: 150px;
            }
        }

        @media (max-width: 575.98px) {
            .navbar-account-menu {
                min-width: 15rem;
            }

            .navbar-account-toggle {
                padding-right: .3rem !important;
            }
        }
    </style>

    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl z-index-sticky"
        id="navbarBlur" data-scroll="false">
        <div class="container-fluid py-1 px-3">

            <nav class="navbar-breadcrumb" aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-4">
                    <li class="breadcrumb-item text-sm">
                        <a href="{{ route('dashboard') }}" class="navbar-breadcrumb-home text-white"
                            aria-label="Dashboard">
                            <i class="ni ni-box-2"></i>
                        </a>
                    </li>
                    @foreach ($navbarBreadcrumbLabels as $navbarBreadcrumbLabel)
                        <li
                            class="breadcrumb-item text-sm {{ $loop->last ? 'navbar-breadcrumb-current' : '' }}">
                            <span class="navbar-breadcrumb-label">{{ $navbarBreadcrumbLabel }}</span>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="sidenav-toggler sidenav-toggler-inner d-xl-block d-none">
                <button type="button" class="nav-link p-0 navbar-icon-btn" id="desktopNavbarSidenav"
                    aria-label="Toggle sidenav">
                    <div class="sidenav-toggler-inner">
                        <i class="sidenav-toggler-line bg-white"></i>
                        <i class="sidenav-toggler-line bg-white"></i>
                        <i class="sidenav-toggler-line bg-white"></i>
                    </div>
                </button>
            </div>

            <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                <div class="ms-md-auto pe-md-3"></div>

                <ul class="navbar-nav justify-content-end align-items-center">

                    @if (!empty($navbarYearContext))
                        <li class="nav-item d-none d-lg-flex align-items-center ">
                            <a href="{{ $navbarContextUrl }}" class="navbar-context-card text-decoration-none"
                                aria-label="Tahun aktif {{ $navbarYearContext['tahun_aktif'] }}, mode {{ $navbarAccessLabel }}">
                                <div class="context-chip">
                                    <span class="navbar-status-dot {{ $navbarAccessClass }}"></span>
                                    <div class="context-text">
                                        <small>Tahun Aktif</small>
                                        <span>
                                            {{ $navbarYearContext['tahun_aktif'] }} &bull; {{ $navbarAccessLabel }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </li>
                    @endif

                    <li class="nav-item d-xl-none pe-2 d-flex align-items-center">
                        <button type="button" class="nav-link text-white p-0 navbar-icon-btn" id="iconNavbarSidenav"
                            aria-label="Toggle sidenav">
                            <div class="sidenav-toggler-inner">
                                <i class="sidenav-toggler-line bg-white"></i>
                                <i class="sidenav-toggler-line bg-white"></i>
                                <i class="sidenav-toggler-line bg-white"></i>
                            </div>
                        </button>
                    </li>

                    <li class="nav-item dropdown ps-1 d-flex align-items-center">
                        <button class="nav-link navbar-account-toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" aria-label="Menu akun">
                            <span class="navbar-account-avatar">
                                {{ $navbarInitials }}
                            </span>
                            <span class="navbar-account-copy d-none d-sm-flex">
                                <span class="navbar-account-name">{{ $navbarUserName }}</span>
                                <span class="navbar-account-role">{{ $navbarRoleLabel }}</span>
                            </span>
                            <i class="fa fa-chevron-down navbar-account-caret d-none d-sm-inline-flex text-white"></i>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end navbar-account-menu">
                            <li>
                                <div class="navbar-account-header">
                                    <span class="navbar-account-avatar navbar-account-avatar--lg">
                                        {{ $navbarInitials }}
                                    </span>
                                    <div class="navbar-account-header-copy">
                                        <div class="navbar-account-header-name">{{ $navbarUserName }}</div>
                                        <div class="navbar-account-header-role">{{ $navbarRoleLabel }}</div>

                                        @if (!empty($navbarYearContext))
                                            <div class="navbar-account-status-row">
                                                <span class="navbar-status-pill">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    {{ $navbarYearContext['tahun_aktif'] }}
                                                </span>
                                                <span class="navbar-status-pill {{ $navbarAccessClass }}">
                                                    <span class="navbar-status-dot"></span>
                                                    {{ $navbarAccessLabel }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </li>

                            <li>
                                <div class="navbar-menu-group-label">Akun</div>
                            </li>

                            <li>
                                <a class="dropdown-item navbar-dropdown-item" href="{{ $navbarProfileUrl }}">
                                    <span class="navbar-menu-icon">
                                        <i class="fa fa-user"></i>
                                    </span>
                                    <span>{{ $navbarProfileLabel }}</span>
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item navbar-dropdown-item" href="{{ $navbarPasswordUrl }}">
                                    <span class="navbar-menu-icon">
                                        <i class="fa fa-key"></i>
                                    </span>
                                    <span>Ganti Password</span>
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item navbar-dropdown-item" href="{{ $navbarContextUrl }}">
                                    <span class="navbar-menu-icon">
                                        <i class="fa fa-id-badge"></i>
                                    </span>
                                    <span>{{ $navbarContextActionLabel }}</span>
                                </a>
                            </li>

                            <li>
                                <div class="navbar-menu-group-label">Preferensi</div>
                            </li>

                            <li>
                                <label class="dropdown-item navbar-dropdown-item navbar-theme-toggle">
                                    <span class="navbar-menu-icon">
                                        <i class="fa fa-moon theme-icon-moon"></i>
                                        <i class="fa fa-sun theme-icon-sun"></i>
                                    </span>
                                    <span class="theme-label-dark">Mode Gelap</span>
                                    <span class="theme-label-light">Mode Terang</span>
                                    <span class="form-check form-switch navbar-theme-switch m-0 ms-auto">
                                        <input class="form-check-input" type="checkbox" id="darkModeSwitch"
                                            onclick="darkMode(this)">
                                    </span>
                                </label>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <div class="navbar-menu-group-label">Sesi</div>
                            </li>

                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item navbar-dropdown-item navbar-logout-item">
                                        <span class="navbar-menu-icon">
                                            <i class="fa fa-sign-out-alt"></i>
                                        </span>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
@endauth
