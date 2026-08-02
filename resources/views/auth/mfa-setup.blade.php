@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $userName = $user?->nama ?? 'User';
    $userInitials = collect(preg_split('/\s+/', trim($userName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $namePart): string => mb_substr($namePart, 0, 1))
        ->implode('');
    $userInitials = mb_strtoupper($userInitials ?: 'U');
    $manualEntryKey = is_array($enrollment ?? null) ? ($enrollment['manual_entry_key'] ?? null) : null;
    $provisioningUri = is_array($enrollment ?? null) ? ($enrollment['provisioning_uri'] ?? null) : null;
    $inlineQrCode = is_array($enrollment ?? null) ? ($enrollment['inline_qr_code'] ?? null) : null;
@endphp

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1b4f8c">
    <title>Setup MFA | SITANGKAS</title>
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
            --mfa-accent: #22b573;
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
            max-width: 1120px;
            margin: 0 auto;
        }

        .mfa-hero {
            height: 100%;
            padding: 1.5rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #123d71 0%, #1b4f8c 58%, #2f8df3 100%);
            color: #fff;
            box-shadow: 0 18px 36px rgba(27, 79, 140, .18);
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
            font-size: 1.7rem;
            font-weight: 800;
            line-height: 1.18;
        }

        .mfa-copy {
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

        .mfa-panel,
        .mfa-recovery-panel {
            border: 1px solid var(--mfa-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 14px 32px rgba(15, 47, 87, .08);
        }

        .mfa-panel-body,
        .mfa-recovery-body {
            padding: 1.25rem;
        }

        .mfa-panel-title {
            margin-bottom: .35rem;
            color: var(--mfa-ink);
            font-size: 1.1rem;
            font-weight: 800;
        }

        .mfa-panel-desc {
            margin-bottom: 1rem;
            color: var(--mfa-muted);
            font-size: .9rem;
            line-height: 1.55;
        }

        .mfa-qr-box {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 240px;
            padding: 1rem;
            border: 1px dashed rgba(100, 116, 139, .28);
            border-radius: 8px;
            background: #f8fafc;
        }

        .mfa-qr-box img {
            width: 220px;
            max-width: 100%;
            height: auto;
        }

        .mfa-manual-box {
            padding: .9rem;
            border: 1px solid rgba(27, 79, 140, .14);
            border-radius: 8px;
            background: rgba(27, 79, 140, .04);
        }

        .mfa-code-input {
            min-height: 52px;
            border-radius: 8px;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: .16em;
            text-align: center;
        }

        .mfa-secret {
            display: block;
            width: 100%;
            padding: .72rem .8rem;
            border: 1px solid rgba(100, 116, 139, .18);
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .82rem;
            word-break: break-all;
        }

        .mfa-copy-button {
            min-height: 38px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
        }

        .mfa-submit {
            min-height: 46px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
        }

        .mfa-recovery-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .65rem;
        }

        .mfa-recovery-code {
            padding: .72rem .8rem;
            border: 1px solid rgba(34, 181, 115, .18);
            border-radius: 8px;
            background: rgba(34, 181, 115, .06);
            color: #14532d;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .82rem;
            font-weight: 800;
            text-align: center;
            word-break: break-word;
        }

        body.dark-version {
            background: linear-gradient(180deg, #051139 0%, #0b1637 50%, #111c44 100%);
            color: #e2e8f0;
        }

        body.dark-version .mfa-frame,
        body.dark-version .mfa-topbar,
        body.dark-version .mfa-panel,
        body.dark-version .mfa-recovery-panel {
            border-color: rgba(148, 163, 184, .18);
            background: rgba(17, 28, 68, .92);
            box-shadow: 0 18px 42px rgba(0, 0, 0, .24);
        }

        body.dark-version .mfa-panel-title,
        body.dark-version .mfa-user-name,
        body.dark-version .mfa-brand-title {
            color: #f8fafc;
        }

        body.dark-version .mfa-panel-desc,
        body.dark-version .mfa-user-meta,
        body.dark-version .mfa-brand-subtitle {
            color: #94a3b8;
        }

        body.dark-version .mfa-qr-box,
        body.dark-version .mfa-manual-box {
            border-color: rgba(148, 163, 184, .22);
            background: rgba(15, 23, 42, .54);
        }

        body.dark-version .mfa-secret,
        body.dark-version .mfa-code-input,
        body.dark-version textarea.form-control {
            border-color: rgba(148, 163, 184, .28);
            background: #0b1637;
            color: #e2e8f0;
        }

        body.dark-version .mfa-recovery-code {
            border-color: rgba(34, 197, 94, .24);
            background: rgba(34, 197, 94, .12);
            color: #bbf7d0;
        }

        @media (max-width: 991.98px) {
            .mfa-main {
                align-items: flex-start;
            }

            .mfa-hero {
                margin-bottom: 1rem;
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
                font-size: 1.36rem;
            }

            .mfa-recovery-grid {
                grid-template-columns: 1fr;
            }

            .mfa-submit,
            .mfa-copy-button {
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
                        <p class="mfa-brand-subtitle">Multi-Factor Authentication</p>
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
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <section class="mfa-hero">
                                <span class="mfa-eyebrow">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    Google Authenticator
                                </span>
                                <h1 class="mfa-title">
                                    {{ $setupComplete ? 'MFA berhasil diaktifkan' : 'Aktifkan MFA untuk melanjutkan' }}
                                </h1>
                                <p class="mfa-copy mb-0">
                                    {{ $setupComplete
                                        ? 'Recovery codes sudah dibuat. Simpan kode ini untuk akses darurat saat aplikasi authenticator tidak tersedia.'
                                        : 'Scan QR atau masukkan manual key ke aplikasi authenticator, lalu verifikasi kode yang muncul.' }}
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
                            </section>
                        </div>

                        <div class="col-lg-7">
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

                            @if ($setupComplete)
                                <section class="mfa-recovery-panel">
                                    <div class="mfa-recovery-body">
                                        <h2 class="mfa-panel-title">Recovery Codes</h2>
                                        <p class="mfa-panel-desc">
                                            Kode ini hanya ditampilkan sekali. Gunakan salah satu kode saat aplikasi
                                            authenticator tidak dapat digunakan.
                                        </p>

                                        <div class="mfa-recovery-grid mb-4">
                                            @foreach ($recoveryCodes as $recoveryCode)
                                                <div class="mfa-recovery-code">{{ $recoveryCode }}</div>
                                            @endforeach
                                        </div>

                                        <a href="{{ $nextUrl }}" class="btn btn-primary mfa-submit">
                                            <i class="fa-solid fa-arrow-right"></i>
                                            Lanjutkan
                                        </a>
                                    </div>
                                </section>
                            @else
                                <section class="mfa-panel">
                                    <div class="mfa-panel-body">
                                        <h2 class="mfa-panel-title">Setup Authenticator</h2>
                                        <p class="mfa-panel-desc">
                                            Kode berubah setiap {{ $totpPeriodSeconds }} detik. Masukkan kode
                                            {{ $totpDigits }} digit pertama untuk mengaktifkan MFA.
                                        </p>

                                        <div class="row g-3 align-items-stretch mb-3">
                                            <div class="col-md-5">
                                                <div class="mfa-qr-box h-100">
                                                    @if (is_string($inlineQrCode) && $inlineQrCode !== '')
                                                        <img src="{{ $inlineQrCode }}" alt="QR code MFA">
                                                    @else
                                                        <div class="text-center text-muted px-2">
                                                            <i class="fa-solid fa-qrcode d-block h3 mb-2"></i>
                                                            QR belum tersedia. Gunakan manual key.
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-md-7">
                                                <div class="mfa-manual-box h-100">
                                                    <label class="form-label fw-bold">Manual key</label>
                                                    <span class="mfa-secret mb-2" data-copy-source="manual-key">
                                                        {{ $manualEntryKey }}
                                                    </span>
                                                    <button type="button" class="btn btn-outline-primary btn-sm mfa-copy-button"
                                                        data-copy-target="manual-key">
                                                        <i class="fa-solid fa-copy"></i>
                                                        Salin Key
                                                    </button>

                                                    @if (is_string($provisioningUri) && $provisioningUri !== '')
                                                        <label class="form-label fw-bold mt-3">Provisioning URI</label>
                                                        <textarea class="form-control small" rows="3" readonly data-copy-source="provisioning-uri">{{ $provisioningUri }}</textarea>
                                                        <button type="button"
                                                            class="btn btn-outline-secondary btn-sm mfa-copy-button mt-2"
                                                            data-copy-target="provisioning-uri">
                                                            <i class="fa-solid fa-link"></i>
                                                            Salin URI
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <form id="formTotpSetup" method="POST" action="{{ route('login.mfa.setup.store') }}" autocomplete="off">
                                            @csrf

                                            <div class="mb-3">
                                                <label for="one_time_password" class="form-label fw-bold">
                                                    Kode Authenticator
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

                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <form action="{{ route('logout') }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary mfa-submit">
                                                    <i class="fa-solid fa-right-from-bracket"></i>
                                                    Logout
                                                </button>
                                            </form>

                                            <button type="submit" form="formTotpSetup" class="btn btn-primary mfa-submit">
                                                <i class="fa-solid fa-circle-check"></i>
                                                Verifikasi MFA
                                            </button>
                                        </div>
                                    </div>
                                </section>
                            @endif
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-copy-target]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const key = button.getAttribute('data-copy-target');
                    const source = document.querySelector(`[data-copy-source="${key}"]`);

                    if (!source) {
                        return;
                    }

                    const text = source.value || source.textContent || '';

                    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                        source.focus();

                        return;
                    }

                    navigator.clipboard.writeText(text.trim()).then(function() {
                        button.classList.remove('btn-outline-primary', 'btn-outline-secondary');
                        button.classList.add('btn-success');
                        const originalHtml = button.innerHTML;
                        button.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin';

                        window.setTimeout(function() {
                            button.classList.remove('btn-success');
                            button.classList.add(key === 'manual-key' ? 'btn-outline-primary' : 'btn-outline-secondary');
                            button.innerHTML = originalHtml;
                        }, 1200);
                    }).catch(function() {
                        source.focus();
                    });
                });
            });
        });
    </script>
</body>

</html>
