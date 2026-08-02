@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $userName = $user?->nama ?? 'User';
    $userInitials = collect(preg_split('/\s+/', trim($userName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $namePart): string => mb_substr($namePart, 0, 1))
        ->implode('');
    $userInitials = mb_strtoupper($userInitials ?: 'U');
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pilih Konteks Kerja - SITANGKAS</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <link href="/assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="/assets/css/nucleo-svg.css" rel="stylesheet">
    <link id="pagestyle" href="/assets/css/argon-dashboard.css?v=2.0.7" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <style>
        :root {
            --context-primary: #1b4f8c;
            --context-accent: #22b573;
            --context-ink: #0f172a;
            --context-muted: #64748b;
            --context-border: rgba(100, 116, 139, .16);
        }

        body {
            min-height: 100vh;
            background: linear-gradient(180deg, #f7fbff 0%, #eef5ff 52%, #f8fafc 100%);
            color: var(--context-ink);
            font-family: "Open Sans", sans-serif;
        }

        .context-shell {
            min-height: 100vh;
            padding: 1rem;
        }

        .context-frame {
            min-height: calc(100vh - 2rem);
            border: 1px solid rgba(255, 255, 255, .7);
            border-radius: 24px;
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 18px 48px rgba(15, 47, 87, .1);
            overflow: hidden;
        }

        .context-topbar {
            min-height: 76px;
            border-bottom: 1px solid var(--context-border);
            background: rgba(255, 255, 255, .84);
            backdrop-filter: blur(12px);
        }

        .context-brand,
        .context-user {
            display: flex;
            align-items: center;
            gap: .8rem;
            min-width: 0;
        }

        .context-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .brand-title {
            margin-bottom: 0;
            color: var(--context-primary);
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-subtitle {
            margin-bottom: 0;
            color: var(--context-muted);
            font-size: .78rem;
        }

        .context-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--context-primary), #2f8df3);
            color: #fff;
            font-size: .82rem;
            font-weight: 800;
        }

        .context-user-name,
        .context-user-meta {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .context-user-name {
            margin-bottom: .12rem;
            color: var(--context-ink);
            font-size: .88rem;
            font-weight: 800;
        }

        .context-user-meta {
            color: var(--context-muted);
            font-size: .74rem;
        }

        .context-main {
            padding: 1.25rem;
        }

        .context-hero {
            padding: 1.25rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #123d71 0%, #1b4f8c 52%, #2f8df3 100%);
            color: #fff;
            box-shadow: 0 18px 36px rgba(27, 79, 140, .18);
        }

        .context-eyebrow {
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

        .context-title {
            margin: .8rem 0 .45rem;
            color: #fff;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .context-copy {
            max-width: 780px;
            margin-bottom: 0;
            color: rgba(255, 255, 255, .84);
            font-size: .9rem;
            line-height: 1.6;
        }

        .context-alert {
            border-radius: 8px;
            border: 1px solid var(--context-border);
        }

        .position-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .position-card {
            position: relative;
            height: 100%;
        }

        .position-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .position-label {
            display: flex;
            flex-direction: column;
            gap: .9rem;
            height: 100%;
            min-height: 218px;
            padding: 1rem;
            border: 1px solid var(--context-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 47, 87, .05);
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .position-label:hover {
            border-color: rgba(27, 79, 140, .32);
            box-shadow: 0 14px 28px rgba(15, 47, 87, .08);
            transform: translateY(-1px);
        }

        .position-radio:checked + .position-label {
            border-color: rgba(27, 79, 140, .7);
            box-shadow: 0 0 0 .2rem rgba(27, 79, 140, .1), 0 14px 28px rgba(15, 47, 87, .08);
        }

        .position-title {
            color: var(--context-ink);
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .position-meta-list {
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .position-meta {
            display: flex;
            gap: .55rem;
            min-width: 0;
            color: var(--context-muted);
            font-size: .82rem;
            line-height: 1.45;
        }

        .position-meta i {
            width: 1rem;
            padding-top: .14rem;
            color: var(--context-primary);
            flex-shrink: 0;
        }

        .position-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            margin-top: auto;
        }

        .position-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .34rem .62rem;
            border-radius: 999px;
            background: rgba(27, 79, 140, .08);
            color: var(--context-primary);
            font-size: .72rem;
            font-weight: 800;
        }

        .position-badge.is-active {
            background: rgba(34, 181, 115, .12);
            color: #168052;
        }

        .context-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid var(--context-border);
            border-radius: 8px;
            background: #fff;
        }

        .action-copy {
            color: var(--context-muted);
            font-size: .84rem;
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
        }

        .action-button {
            min-height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
        }

        .empty-panel {
            margin-top: 1rem;
            padding: 1.25rem;
            border: 1px solid var(--context-border);
            border-radius: 8px;
            background: #fff;
            text-align: center;
            box-shadow: 0 10px 24px rgba(15, 47, 87, .05);
        }

        .empty-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .8rem;
            background: rgba(245, 54, 92, .1);
            color: #f5365c;
            font-size: 1.3rem;
        }

        @media (max-width: 991.98px) {
            .position-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .context-shell {
                padding: .5rem;
            }

            .context-frame {
                min-height: calc(100vh - 1rem);
                border-radius: 18px;
            }

            .context-main {
                padding: 1rem;
            }

            .context-title {
                font-size: 1.24rem;
            }

            .action-buttons,
            .action-button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="context-shell">
        <div class="context-frame">
            <header class="context-topbar d-flex align-items-center justify-content-between flex-wrap px-3 px-lg-4">
                <div class="context-brand">
                    <img src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}" alt="Logo Pemerintah Kota Malang">
                    <div>
                        <p class="brand-title">SITANGKAS</p>
                        <p class="brand-subtitle">Pemilihan Konteks Kerja</p>
                    </div>
                </div>

                <div class="context-user">
                    <span class="context-avatar">{{ $userInitials }}</span>
                    <div class="d-none d-sm-block">
                        <div class="context-user-name">{{ $userName }}</div>
                        <div class="context-user-meta">Tahun aktif {{ $activeYear ?? now()->year }}</div>
                    </div>
                </div>
            </header>

            <main class="context-main">
                <section class="context-hero">
                    <span class="context-eyebrow">
                        <i class="fa-solid fa-id-card"></i>
                        Context switch
                    </span>
                    <h1 class="context-title">Pilih posisi kerja untuk sesi saat ini</h1>
                    <p class="context-copy">
                        Dashboard dan modul internal akan memakai jabatan, instansi, unit kerja, dan tahun aktif dari
                        posisi yang dipilih di halaman ini.
                    </p>
                </section>

                @if (session('status'))
                    <div class="alert alert-info context-alert mt-3 mb-0">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger context-alert mt-3 mb-0">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if ($userPositions->isEmpty())
                    <section class="empty-panel">
                        <span class="empty-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </span>
                        <h2 class="h5 fw-bold mb-2">Belum ada posisi aktif</h2>
                        <p class="text-muted mb-3">
                            Akun ini belum memiliki posisi kerja aktif yang dapat dipakai untuk membuka dashboard.
                        </p>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-danger action-button">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                Logout
                            </button>
                        </form>
                    </section>
                @else
                    <form action="{{ route('login.context.store') }}" method="POST">
                        @csrf

                        <section class="position-grid">
                            @foreach ($userPositions as $userPosition)
                                @php
                                    $context = $userPosition->toAuthenticationContext();
                                    $isCurrent = (string) $activeUserPositionId === (string) $userPosition->getKey();
                                    $isChecked = (string) old('user_position_id', $activeUserPositionId) === (string) $userPosition->getKey();
                                @endphp

                                <div class="position-card">
                                    <input
                                        type="radio"
                                        name="user_position_id"
                                        id="user_position_{{ $userPosition->getKey() }}"
                                        value="{{ $userPosition->getKey() }}"
                                        class="position-radio"
                                        @checked($isChecked)
                                        required
                                    >
                                    <label for="user_position_{{ $userPosition->getKey() }}" class="position-label">
                                        <div>
                                            <div class="position-title">{{ $context['nama_jabatan'] ?? 'Jabatan' }}</div>
                                        </div>

                                        <div class="position-meta-list">
                                            <div class="position-meta">
                                                <i class="fa-solid fa-building-columns"></i>
                                                <span>{{ $context['nama_instansi'] ?? 'Instansi belum tersedia' }}</span>
                                            </div>
                                            <div class="position-meta">
                                                <i class="fa-solid fa-sitemap"></i>
                                                <span>{{ $context['nama_unit_kerja'] ?? 'Unit kerja belum tersedia' }}</span>
                                            </div>
                                            <div class="position-meta">
                                                <i class="fa-solid fa-clock"></i>
                                                <span>
                                                    Terakhir dipakai:
                                                    {{ $userPosition->last_used_at?->diffForHumans() ?? 'belum pernah' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="position-badges">
                                            @if ($isCurrent)
                                                <span class="position-badge is-active">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    Sedang aktif
                                                </span>
                                            @endif
                                            <span class="position-badge">
                                                ID {{ $userPosition->getKey() }}
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </section>

                        <section class="context-actions">
                            <div class="action-copy">
                                Setelah disimpan, dashboard akan memakai konteks kerja yang dipilih.
                            </div>
                            <div class="action-buttons">
                                @if ($activeUserPositionId)
                                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary action-button">
                                        <i class="fa-solid fa-arrow-left"></i>
                                        Kembali
                                    </a>
                                @endif
                                <button type="submit" class="btn btn-primary action-button">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Simpan Konteks
                                </button>
                            </div>
                        </section>
                    </form>
                @endif
            </main>
        </div>
    </div>
</body>

</html>
