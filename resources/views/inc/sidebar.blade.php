@php
    $sidebarContext = app(\App\Services\Auth\CurrentUserContext::class);
    $sidebarActor = auth()->check() ? $sidebarContext->activePosition(request()) : null;

    $sidebarRoleId = (int) ($sidebarActor?->jabatan_id ?? 0);
    $sidebarWorkspaceConfig = config('sidebar_workspaces', []);

    $sidebarRouteMeta = $sidebarWorkspaceConfig['route_meta'] ?? [];
    $sidebarGroupMeta = $sidebarWorkspaceConfig['group_meta'] ?? [];
    $sidebarRoleMeta = $sidebarWorkspaceConfig['roles'][$sidebarRoleId] ?? [
        'label' => $sidebarActor?->jabatan?->nama,
        'workspace_routes' => [],
        'notes' => null,
    ];

    $sidebarWorkspaceRoutes = collect($sidebarRoleMeta['workspace_routes'] ?? []);

    if (in_array($sidebarRoleId, [1, 2, 3], true) && \Illuminate\Support\Facades\Route::has('users.index')) {
        $sidebarWorkspaceRoutes->prepend('users.index');
    }

    $sidebarWorkspaceRoutes = $sidebarWorkspaceRoutes
        ->filter(fn($routeName) => isset($sidebarRouteMeta[$routeName]) && \Illuminate\Support\Facades\Route::has($routeName))
        ->unique()
        ->values();

    $sidebarPagesRoutes = $sidebarWorkspaceRoutes
        ->filter(fn($routeName) => ($sidebarRouteMeta[$routeName]['group'] ?? null) === 'pages')
        ->values();

    $sidebarWorkspaceGroups = [];

    foreach ($sidebarGroupMeta as $groupKey => $groupMeta) {
        if ($groupKey === 'pages') {
            continue;
        }

        $groupRoutes = $sidebarWorkspaceRoutes
            ->filter(fn($routeName) => ($sidebarRouteMeta[$routeName]['group'] ?? null) === $groupKey)
            ->values();

        if ($groupRoutes->isEmpty()) {
            continue;
        }

        $sidebarWorkspaceGroups[$groupKey] = [
            'meta' => $groupMeta,
            'routes' => $groupRoutes,
            'active' => $groupRoutes->contains(fn($routeName) => request()->routeIs($routeName)),
        ];
    }

    $sidebarGroupColors = [
        'bank' => 'text-secondary',
        'ls' => 'text-primary',
        'ls_gaji' => 'text-success',
        'gu_skpd' => 'text-info',
        'gu_uk' => 'text-warning',
        'tu' => 'text-warning',
        'kkpd' => 'text-purple',
        'up' => 'text-warning',
    ];

    $sidebarRouteIcons = [
        'dashboard.anggaran.index' => 'fa-solid fa-chart-line text-success',
        'users.index' => 'fa-solid fa-users-gear text-danger',
        'bank.sp2d.index' => 'fa-solid fa-file-invoice-dollar text-secondary',
        'kkpd.spp.index' => 'fa-solid fa-file-invoice-dollar text-secondary',
        'kkpd.lpj.index' => 'fa-solid fa-file-waveform text-secondary',
    ];

    $sidebarDefaultChildIcon = 'fa-solid fa-file-contract text-secondary';
@endphp

<style>
    .sidenav .sidebar-brand {
        display: flex;
        align-items: center;
        gap: .75rem;
        min-height: 4.875rem;
        padding: 1rem 1.4rem;
    }

    .navbar-vertical .sidebar-brand > .sidebar-brand__logo {
        flex: 0 0 auto;
        width: 2.8rem;
        height: 2.8rem;
        max-width: none;
        max-height: 2.8rem;
        object-fit: contain;
    }

    .sidebar-brand__text {
        display: block;
        width: min(8.75rem, 100%);
        max-height: 1.95rem;
        object-fit: contain;
        object-position: left center;
    }

    @media (max-width: 1199.98px) {
        .sidenav .sidebar-brand {
            padding-right: 3.5rem;
        }
    }
</style>

<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4 "
    id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
            aria-hidden="true" id="iconSidenav" style="padding-top: 2rem;padding-right: 2rem;"></i>
        <a class="navbar-brand sidebar-brand m-0" href="{{ route('dashboard') }}" aria-label="Dashboard SITANGKAS">
            <img src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}" class="sidebar-brand__logo"
                alt="Logo Kota Malang">
            <span class="font-weight-bold d-flex align-items-center">
                <img src="{{ asset('assets/img/sitangkas_text.png') }}" class="sidebar-brand__text" alt="SITANGKAS">
            </span>
        </a>
    </div>

    <div class="collapse navbar-collapse  w-auto h-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">

            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <div class="icon icon-shape icon-sm text-center d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-gauge text-primary text-sm"></i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>

            @if (\Illuminate\Support\Facades\Route::has('profile.security'))
                <li class="nav-item">
                    <a href="{{ route('profile.security') }}"
                        class="nav-link {{ request()->routeIs('profile.security*') ? 'active' : '' }}">
                        <div class="icon icon-shape icon-sm text-center d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-shield-halved text-success text-sm"></i>
                        </div>
                        <span class="nav-link-text ms-1">Keamanan Akun</span>
                    </a>
                </li>
            @endif

                            <li class="nav-item mt-3">
                    <hr class="horizontal dark mt-0 mb-2">
                    <h6 class="ps-3 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">Pages</h6>
                </li>

            @if ($sidebarPagesRoutes->isNotEmpty())


                @foreach ($sidebarPagesRoutes as $routeName)
                    @php
                        $routeMeta = $sidebarRouteMeta[$routeName];
                        $routeIcon = $sidebarRouteIcons[$routeName] ?? $sidebarDefaultChildIcon;
                    @endphp
                    <li class="nav-item">
                        <a href="{{ route($routeName) }}"
                            class="nav-link {{ request()->routeIs($routeName) ? 'active' : '' }}"
                            role="button" aria-expanded="false">
                            <div class="icon icon-shape icon-sm text-center d-flex align-items-center justify-content-center">
                                <i class="{{ $routeIcon }} text-sm"></i>
                            </div>
                            <span class="nav-link-text ms-1">{{ $routeMeta['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            @endif

            @foreach ($sidebarWorkspaceGroups as $groupKey => $group)
                @php
                    $groupMeta = $group['meta'];
                    $groupRoutes = $group['routes'];
                    $groupIsActive = $group['active'];
                    $groupCollapseId = 'sidebar-' . \Illuminate\Support\Str::slug($groupKey);
                    $groupColor = $sidebarGroupColors[$groupKey] ?? 'text-primary';
                @endphp
                <li class="nav-item">
                    <a data-bs-toggle="collapse" href="#{{ $groupCollapseId }}"
                        class="nav-link {{ $groupIsActive ? '' : 'collapsed' }}"
                        aria-controls="{{ $groupCollapseId }}"
                        role="button" aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}">
                        <div class="icon icon-shape icon-sm text-center d-flex align-items-center justify-content-center">
                            <i class="{{ $groupMeta['icon'] ?? 'fa-solid fa-folder-open' }} {{ $groupColor }} text-sm"></i>
                        </div>
                        <span class="nav-link-text ms-1">{{ $groupMeta['label'] }}</span>
                    </a>
                    <div class="collapse {{ $groupIsActive ? 'show' : '' }}" id="{{ $groupCollapseId }}">
                        <ul class="nav ms-4">
                            @foreach ($groupRoutes as $routeName)
                                @php
                                    $routeMeta = $sidebarRouteMeta[$routeName];
                                    $routeIcon = $sidebarRouteIcons[$routeName] ?? $sidebarDefaultChildIcon;
                                @endphp
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs($routeName) ? 'active' : '' }}"
                                        href="{{ route($routeName) }}">
                                        <span class="sidenav-mini-icon d-none d-lg-block">
                                            {{ $routeMeta['code'] ?? 'DOC' }}
                                        </span>
                                        <span class="sidenav-normal">
                                            <i class="{{ $routeIcon }}"></i>
                                            {{ $routeMeta['label'] }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endforeach

            <li class="nav-item mt-3">
                <hr class="horizontal dark mt-0 mb-2" />
                <h6 class="ps-3 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">System</h6>
            </li>
            @if ($sidebarContext->isRealActivePositionAdminSuper(request()) && \Illuminate\Support\Facades\Route::has('admin.realtime.online-users.index'))
                <li class="nav-item">
                    <a href="{{ route('admin.realtime.online-users.index') }}"
                        class="nav-link {{ request()->routeIs('admin.realtime.online-users*') ? 'active' : '' }}">
                        <div class="icon icon-shape icon-sm text-center d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-users-viewfinder text-info text-sm"></i>
                        </div>
                        <span class="nav-link-text ms-1">Online Monitoring</span>
                    </a>
                </li>
            @endif
            <li class="nav-item">
                <a href="#" class="nav-link p-2" role="button" aria-expanded="false">
                    <div
                        class="icon icon-shape icon-sm text-center d-flex flex-column align-items-center justify-content-center border-1 p-4">
                        <i id="ux-icon" class="fa-solid fa-signal text-success text-sm pb-2"></i>
                        <small id="ux-latency" class="text-success lh-1">- ms</small>
                    </div>
                    <span class="nav-link-text ms-2 border-bottom" id="latency-section">
                        Status : <span id="ux-status">-</span>
                    </span>
                </a>

                <a href="#" class="nav-link p-2" role="button" aria-expanded="false">
                    <div
                        class="icon icon-shape icon-sm text-center d-flex flex-column align-items-center justify-content-center border-1 p-4">
                        <i id="infra-icon" class="fa-solid fa-network-wired text-success text-sm pb-2"></i>
                        <small id="infra-latency" class="text-success lh-1">- ms</small>
                    </div>
                    <span class="nav-link-text ms-2 border-bottom" id="latency-section-infrastruction">
                        Status : <span id="infra-status">-</span>
                    </span>
                </a>
            </li>

            <li class="nav-item">
                <hr class="horizontal dark" />
                <h6 class="ps-3 ms-2 text-uppercase text-xs font-weight-bolder opacity-6">DOCS</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="https://wa.me/6282131701177" target="_blank">
                    <div class="ms-2 me-2 d-flex align-items-center justify-content-center">
                        <i class="fab fa-whatsapp-square text-info fa-lg"></i>
                    </div>
                    <span class="nav-link-text ms-1">WhatApps Admin</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
