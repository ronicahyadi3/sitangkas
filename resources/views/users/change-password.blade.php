@php
    $passwordChangeErrors = $errors->all();
    $passwordChangeHasErrors = $errors->any();
    $passwordChangeSuccess = session('password_change_success');
    $passwordChangeValidationFailed = session('password_change_validation_failed');
    $passwordChangeNotice = session('password_change_required');
    $legacyStatus = session('status');
    $sessionErrors = session('errors');

    if (!$passwordChangeHasErrors && $sessionErrors instanceof \Illuminate\Support\ViewErrorBag && $sessionErrors->any()) {
        $passwordChangeErrors = $sessionErrors->all();
        $passwordChangeHasErrors = true;
    }

    if (!$passwordChangeHasErrors && $passwordChangeValidationFailed) {
        $passwordChangeErrors = ['Password belum dapat diperbarui. Periksa kembali password lama, password baru, dan konfirmasi password.'];
        $passwordChangeHasErrors = true;
    }

    if ($passwordChangeHasErrors) {
        $passwordChangeSuccess = null;
        $passwordChangeNotice = null;
    }

    if (!$passwordChangeHasErrors && !$passwordChangeSuccess && !$passwordChangeNotice && is_string($legacyStatus) && $legacyStatus !== '') {
        if ($legacyStatus === 'Password berhasil diperbarui.') {
            $passwordChangeSuccess = $legacyStatus;
        } else {
            $passwordChangeNotice = $legacyStatus;
        }
    }
@endphp

@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">

            <div class="card shadow-lg border-0">
                <div class="card-header pb-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon icon-shape icon-lg bg-gradient-primary shadow text-center border-radius-lg">
                            <i class="fa fa-key text-white opacity-10"></i>
                        </div>

                        <div>
                            <p class="text-uppercase text-sm text-primary font-weight-bolder mb-1">
                                Keamanan Akun
                            </p>
                            <h5 class="mb-0">
                                Ganti Password Akun Anda
                            </h5>
                            <p class="text-sm text-muted mb-0">
                                Perbarui password secara berkala untuk menjaga akses aplikasi tetap aman.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card-body">

                    @if ($passwordChangeSuccess)
                        <div class="alert alert-success text-white bg-gradient-success" role="alert">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa fa-circle-check mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">Password berhasil diperbarui</strong>
                                    <span>{{ $passwordChangeSuccess }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($passwordChangeNotice)
                        <div class="alert alert-warning text-white bg-gradient-warning" role="alert">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa fa-circle-info mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">Password wajib diperbarui</strong>
                                    <span>{{ $passwordChangeNotice }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($passwordChangeHasErrors)
                        <div class="alert alert-danger text-white bg-gradient-danger" role="alert">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa fa-triangle-exclamation mt-1"></i>
                                <div>
                                    <strong class="d-block mb-1">Password belum dapat diperbarui</strong>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($passwordChangeErrors as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row mb-4">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="border rounded-3 p-3 h-100">
                                <p class="text-xs text-uppercase text-muted font-weight-bolder mb-1">
                                    Nama Pengguna
                                </p>
                                <h6 class="mb-0">
                                    <i class="fa fa-user me-2 text-primary"></i>
                                    {{ $user->nama }}
                                </h6>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="border rounded-3 p-3 h-100">
                                <p class="text-xs text-uppercase text-muted font-weight-bolder mb-1">
                                    NIK
                                </p>
                                <h6 class="mb-0">
                                    <i class="fa fa-id-card me-2 text-primary"></i>
                                    {{ $user->nik }}
                                </h6>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <p class="text-xs text-uppercase text-muted font-weight-bolder mb-1">
                                    Masa Berlaku
                                </p>
                                <h6 class="mb-0">
                                    <i class="fa fa-clock me-2 text-primary"></i>
                                    @if ($passwordExpiresAt)
                                        {{ $passwordExpiresAt->translatedFormat('d F Y') }}
                                    @else
                                        Belum ada riwayat
                                    @endif
                                </h6>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning text-white bg-gradient-warning" role="alert">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa fa-circle-info mt-1"></i>
                            <div>
                                <strong class="d-block mb-1">Perhatian</strong>
                                Perubahan password ini diwajibkan sebagai bentuk kepatuhan terhadap kebijakan keamanan
                                informasi yang ditetapkan oleh Badan Siber dan Sandi Negara (BSSN).
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('password.change.save') }}" novalidate>
                        @csrf

                        <div class="row">

                            {{-- Password Lama --}}
                            <div class="col-12 mb-3">
                                <label for="old_password" class="form-label">
                                    Password Lama
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fa fa-lock"></i>
                                    </span>

                                    <input
                                        id="old_password"
                                        type="password"
                                        name="old_password"
                                        class="form-control @error('old_password') is-invalid @enderror"
                                        autocomplete="current-password"
                                        placeholder="Masukkan password lama"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary mb-0 toggle-password"
                                        data-target="old_password"
                                        aria-label="Tampilkan password lama"
                                        aria-pressed="false"
                                    >
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>

                                @error('old_password')
                                    <div class="text-danger text-xs mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Password Baru --}}
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    Password Baru
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fa fa-shield-alt"></i>
                                    </span>

                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        autocomplete="new-password"
                                        placeholder="Masukkan password baru"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary mb-0 toggle-password"
                                        data-target="password"
                                        aria-label="Tampilkan password baru"
                                        aria-pressed="false"
                                    >
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>

                                @error('password')
                                    <div class="text-danger text-xs mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <p class="text-xs text-muted mt-2 mb-0">
                                    Minimal 8 karakter, berisi huruf besar, huruf kecil, angka, simbol, dan berbeda dari
                                    password lama.
                                </p>
                            </div>

                            {{-- Konfirmasi Password Baru --}}
                            <div class="col-md-6 mb-3">
                                <label for="password_confirmation" class="form-label">
                                    Ulangi Password Baru
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fa fa-check-circle"></i>
                                    </span>

                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        class="form-control"
                                        autocomplete="new-password"
                                        placeholder="Ulangi password baru"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary mb-0 toggle-password"
                                        data-target="password_confirmation"
                                        aria-label="Tampilkan konfirmasi password baru"
                                        aria-pressed="false"
                                    >
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                        </div>

                        <hr class="horizontal dark my-4">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <p class="text-sm text-muted mb-0">
                                Setelah berhasil, tanggal perubahan password akan diperbarui untuk periode 3 bulan berikutnya.
                            </p>

                            <button type="submit" class="btn bg-gradient-primary mb-0">
                                <i class="fa fa-save me-2"></i>
                                Simpan Password Baru
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection

@section('additionals')
    <script>
        $(document).ready(function () {
            const passwordChangeErrors = @json($passwordChangeErrors);
            const passwordChangeSuccess = @json($passwordChangeSuccess);
            const passwordChangeNotice = @json($passwordChangeNotice);

            if (passwordChangeErrors.length > 0 && window.Swal) {
                const html = '<ul class="text-start mb-0 ps-3">' + passwordChangeErrors
                    .map(function (message) {
                        return '<li>' + $('<div>').text(message).html() + '</li>';
                    })
                    .join('') + '</ul>';

                Swal.fire({
                    icon: 'error',
                    title: 'Password belum dapat diperbarui',
                    html: html,
                    confirmButtonText: 'Mengerti',
                    confirmButtonColor: '#ea0606',
                });
            } else if (passwordChangeSuccess && window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Password berhasil diperbarui',
                    text: passwordChangeSuccess,
                    confirmButtonText: 'Mengerti',
                    confirmButtonColor: '#2dce89',
                });
            } else if (passwordChangeNotice && window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Password wajib diperbarui',
                    text: passwordChangeNotice,
                    confirmButtonText: 'Mengerti',
                    confirmButtonColor: '#fb6340',
                });
            }

            $(document).on('click', '.toggle-password', function () {
                const button = $(this);
                const targetId = button.data('target');
                const input = $('#' + targetId);
                const icon = button.find('i');

                if (!input.length) {
                    return;
                }

                const isHidden = input.attr('type') === 'password';

                input.attr('type', isHidden ? 'text' : 'password');

                button.toggleClass('active', isHidden);
                button.attr('aria-pressed', isHidden ? 'true' : 'false');
                button.attr(
                    'aria-label',
                    isHidden ? 'Sembunyikan password' : 'Tampilkan password'
                );

                icon.toggleClass('fa-eye', !isHidden);
                icon.toggleClass('fa-eye-slash', isHidden);
            });
        });
    </script>
@endsection
