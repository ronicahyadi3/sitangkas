@extends('layouts.app')

@section('styling')
    <style>
        .switch-hero {
            background: linear-gradient(135deg, rgb(239 255 239 / 90%), rgba(17, 205, 239, 0.9));
        }

        .position-card {
            border: 1px solid rgba(17, 24, 39, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .position-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 1rem 2rem rgba(17, 24, 39, 0.08);
            border-color: rgba(94, 114, 228, 0.2);
        }

        .position-card.is-active {
            border-color: rgba(45, 206, 137, 0.35);
            box-shadow: 0 1rem 2rem rgba(45, 206, 137, 0.12);
        }

        .position-meta {
            display: grid;
            gap: 0.75rem;
        }

        .position-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.875rem;
        }

        .position-meta-row span {
            color: #8392ab;
        }

        .position-meta-row strong {
            color: #344767;
            text-align: right;
        }

        .position-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(17, 24, 39, 0.08);
            font-size: 0.875rem;
            color: #344767;
        }

        .empty-state {
            border: 1px dashed rgba(131, 146, 171, 0.5);
        }

        .status-panel {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            border: 1px solid transparent;
            animation: statusReveal 0.28s ease;
            transition: transform 0.45s ease, opacity 0.45s ease, max-height 0.45s ease, margin 0.45s ease, padding 0.45s ease;
            transform-origin: top center;
            max-height: 320px;
        }

        .status-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0));
            pointer-events: none;
        }

        .status-panel--success {
            background: linear-gradient(135deg, #17ad37, #29b85a);
            border-color: rgba(23, 173, 55, 0.35);
            box-shadow: 0 1.25rem 2.5rem rgba(23, 173, 55, 0.18);
        }

        .status-panel--error {
            background: linear-gradient(135deg, #ea0606, #ff5b5b);
            border-color: rgba(234, 6, 6, 0.35);
            box-shadow: 0 1.25rem 2.5rem rgba(234, 6, 6, 0.18);
        }

        .status-icon {
            width: 3rem;
            height: 3rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            flex-shrink: 0;
        }

        .status-close {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .status-close:hover {
            background: rgba(255, 255, 255, 0.26);
            transform: scale(1.05);
        }

        .status-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.8rem;
            border-radius: 999px;
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        .super-context-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(94, 114, 228, 0.18);
            box-shadow: 0 1rem 2rem rgba(17, 24, 39, 0.06);
        }

        .super-context-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, #5e72e4, #11cdef, #2dce89);
        }

        .super-context-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .super-context-icon {
            width: 3rem;
            height: 3rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            background: rgba(94, 114, 228, 0.12);
            color: #5e72e4;
            flex-shrink: 0;
        }

        .super-context-eyebrow {
            display: block;
            margin-bottom: 0.25rem;
            color: #8392ab;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .super-context-title {
            margin-bottom: 0.25rem;
            color: #344767;
        }

        .super-context-desc {
            color: #67748e;
            font-size: 0.875rem;
        }

        .super-context-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .super-context-item,
        .super-context-special {
            border: 1px solid rgba(131, 146, 171, 0.18);
            border-radius: 0.9rem;
            background: #f8f9fa;
        }

        .super-context-item {
            min-height: 5.25rem;
            padding: 0.85rem;
        }

        .super-context-item span,
        .super-context-special span {
            display: block;
            margin-bottom: 0.25rem;
            color: #8392ab;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .super-context-item strong,
        .super-context-special strong {
            display: block;
            color: #344767;
            font-size: 0.92rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .super-context-item small,
        .super-context-special small {
            display: block;
            margin-top: 0.25rem;
            color: #8392ab;
            line-height: 1.35;
        }

        .super-context-special {
            padding: 1rem;
        }

        .super-context-special-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.65rem;
        }

        .super-context-special-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
            background: #fff;
            border: 1px solid rgba(131, 146, 171, 0.16);
            color: #344767;
            font-size: 0.78rem;
            letter-spacing: 0;
            text-transform: none;
        }

        .super-context-special-scope {
            min-width: 14rem;
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: #fff;
            border: 1px solid rgba(131, 146, 171, 0.16);
        }

        .status-panel.is-hiding {
            opacity: 0;
            transform: translateY(-18px);
            max-height: 0;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            border-width: 0;
        }

        @keyframes statusReveal {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1199.98px) {
            .super-context-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .super-context-grid {
                grid-template-columns: 1fr;
            }

            .super-context-special-scope {
                width: 100%;
                min-width: 0;
            }
        }

    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card switch-hero mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <span class="badge bg-gradient-primary mb-3">Pengaturan Akun</span>
                            <h2 class="mb-2">Ganti Jabatan Aktif</h2>
                            <p class="text-sm text-secondary mb-0">
                                Pilih jabatan yang ingin Anda gunakan. Perpindahan ini langsung mengubah konteks kerja,
                                menu, dan akses dokumen yang tampil.
                            </p>
                        </div>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-primary mb-0">
                            <i class="fa-solid fa-arrow-left me-2"></i>Kembali ke Dashboard
                        </a>
                    </div>

                    @if ($activePosition)
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <div class="position-chip">
                                <i class="fa-solid fa-user-check text-success"></i>
                                <strong>{{ $activePosition->jabatan?->nama ?? 'Jabatan' }}</strong>
                            </div>
                            <div class="position-chip">
                                <i class="fa-solid fa-building text-info"></i>
                                {{ $activePosition->instansi?->nama ?? 'Instansi belum diatur' }}
                            </div>
                            <div class="position-chip">
                                <i class="fa-solid fa-sitemap text-warning"></i>
                                {{ $activePosition->unitKerja?->nama ?? 'Unit kerja belum diatur' }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (!empty($adminSuperContext))
            <div class="col-12">
                <div class="card super-context-card mb-4">
                    <div class="card-body p-4">
                        <div class="super-context-header">
                            <div class="d-flex align-items-start gap-3">
                                <div class="super-context-icon">
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>
                                <div>
                                    <span class="super-context-eyebrow">Mode Admin Super</span>
                                    <h5 class="super-context-title">Konteks Login Admin Super</h5>
                                    <p class="super-context-desc mb-0">
                                        Konteks ini sedang dipakai untuk dashboard, menu, dan akses dokumen pada sesi saat ini.
                                    </p>
                                </div>
                            </div>

                            <a href="{{ route('login.post') }}" class="btn btn-outline-primary mb-0">
                                <i class="fa-solid fa-sliders me-2"></i>Ubah Konteks
                            </a>
                        </div>

                        <div class="super-context-grid mt-4">
                            <div class="super-context-item">
                                <span>Akun Login</span>
                                <strong>{{ $adminSuperContext['login_user'] }}</strong>
                                <small>{{ $adminSuperContext['login_role'] }}</small>
                            </div>
                            <div class="super-context-item">
                                <span>Jabatan Konteks</span>
                                <strong>{{ $adminSuperContext['jabatan'] }}</strong>
                            </div>
                            <div class="super-context-item">
                                <span>Instansi</span>
                                <strong>{{ $adminSuperContext['instansi'] }}</strong>
                            </div>
                            <div class="super-context-item">
                                <span>Unit Kerja</span>
                                <strong>{{ $adminSuperContext['unit_kerja'] }}</strong>
                            </div>
                            <div class="super-context-item">
                                <span>Tahun Aktif</span>
                                <strong>{{ $adminSuperContext['tahun_aktif'] }}</strong>
                            </div>
                        </div>

                        @if (!empty($adminSuperContext['special_user_label']))
                            <div class="super-context-special mt-3">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <span>{{ $adminSuperContext['special_user_label'] }}</span>
                                        <strong>{{ $adminSuperContext['special_user_name'] ?? 'Data user tidak ditemukan' }}</strong>

                                        <div class="super-context-special-meta">
                                            @if (!empty($adminSuperContext['special_user_jabatan']))
                                                <span>
                                                    <i class="fa-solid fa-briefcase"></i>
                                                    {{ $adminSuperContext['special_user_jabatan'] }}
                                                </span>
                                            @endif

                                            @if (!empty($adminSuperContext['special_user_nip']))
                                                <span>NIP: {{ $adminSuperContext['special_user_nip'] }}</span>
                                            @elseif (!empty($adminSuperContext['special_user_nik']))
                                                <span>NIK: {{ $adminSuperContext['special_user_nik'] }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="super-context-special-scope">
                                        <span>Scope User</span>
                                        <strong>{{ $adminSuperContext['special_user_instansi'] ?? '-' }}</strong>
                                        <small>{{ $adminSuperContext['special_user_unit_kerja'] ?? '-' }}</small>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if (session('status'))
            <div class="col-12 mb-4">
                <div class="status-panel status-panel--success p-4 text-white" role="alert" data-auto-hide="true">
                    <div class="d-flex align-items-start gap-3">
                        <div class="status-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <p class="text-uppercase text-xs fw-bold text-white opacity-8 mb-2">Perubahan Berhasil
                                    </p>
                                    <h5 class="text-white mb-2">Jabatan aktif sudah diperbarui</h5>
                                </div>
                                <button type="button" class="status-close" aria-label="Tutup notifikasi">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <p class="mb-0 text-white">{{ session('status') }}</p>

                            @if (session('active_role'))
                                <div class="status-meta">
                                    <span class="status-badge">
                                        <i class="fa-solid fa-user-tie"></i>{{ session('active_role') }}
                                    </span>
                                    @if (session('active_instansi'))
                                        <span class="status-badge">
                                            <i class="fa-solid fa-building"></i>{{ session('active_instansi') }}
                                        </span>
                                    @endif
                                    @if (session('active_unit_kerja'))
                                        <span class="status-badge">
                                            <i class="fa-solid fa-sitemap"></i>{{ session('active_unit_kerja') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="col-12 mb-4">
                <div class="status-panel status-panel--error p-4 text-white" role="alert" data-auto-hide="true">
                    <div class="d-flex align-items-start gap-3">
                        <div class="status-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <p class="text-uppercase text-xs fw-bold text-white opacity-8 mb-2">Perlu Dicek Lagi
                                    </p>
                                    <h5 class="text-white mb-2">Perubahan jabatan belum bisa diproses</h5>
                                </div>
                                <button type="button" class="status-close" aria-label="Tutup notifikasi">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <ul class="mb-0 ps-3 text-white">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @forelse ($positions as $position)
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card h-100 position-card {{ $position->is_active ? 'is-active' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <div
                                class="icon icon-shape bg-gradient-{{ $position->is_active ? 'success' : 'primary' }} shadow text-center border-radius-md">
                                <i class="fa-solid fa-briefcase text-white"></i>
                            </div>
                            <span
                                class="badge bg-gradient-{{ $position->is_active ? 'success' : 'secondary' }}">
                                {{ $position->is_active ? 'Sedang Aktif' : 'Tersedia' }}
                            </span>
                        </div>

                        <div class="mt-4">
                            <h5 class="mb-1">{{ $position->jabatan?->nama ?? 'Jabatan belum diatur' }}</h5>
                            <p class="text-sm text-secondary mb-0">
                                {{ $position->instansi?->nama ?? 'Instansi belum diatur' }}
                            </p>
                        </div>

                        <hr class="horizontal dark my-4">

                        <div class="position-meta">
                            <div class="position-meta-row">
                                <span>Unit Kerja</span>
                                <strong>{{ $position->unitKerja?->nama ?? '-' }}</strong>
                            </div>
                            <div class="position-meta-row">
                                <span>Instansi</span>
                                <strong>{{ $position->instansi?->nama ?? '-' }}</strong>
                            </div>
                            <div class="position-meta-row">
                                <span>Status</span>
                                <strong>{{ $position->is_active ? 'Sedang digunakan' : 'Siap dipakai' }}</strong>
                            </div>
                        </div>

                        <div class="mt-auto pt-4">
                            @if ($position->is_active)
                                @if ((int) $position->jabatan_id === 1)
                                    <a href="{{ route('login.post') }}" class="btn bg-gradient-success w-100 mb-0">
                                        <i class="fa-solid fa-circle-check me-2"></i>Jabatan Aktif
                                    </a>
                                @else
                                    <button class="btn bg-gradient-success w-100 mb-0" disabled>
                                        <i class="fa-solid fa-circle-check me-2"></i>Jabatan Aktif
                                    </button>
                                @endif
                            @else
                                <form method="POST" action="{{ route('positions.switch') }}">
                                    @csrf
                                    <input type="hidden" name="position_id" value="{{ $position->getRouteKey() }}">
                                    <button type="submit" class="btn bg-gradient-primary w-100 mb-0">
                                        <i class="fa-solid fa-repeat me-2"></i>Pakai Jabatan Ini
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card empty-state">
                    <div class="card-body py-5 text-center">
                        <i class="fa-solid fa-circle-exclamation text-warning fa-2x mb-3"></i>
                        <h5 class="mb-2">Belum ada jabatan yang tersedia</h5>
                        <p class="text-sm text-secondary mb-0">
                            Akun ini belum memiliki posisi aktif yang bisa dipilih. Silakan hubungi admin.
                        </p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection

@section('additionals')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hidePanel = function(panel) {
                if (!panel || panel.classList.contains('is-hiding')) {
                    return;
                }

                panel.classList.add('is-hiding');

                window.setTimeout(function() {
                    panel.remove();
                }, 500);
            };

            document.querySelectorAll('.status-panel').forEach(function(panel) {
                const closeButton = panel.querySelector('.status-close');

                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        hidePanel(panel);
                    });
                }

                if (panel.dataset.autoHide === 'true') {
                    window.setTimeout(function() {
                        hidePanel(panel);
                    }, 5000);
                }
            });
        });
    </script>
@endsection
