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

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1b4f8c">
    <title>Verifikasi MFA | SITANGKAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}">

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/nucleo-svg.css') }}" rel="stylesheet">
    <link id="pagestyle" href="{{ asset('assets/css/argon-dashboard.css') }}?v=2.0.7" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <style>
        :root {
            --mfa-primary: #1b4f8c;
            --mfa-secondary: #2f8df3;
            --mfa-ink: #0f172a;
            --mfa-muted: #64748b;
            --mfa-border: rgba(100, 116, 139, .18);
        }

        html,
        body {
            min-height: 100%;
            font-family: "Open Sans", sans-serif;
            background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 48%, #f8fafc 100%);
            color: var(--mfa-ink);
        }

        .auth-shell {
            min-height: 100vh;
            padding: 1rem;
        }

        .mfa-frame {
            min-height: calc(100vh - 2rem);
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, .72);
            border-radius: 8px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 18px 48px rgba(15, 47, 87, .1);
            overflow: hidden;
        }

        .mfa-topbar {
            min-height: 76px;
            border-bottom: 1px solid var(--mfa-border);
            background: rgba(255, 255, 255, .86);
            backdrop-filter: blur(12px);
        }

        .mfa-brand,
        .mfa-user {
            display: flex;
            align-items: center;
            gap: .8rem;
            min-width: 0;
        }

        .mfa-brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .mfa-brand-title {
            margin-bottom: 0;
            color: var(--mfa-primary);
            font-size: 1rem;
            font-weight: 800;
        }

        .mfa-brand-subtitle,
        .mfa-user-meta {
            margin-bottom: 0;
            color: var(--mfa-muted);
            font-size: .78rem;
        }

        .mfa-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--mfa-primary), var(--mfa-secondary));
            color: #fff;
            font-size: .82rem;
            font-weight: 800;
        }

        .mfa-user-name {
            max-width: 220px;
            margin-bottom: .12rem;
            color: var(--mfa-ink);
            font-size: .88rem;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mfa-main {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 1.25rem;
        }

        .mfa-wrap {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
        }

        .mfa-panel {
            border: 1px solid var(--mfa-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 14px 32px rgba(15, 47, 87, .08);
            overflow: hidden;
        }

        .mfa-side {
            height: 100%;
            padding: 1.4rem;
            background: linear-gradient(135deg, #123d71 0%, #1b4f8c 58%, #2f8df3 100%);
            color: #fff;
        }

        .mfa-eyebrow {
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

        .mfa-title {
            margin: .85rem 0 .55rem;
            color: #fff;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.18;
        }

        .mfa-copy {
            margin-bottom: 0;
            color: rgba(255, 255, 255, .84);
            font-size: .92rem;
            line-height: 1.65;
        }

        .mfa-context {
            display: grid;
            gap: .65rem;
            margin-top: 1.25rem;
        }

        .mfa-context-item {
            padding: .75rem .85rem;
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 8px;
            background: rgba(255, 255, 255, .1);
        }

        .mfa-context-label {
            display: block;
            margin-bottom: .18rem;
            color: rgba(255, 255, 255, .68);
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .mfa-context-value {
            color: #fff;
            font-size: .9rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .mfa-form-body {
            padding: 1.4rem;
        }

        .mfa-form-title {
            margin-bottom: .35rem;
            color: var(--mfa-ink);
            font-size: 1.15rem;
            font-weight: 800;
        }

        .mfa-form-desc {
            margin-bottom: 1rem;
            color: var(--mfa-muted);
            font-size: .9rem;
            line-height: 1.55;
        }

        .mfa-code-input {
            min-height: 52px;
            border-radius: 8px;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: .16em;
            text-align: center;
        }

        .mfa-recovery-card {
            padding: .95rem;
            border: 1px solid rgba(27, 79, 140, .14);
            border-radius: 8px;
            background: rgba(27, 79, 140, .05);
        }

        .mfa-recovery-title {
            margin-bottom: .28rem;
            color: var(--mfa-ink);
            font-size: .92rem;
            font-weight: 800;
        }

        .mfa-recovery-desc {
            margin-bottom: .75rem;
            color: var(--mfa-muted);
            font-size: .84rem;
            line-height: 1.5;
        }

        .mfa-recovery-input {
            min-height: 46px;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .92rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-align: center;
            text-transform: uppercase;
        }

        .mfa-submit {
            min-height: 46px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
        }

        .mfa-session-note {
            padding: .85rem .95rem;
            border: 1px solid rgba(27, 79, 140, .14);
            border-radius: 8px;
            background: rgba(27, 79, 140, .05);
            color: #334155;
            font-size: .86rem;
            line-height: 1.55;
        }

        body.dark-version {
            background: linear-gradient(180deg, #051139 0%, #0b1637 50%, #111c44 100%);
            color: #e2e8f0;
        }

        body.dark-version .mfa-frame,
        body.dark-version .mfa-topbar,
        body.dark-version .mfa-panel {
            border-color: rgba(148, 163, 184, .18);
            background: rgba(17, 28, 68, .92);
            box-shadow: 0 18px 42px rgba(0, 0, 0, .24);
        }

        body.dark-version .mfa-form-title,
        body.dark-version .mfa-user-name,
        body.dark-version .mfa-brand-title {
            color: #f8fafc;
        }

        body.dark-version .mfa-form-desc,
        body.dark-version .mfa-user-meta,
        body.dark-version .mfa-brand-subtitle {
            color: #94a3b8;
        }

        body.dark-version .mfa-code-input {
            border-color: rgba(148, 163, 184, .28);
            background: #0b1637;
            color: #e2e8f0;
        }

        body.dark-version .mfa-recovery-card {
            border-color: rgba(59, 130, 246, .22);
            background: rgba(59, 130, 246, .12);
        }

        body.dark-version .mfa-recovery-title {
            color: #f8fafc;
        }

        body.dark-version .mfa-recovery-desc {
            color: #cbd5e1;
        }

        body.dark-version .mfa-recovery-input {
            border-color: rgba(148, 163, 184, .28);
            background: #0b1637;
            color: #e2e8f0;
        }

        body.dark-version .mfa-session-note {
            border-color: rgba(59, 130, 246, .22);
            background: rgba(59, 130, 246, .12);
            color: #cbd5e1;
        }

        @media (max-width: 991.98px) {
            .mfa-main {
                align-items: flex-start;
            }
        }

        @media (max-width: 575.98px) {
            .auth-shell {
                padding: .5rem;
            }

            .mfa-frame {
                min-height: calc(100vh - 1rem);
            }

            .mfa-main {
                padding: 1rem;
            }

            .mfa-title {
                font-size: 1.32rem;
            }

            .mfa-submit {
                width: 100%;
            }
        }
    </style>
    @include('inc.standalone-dark-mode')
</head>

<body>
    <div class="auth-shell">
        <div class="mfa-frame">
            <header class="mfa-topbar d-flex align-items-center justify-content-between flex-wrap px-3 px-lg-4">
                <div class="mfa-brand">
                    <img src="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}" alt="Logo Pemerintah Kota Malang">
                    <div>
                        <p class="mfa-brand-title">SITANGKAS</p>
                        <p class="mfa-brand-subtitle">Verifikasi MFA</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="public-theme-toggle-slot" data-public-theme-toggle-mount
                        data-public-theme-toggle-variant="icon"></span>

                    <div class="mfa-user">
                        <span class="mfa-avatar">{{ $userInitials }}</span>
                        <div class="d-none d-sm-block">
                            <div class="mfa-user-name">{{ $userName }}</div>
                            <div class="mfa-user-meta">Tahun aktif {{ $activeYear ?? now()->year }}</div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mfa-main">
                <div class="mfa-wrap">
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
                                    <strong class="d-block mb-1">Verifikasi MFA belum berhasil</strong>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    <section class="mfa-panel">
                        <div class="row g-0">
                            <div class="col-lg-5">
                                <div class="mfa-side">
                                    <span class="mfa-eyebrow">
                                        <i class="fa-solid fa-shield-halved"></i>
                                        Authenticator atau recovery code
                                    </span>
                                    <h1 class="mfa-title">Masukkan kode MFA</h1>
                                    <p class="mfa-copy">
                                        Gunakan kode dari aplikasi authenticator, atau recovery code sekali pakai jika
                                        authenticator tidak tersedia.
                                    </p>

                                    <div class="mfa-context">
                                        <div class="mfa-context-item">
                                            <span class="mfa-context-label">Jabatan nyata</span>
                                            <div class="mfa-context-value">
                                                {{ $positionContext['nama_jabatan'] ?? 'Jabatan belum tersedia' }}
                                            </div>
                                        </div>
                                        <div class="mfa-context-item">
                                            <span class="mfa-context-label">Instansi</span>
                                            <div class="mfa-context-value">
                                                {{ $positionContext['nama_instansi'] ?? 'Instansi belum tersedia' }}
                                            </div>
                                        </div>
                                        <div class="mfa-context-item">
                                            <span class="mfa-context-label">Unit kerja</span>
                                            <div class="mfa-context-value">
                                                {{ $positionContext['nama_unit_kerja'] ?? 'Unit kerja belum tersedia' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <div class="mfa-form-body">
                                    <h2 class="mfa-form-title">Verifikasi Authenticator</h2>
                                    <p class="mfa-form-desc">
                                        Kode berubah setiap {{ $totpPeriodSeconds }} detik. Session MFA valid sampai
                                        {{ $verifiedExpiresAt->timezone(config('app.timezone'))->format('H:i') }} jika verifikasi berhasil.
                                    </p>

                                    <form id="formTotpChallenge" method="POST" action="{{ route('login.mfa.store') }}" autocomplete="off">
                                        @csrf
                                        <input type="hidden" name="challenge_method" value="totp">

                                        <div class="mb-3">
                                            <label for="one_time_password" class="form-label fw-bold">
                                                Kode {{ $totpDigits }} Digit
                                            </label>
                                            <input
                                                type="text"
                                                name="one_time_password"
                                                id="one_time_password"
                                                value="{{ old('one_time_password') }}"
                                                class="form-control mfa-code-input @error('one_time_password') is-invalid @enderror"
                                                inputmode="numeric"
                                                autocomplete="one-time-code"
                                                maxlength="20"
                                                pattern="[0-9\-\s]*"
                                                required
                                                autofocus
                                            >
                                            @error('one_time_password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </form>

                                    <div class="mfa-session-note mb-3">
                                        Admin Super tetap harus memilih acting context setelah MFA valid.
                                    </div>

                                    <div class="mfa-recovery-card mb-3">
                                        <h3 class="mfa-recovery-title">Gunakan recovery code</h3>
                                        <p class="mfa-recovery-desc">
                                            Recovery code hanya bisa dipakai satu kali dan akan otomatis dicabut setelah
                                            verifikasi berhasil.
                                        </p>

                                        <form id="formRecoveryCodeChallenge" method="POST" action="{{ route('login.mfa.store') }}" autocomplete="off">
                                            @csrf
                                            <input type="hidden" name="challenge_method" value="recovery_code">

                                            <div class="row g-2 align-items-start">
                                                <div class="col-md">
                                                    <label for="recovery_code" class="visually-hidden">Recovery code</label>
                                                    <input
                                                        type="text"
                                                        name="recovery_code"
                                                        id="recovery_code"
                                                        class="form-control mfa-recovery-input @error('recovery_code') is-invalid @enderror"
                                                        inputmode="text"
                                                        autocomplete="off"
                                                        maxlength="64"
                                                        placeholder="XXXX-XXXX-XXXX-XXXX"
                                                    >
                                                    @error('recovery_code')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                                <div class="col-md-auto">
                                                    <button type="submit" class="btn btn-outline-primary mfa-submit">
                                                        <i class="fa-solid fa-key"></i>
                                                        Pakai Recovery Code
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary mfa-submit">
                                                <i class="fa-solid fa-right-from-bracket"></i>
                                                Logout
                                            </button>
                                        </form>

                                        <button type="submit" form="formTotpChallenge" class="btn btn-primary mfa-submit">
                                            <i class="fa-solid fa-circle-check"></i>
                                            Verifikasi MFA
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
