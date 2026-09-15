@php
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
    <title>Belum Memiliki Posisi Aktif - SITANGKAS</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <link href="/assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="/assets/css/nucleo-svg.css" rel="stylesheet">
    <link id="pagestyle" href="/assets/css/argon-dashboard.css?v=2.0.7" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <style>
        :root {
            --empty-primary: #1b4f8c;
            --empty-accent: #22b573;
            --empty-danger: #e11d48;
            --empty-ink: #0f172a;
            --empty-muted: #64748b;
            --empty-border: rgba(100, 116, 139, .18);
            --empty-surface: rgba(255, 255, 255, .94);
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        body {
            margin: 0;
            color: var(--empty-ink);
            font-family: "Open Sans", sans-serif;
            background:
                linear-gradient(135deg, rgba(27, 79, 140, .12) 0%, rgba(34, 181, 115, .08) 45%, rgba(248, 250, 252, 1) 100%),
                #f8fafc;
        }

        .empty-shell {
            width: 100%;
            height: 100dvh;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(.75rem, 2vw, 1.5rem);
        }

        .empty-panel {
            width: min(100%, 920px);
            max-height: calc(100dvh - clamp(1.5rem, 4vw, 3rem));
            display: grid;
            grid-template-columns: minmax(0, .92fr) minmax(280px, 1.08fr);
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 8px;
            background: var(--empty-surface);
            box-shadow: 0 24px 64px rgba(15, 47, 87, .14);
            overflow: hidden;
        }

        .empty-brand {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
            padding: clamp(1rem, 3vw, 2rem);
            color: #fff;
            background: linear-gradient(145deg, #0f2f57 0%, #1b4f8c 58%, #22b573 100%);
        }

        .empty-logo-row,
        .empty-user {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
        }

        .empty-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
            padding: .35rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, .94);
        }

        .empty-brand-title {
            margin: 0;
            color: #fff;
            font-size: .98rem;
            font-weight: 800;
        }

        .empty-brand-subtitle,
        .empty-user-meta {
            margin: 0;
            color: rgba(255, 255, 255, .74);
            font-size: .78rem;
        }

        .empty-user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .24);
            color: #fff;
            font-size: .82rem;
            font-weight: 800;
        }

        .empty-user-name {
            max-width: 240px;
            margin: 0 0 .12rem;
            color: #fff;
            font-size: .92rem;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty-content {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(.8rem, 2vh, 1.2rem);
            padding: clamp(1.1rem, 3vw, 2.25rem);
        }

        .empty-status {
            display: inline-flex;
            align-items: center;
            gap: .48rem;
            width: fit-content;
            padding: .4rem .72rem;
            border-radius: 999px;
            background: rgba(225, 29, 72, .09);
            color: var(--empty-danger);
            font-size: .74rem;
            font-weight: 800;
        }

        .empty-icon {
            width: clamp(54px, 8vw, 72px);
            height: clamp(54px, 8vw, 72px);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(225, 29, 72, .1);
            color: var(--empty-danger);
            font-size: clamp(1.4rem, 3vw, 1.9rem);
        }

        .empty-title {
            margin: 0;
            color: var(--empty-ink);
            font-size: clamp(1.35rem, 3.2vw, 2.15rem);
            font-weight: 800;
            line-height: 1.16;
        }

        .empty-copy {
            max-width: 560px;
            margin: 0;
            color: var(--empty-muted);
            font-size: clamp(.86rem, 1.5vw, .96rem);
            line-height: 1.58;
        }

        .empty-note {
            display: flex;
            gap: .65rem;
            padding: .85rem;
            border: 1px solid var(--empty-border);
            border-radius: 8px;
            background: #fff;
            color: var(--empty-muted);
            font-size: .84rem;
            line-height: 1.48;
        }

        .empty-note i {
            padding-top: .16rem;
            color: var(--empty-primary);
            flex-shrink: 0;
        }

        .empty-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
        }

        .empty-action {
            min-height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .empty-panel {
                grid-template-columns: 1fr;
            }

            .empty-brand {
                min-height: auto;
                flex-direction: row;
                align-items: center;
                padding: .9rem;
            }

            .empty-brand-copy {
                display: none;
            }

            .empty-user-name {
                max-width: 160px;
            }

            .empty-content {
                padding: 1rem;
            }
        }

        @media (max-width: 420px), (max-height: 560px) {
            .empty-shell {
                padding: .5rem;
            }

            .empty-panel {
                max-height: calc(100dvh - 1rem);
            }

            .empty-brand {
                padding: .75rem;
            }

            .empty-logo {
                width: 38px;
                height: 38px;
            }

            .empty-user-avatar {
                width: 38px;
                height: 38px;
            }

            .empty-content {
                gap: .65rem;
                padding: .85rem;
            }

            .empty-note {
                padding: .7rem;
            }

            .empty-actions,
            .empty-action {
                width: 100%;
            }
        }

        @media (max-height: 500px) {
            .empty-icon,
            .empty-note,
            .empty-brand-copy {
                display: none;
            }

            .empty-copy {
                line-height: 1.42;
            }
        }
    </style>
</head>

<body>
    <main class="empty-shell">
        <section class="empty-panel" aria-labelledby="missing-position-title">
            <aside class="empty-brand">
                <div class="empty-logo-row">
                    <img class="empty-logo" src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}"
                        alt="Logo Pemerintah Kota Malang">
                    <div>
                        <p class="empty-brand-title">SITANGKAS</p>
                        <p class="empty-brand-subtitle">Akses akun</p>
                    </div>
                </div>

                <div class="empty-brand-copy">
                    <p class="mb-2 fw-bold text-white">Konteks kerja diperlukan</p>
                    <p class="mb-0 empty-brand-subtitle">
                        Dashboard hanya dapat dibuka setelah akun memiliki posisi aktif yang sesuai.
                    </p>
                </div>

                <div class="empty-user">
                    <span class="empty-user-avatar">{{ $userInitials }}</span>
                    <div>
                        <p class="empty-user-name">{{ $userName }}</p>
                        <p class="empty-user-meta">Tahun aktif {{ $activeYear }}</p>
                    </div>
                </div>
            </aside>

            <div class="empty-content">
                <span class="empty-status">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    Posisi aktif belum tersedia
                </span>

                <span class="empty-icon" aria-hidden="true">
                    <i class="fa-solid fa-user-lock"></i>
                </span>

                <h1 class="empty-title" id="missing-position-title">Akun belum memiliki posisi aktif</h1>

                @if (session('status'))
                    <div class="alert alert-info mb-0 py-2 px-3">
                        {{ session('status') }}
                    </div>
                @endif

                <p class="empty-copy">
                    Akun Anda sudah berhasil masuk, tetapi belum ada posisi kerja aktif yang dapat digunakan untuk
                    membuka dashboard. Hubungi admin pengelola user agar posisi, instansi, unit kerja, dan masa berlaku
                    akun dilengkapi.
                </p>

                <div class="empty-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>
                        Setelah admin menambahkan posisi aktif, gunakan tombol cek lagi untuk kembali ke pemilihan
                        konteks kerja.
                    </span>
                </div>

                <div class="empty-actions">
                    <a href="{{ route('positions.index') }}" class="btn btn-primary empty-action">
                        <i class="fa-solid fa-rotate-right"></i>
                        Cek Lagi
                    </a>

                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger empty-action">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
