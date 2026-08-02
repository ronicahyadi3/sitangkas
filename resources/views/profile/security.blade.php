@php
    $regeneratedRecoveryCodes = session('mfa_recovery_codes_regenerated');
    $regeneratedRecoveryCodeList = is_array($regeneratedRecoveryCodes) && is_array($regeneratedRecoveryCodes['codes'] ?? null)
        ? array_values(array_filter(
            $regeneratedRecoveryCodes['codes'],
            static fn (mixed $recoveryCode): bool => is_string($recoveryCode) && $recoveryCode !== ''
        ))
        : [];
    $canRegenerateRecoveryCodes = $mfaStatus['is_enrolled'] && $mfaStatus['is_verified_with_totp'];
    $canShowMfaEnrollmentAction = $mfaStatus['can_start_enrollment'] || $mfaStatus['can_continue_enrollment'];
@endphp

@extends('layouts.app')

@section('title', 'Keamanan Akun - SITANGKAS')

@section('content')
    <div class="profile-security-page">
        @if (session('status'))
            <div class="alert alert-success text-white" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                {{ session('status') }}
            </div>
        @endif

        @error('recovery_codes')
            <div class="alert alert-danger text-white" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                {{ $message }}
            </div>
        @enderror

        @if ($regeneratedRecoveryCodeList !== [])
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-3">
                        <div>
                            <span class="badge bg-gradient-warning mb-3">
                                <i class="fa-solid fa-key me-1"></i>
                                Recovery Codes Baru
                            </span>
                            <h2 class="h5 font-weight-bolder mb-2">Simpan recovery codes ini</h2>
                            <p class="text-sm text-secondary mb-0">
                                Codes lama sudah dicabut dan codes baru hanya ditampilkan pada halaman ini.
                            </p>
                        </div>
                        <span class="badge bg-gradient-dark">
                            {{ count($regeneratedRecoveryCodeList) }} codes
                        </span>
                    </div>

                    <div class="row g-2">
                        @foreach ($regeneratedRecoveryCodeList as $recoveryCode)
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="border rounded-3 bg-gray-100 px-3 py-2">
                                    <code class="text-dark font-weight-bold">{{ $recoveryCode }}</code>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                            <div>
                                <span class="badge {{ $mfaStatus['badge_class'] }} mb-3">
                                    <i class="fa-solid {{ $mfaStatus['icon'] }} me-1"></i>
                                    {{ $mfaStatus['label'] }}
                                </span>
                                <h1 class="h4 font-weight-bolder mb-2">Keamanan Akun</h1>
                                <p class="text-sm text-secondary mb-0">
                                    Ringkasan status MFA dan recovery code untuk akun aktif.
                                </p>
                            </div>

                            <a href="{{ route('dashboard') }}" class="btn btn-outline-primary mb-0">
                                <i class="fa-solid fa-arrow-left me-2"></i>
                                Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Status MFA</p>
                                <h2 class="h5 font-weight-bolder mb-0">{{ $mfaStatus['label'] }}</h2>
                            </div>
                            <span class="icon icon-shape {{ $mfaStatus['badge_class'] }} text-white rounded-circle shadow">
                                <i class="fa-solid {{ $mfaStatus['icon'] }}"></i>
                            </span>
                        </div>
                        <p class="text-sm text-secondary mt-3 mb-0">
                            Policy: {{ $mfaStatus['policy_label'] }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Metode Aktif</p>
                                <h2 class="h6 font-weight-bolder mb-0">{{ $mfaStatus['method_label'] }}</h2>
                            </div>
                            <span class="icon icon-shape bg-gradient-primary text-white rounded-circle shadow">
                                <i class="fa-solid fa-mobile-screen-button"></i>
                            </span>
                        </div>
                        <p class="text-sm text-secondary mt-3 mb-0">
                            Aktif sejak: {{ $mfaStatus['enabled_at_label'] }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Terakhir Dipakai</p>
                                <h2 class="h6 font-weight-bolder mb-0">{{ $mfaStatus['last_used_at_label'] }}</h2>
                            </div>
                            <span class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </span>
                        </div>
                        <p class="text-sm text-secondary mt-3 mb-0">
                            Sesi: {{ $mfaStatus['session_method_label'] }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Recovery Codes</p>
                                <h2 class="h5 font-weight-bolder mb-0">{{ $mfaStatus['recovery_codes_remaining'] }}</h2>
                            </div>
                            <span class="icon icon-shape bg-gradient-dark text-white rounded-circle shadow">
                                <i class="fa-solid fa-key"></i>
                            </span>
                        </div>
                        <p class="text-sm text-secondary mt-3 mb-0">
                            Dibuat: {{ $mfaStatus['recovery_codes_generated_at_label'] }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-8">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start gap-3">
                            <span class="icon icon-shape bg-gradient-success text-white rounded-circle shadow">
                                <i class="fa-solid fa-user-shield"></i>
                            </span>
                            <div>
                                <h2 class="h6 font-weight-bolder mb-2">Ringkasan MFA</h2>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-1">Status Enrollment</p>
                                        <p class="text-sm mb-0">{{ $mfaStatus['is_enrolled'] ? 'Sudah enroll' : 'Belum enroll' }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-1">Setup Pending</p>
                                        <p class="text-sm mb-0">{{ $mfaStatus['has_pending_enrollment'] ? 'Ada' : 'Tidak ada' }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-1">Session MFA Berakhir</p>
                                        <p class="text-sm mb-0">{{ $mfaStatus['session_expires_at_label'] }}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-1">Verified Via TOTP</p>
                                        <p class="text-sm mb-0">{{ $mfaStatus['is_verified_with_totp'] ? 'Ya' : 'Tidak' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body p-4">
                        <div>
                            <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Akun</p>
                            <h2 class="h6 font-weight-bolder mb-1">{{ $user?->nama ?? 'User' }}</h2>
                            <p class="text-sm text-secondary mb-0">{{ $user?->nik ?? '-' }}</p>
                        </div>

                        <hr class="horizontal dark">

                        <div>
                            <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Konteks Real</p>
                            <p class="text-sm font-weight-bold mb-1">
                                {{ $realActiveUserPosition?->jabatan?->nama ?? '-' }}
                            </p>
                            <p class="text-sm text-secondary mb-0">
                                {{ $realActiveUserPosition?->instansi?->nama ?? '-' }}
                            </p>
                        </div>

                        <hr class="horizontal dark">

                        @if ($canShowMfaEnrollmentAction)
                            <div>
                                <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Authenticator</p>
                                <a href="{{ route('login.mfa.setup') }}" class="btn btn-primary w-100 mb-2">
                                    <i class="fa-solid {{ $mfaStatus['enrollment_action_icon'] }} me-2"></i>
                                    {{ $mfaStatus['enrollment_action_label'] }}
                                </a>
                                <p class="text-xs text-secondary mb-0">
                                    {{ $mfaStatus['enrollment_action_help'] }}
                                </p>
                            </div>

                            <hr class="horizontal dark">
                        @endif

                        <div>
                            <p class="text-xs text-uppercase text-secondary font-weight-bolder mb-2">Recovery Codes</p>
                            <form method="POST" action="{{ route('profile.security.mfa.recovery_codes.regenerate') }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100 mb-2" {{ $canRegenerateRecoveryCodes ? '' : 'disabled' }}>
                                    <i class="fa-solid fa-rotate me-2"></i>
                                    Regenerate Codes
                                </button>
                            </form>
                            <p class="text-xs text-secondary mb-0">
                                @if ($canRegenerateRecoveryCodes)
                                    Membuat codes baru akan mencabut semua recovery codes lama.
                                @else
                                    Regenerate membutuhkan MFA aktif dan session TOTP valid.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
