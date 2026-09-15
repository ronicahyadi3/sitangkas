@php
    $positionContext = $activeUserPosition?->toAuthenticationContext() ?? [];
    $userName = $user?->nama ?? 'User';
    $contextRouteName = ($isRealActiveUserPositionAdminSuper ?? false) && \Illuminate\Support\Facades\Route::has('login.post')
        ? 'login.post'
        : 'positions.index';
    $contextActionLabel = ($isRealActiveUserPositionAdminSuper ?? false)
        ? (($isActingContext ?? false) ? 'Ganti Acting Context' : 'Pilih Acting Context')
        : 'Ganti Posisi';
@endphp

@extends('layouts.app')

@section('title', 'Dashboard - SITANGKAS')

@section('styling')
    <style>
        :root {
            --dashboard-primary: #1b4f8c;
            --dashboard-accent: #22b573;
            --dashboard-ink: #0f172a;
            --dashboard-muted: #64748b;
            --dashboard-border: rgba(100, 116, 139, .16);
        }

        .dashboard-page {
            color: var(--dashboard-ink);
        }

        .dashboard-hero {
            padding: 1.25rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #123d71 0%, #1b4f8c 48%, #2f8df3 100%);
            color: #fff;
            box-shadow: 0 18px 36px rgba(27, 79, 140, .18);
        }

        .dashboard-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .34rem .72rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            color: rgba(255, 255, 255, .9);
            font-size: .74rem;
            font-weight: 800;
        }

        .dashboard-title {
            margin: .8rem 0 .45rem;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .dashboard-copy {
            max-width: 760px;
            margin-bottom: 0;
            color: rgba(255, 255, 255, .82);
            font-size: .9rem;
            line-height: 1.6;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .summary-item,
        .work-panel {
            border: 1px solid var(--dashboard-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 47, 87, .05);
        }

        .summary-item {
            padding: 1rem;
            min-height: 112px;
        }

        .summary-label {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .55rem;
            color: var(--dashboard-muted);
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .summary-value {
            color: var(--dashboard-ink);
            font-size: .96rem;
            font-weight: 800;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .work-panel {
            padding: 1.1rem;
            height: 100%;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .panel-title {
            margin-bottom: .15rem;
            color: var(--dashboard-ink);
            font-size: 1rem;
            font-weight: 800;
        }

        .panel-subtitle {
            margin-bottom: 0;
            color: var(--dashboard-muted);
            font-size: .8rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .65rem;
            border-radius: 999px;
            background: rgba(34, 181, 115, .1);
            color: #168052;
            font-size: .75rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .metric-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .metric-item {
            padding: .9rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid rgba(100, 116, 139, .12);
        }

        .metric-value {
            margin-bottom: .2rem;
            color: var(--dashboard-primary);
            font-size: 1.35rem;
            font-weight: 800;
        }

        .metric-label {
            margin-bottom: 0;
            color: var(--dashboard-muted);
            font-size: .76rem;
            font-weight: 700;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
        }

        .action-button {
            min-height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
        }

        body.dark-version .summary-item,
        body.dark-version .work-panel {
            background: var(--app-dark-surface) !important;
            border-color: var(--app-dark-border) !important;
        }

        body.dark-version .metric-item {
            background: var(--app-dark-surface-2) !important;
            border-color: var(--app-dark-border) !important;
        }

        body.dark-version .dashboard-page {
            color: var(--app-dark-text);
        }

        body.dark-version .summary-value,
        body.dark-version .panel-title {
            color: var(--app-dark-heading);
        }

        body.dark-version .summary-label,
        body.dark-version .panel-subtitle,
        body.dark-version .metric-label {
            color: var(--app-dark-muted);
        }

        @media (max-width: 1199.98px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .summary-grid,
            .metric-list {
                grid-template-columns: 1fr;
            }

            .dashboard-title {
                font-size: 1.25rem;
            }

            .action-button {
                width: 100%;
            }
        }
    </style>
@endsection

@section('content')
    <div class="dashboard-page">
        <section class="dashboard-hero">
            <span class="dashboard-eyebrow">
                <i class="fa-solid fa-shield-halved"></i>
                Konteks kerja aktif
            </span>
            <h1 class="dashboard-title">Selamat bekerja, {{ $userName }}</h1>
            <p class="dashboard-copy">
                Sesi ini berjalan menggunakan konteks jabatan, instansi, unit kerja, dan tahun aktif yang tersimpan saat login.
            </p>
        </section>

        <section class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">
                    <i class="fa-solid fa-calendar-days text-primary"></i>
                    Tahun Aktif
                </div>
                <div class="summary-value">{{ $activeYear ?? '-' }}</div>
            </div>

            <div class="summary-item">
                <div class="summary-label">
                    <i class="fa-solid fa-id-badge text-success"></i>
                    Jabatan
                </div>
                <div class="summary-value">{{ $positionContext['nama_jabatan'] ?? '-' }}</div>
            </div>

            <div class="summary-item">
                <div class="summary-label">
                    <i class="fa-solid fa-building-columns text-info"></i>
                    Instansi
                </div>
                <div class="summary-value">{{ $positionContext['nama_instansi'] ?? '-' }}</div>
            </div>

            <div class="summary-item">
                <div class="summary-label">
                    <i class="fa-solid fa-sitemap text-warning"></i>
                    Unit Kerja
                </div>
                <div class="summary-value">{{ $positionContext['nama_unit_kerja'] ?? '-' }}</div>
            </div>
        </section>

        <section class="row g-3 mt-1">
            <div class="col-lg-8">
                <div class="work-panel">
                    <div class="panel-heading">
                        <div>
                            <h2 class="panel-title">Ringkasan Pekerjaan</h2>
                            <p class="panel-subtitle">
                                Angka awal akan dihubungkan ke modul dokumen setelah domain operasional dipindahkan.
                            </p>
                        </div>
                        <span class="status-pill">
                            <i class="fa-solid fa-circle-check"></i>
                            Session valid
                        </span>
                    </div>

                    <div class="metric-list">
                        <div class="metric-item">
                            <div class="metric-value">0</div>
                            <p class="metric-label">Dokumen Masuk</p>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">0</div>
                            <p class="metric-label">Menunggu Validasi</p>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">0</div>
                            <p class="metric-label">Selesai Diproses</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="work-panel">
                    <div class="panel-heading">
                        <div>
                            <h2 class="panel-title">Aksi Sesi</h2>
                            <p class="panel-subtitle">Kelola konteks dan sesi aktif.</p>
                        </div>
                    </div>

                    <div class="action-row">
                        <a href="{{ route($contextRouteName) }}" class="btn btn-outline-primary action-button">
                            <i class="fa-solid fa-id-card"></i>
                            {{ $contextActionLabel }}
                        </a>

                        <form action="{{ route('logout') }}" method="POST" class="m-0 flex-grow-1">
                            @csrf
                            <button type="submit" class="btn btn-danger action-button w-100">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
