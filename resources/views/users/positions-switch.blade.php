@extends('layouts.app')

@section('title', 'Pilih Posisi Kerja - SITANGKAS')

@section('styling')
    <style>
        .switch-hero {
            border: 1px solid rgba(17, 24, 39, 0.06);
            background: linear-gradient(135deg, rgb(239 255 239 / 90%), rgba(17, 205, 239, 0.9));
            box-shadow: 0 1rem 2rem rgba(17, 24, 39, 0.06);
        }

        .position-card {
            border: 1px solid rgba(17, 24, 39, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .position-card:hover {
            transform: translateY(-2px);
            border-color: rgba(94, 114, 228, 0.2);
            box-shadow: 0 1rem 2rem rgba(17, 24, 39, 0.08);
        }

        .position-card.is-current {
            border-color: rgba(45, 206, 137, 0.42);
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
            max-width: 65%;
            color: #344767;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .position-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            max-width: 100%;
            padding: 0.5rem 0.875rem;
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.85);
            color: #344767;
            font-size: 0.875rem;
        }

        .position-chip span,
        .position-chip strong {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-panel {
            position: relative;
            max-height: 320px;
            overflow: hidden;
            border: 1px solid transparent;
            border-radius: 1rem;
            transform-origin: top center;
            animation: statusReveal 0.28s ease;
            transition: transform 0.45s ease, opacity 0.45s ease, max-height 0.45s ease, margin 0.45s ease,
                padding 0.45s ease;
        }

        .min-width-0 {
            min-width: 0;
        }

        .status-panel--success {
            border-color: rgba(23, 173, 55, 0.35);
            background: linear-gradient(135deg, #17ad37, #29b85a);
            box-shadow: 0 1.25rem 2.5rem rgba(23, 173, 55, 0.18);
        }

        .status-panel--error {
            border-color: rgba(234, 6, 6, 0.35);
            background: linear-gradient(135deg, #ea0606, #ff5b5b);
            box-shadow: 0 1.25rem 2.5rem rgba(234, 6, 6, 0.18);
        }

        .status-icon {
            width: 3rem;
            height: 3rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 3rem;
            border-radius: 0.9rem;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        .status-close {
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 2rem;
            border: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .acting-context-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(94, 114, 228, 0.18);
            box-shadow: 0 1rem 2rem rgba(17, 24, 39, 0.06);
        }

        .acting-context-card::before {
            position: absolute;
            inset: 0 0 auto;
            height: 4px;
            background: linear-gradient(90deg, #5e72e4, #11cdef, #2dce89);
            content: '';
        }

        .acting-context-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .acting-context-item {
            min-width: 0;
            padding: 0.85rem;
            border: 1px solid rgba(131, 146, 171, 0.18);
            border-radius: 0.9rem;
            background: #f8f9fa;
        }

        .acting-context-item span {
            display: block;
            margin-bottom: 0.25rem;
            color: #8392ab;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .acting-context-item strong {
            display: block;
            color: #344767;
            font-size: 0.92rem;
            overflow-wrap: anywhere;
        }

        .empty-state {
            border: 1px dashed rgba(131, 146, 171, 0.5);
        }

        .status-panel.is-hiding {
            max-height: 0;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            border-width: 0;
            opacity: 0;
            transform: translateY(-18px);
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

        @media (max-width: 991.98px) {
            .acting-context-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .switch-hero .card-body,
            .position-card .card-body {
                padding: 1.25rem !important;
            }

            .acting-context-grid {
                grid-template-columns: 1fr;
            }

            .position-meta-row {
                flex-direction: column;
                gap: 0.25rem;
            }

            .position-meta-row strong {
                max-width: 100%;
                text-align: left;
            }
        }
    </style>
@endsection

@section('content')
    @php
        $hasActivePosition = filled($activeUserPositionId);
        $isActingContext = (bool) ($contextSnapshot['is_acting_context'] ?? false);
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="card switch-hero mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <span class="badge bg-gradient-primary mb-3">Pengaturan Akun</span>
                            <h2 class="mb-2">{{ $hasActivePosition ? 'Ganti Posisi Kerja' : 'Pilih Posisi Kerja' }}</h2>
                            <p class="text-sm text-secondary mb-0">
                                Pilih posisi yang ingin Anda gunakan. Pilihan ini menentukan jabatan, instansi, unit
                                kerja, menu, dan akses dokumen untuk sesi saat ini.
                            </p>
                        </div>

                        @if ($hasActivePosition)
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-primary mb-0">
                                <i class="fa-solid fa-arrow-left me-2"></i>Kembali ke Dashboard
                            </a>
                        @endif
                    </div>

                    @if ($activePosition)
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <div class="position-chip">
                                <i class="fa-solid fa-user-check text-success"></i>
                                <strong>{{ $activePosition->jabatan?->nama ?? 'Jabatan' }}</strong>
                            </div>
                            <div class="position-chip">
                                <i class="fa-solid fa-building text-info"></i>
                                <span>{{ $activePosition->instansi?->nama ?? 'Instansi belum diatur' }}</span>
                            </div>
                            <div class="position-chip">
                                <i class="fa-solid fa-sitemap text-warning"></i>
                                <span>{{ $activePosition->unitKerja?->nama ?? 'Unit kerja belum diatur' }}</span>
                            </div>
                            <div class="position-chip">
                                <i class="fa-solid fa-calendar text-primary"></i>
                                <span>Tahun {{ $activeYear ?? now()->year }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($isActingContext)
            <div class="col-12">
                <div class="card acting-context-card super-context-card mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                    <i class="fa-solid fa-user-shield text-white"></i>
                                </div>
                                <div>
                                    <span class="text-xs text-uppercase text-secondary fw-bold">Mode Admin Super</span>
                                    <h5 class="mb-1">Acting Context Saat Ini</h5>
                                    <p class="text-sm text-secondary mb-0">
                                        Konteks ini sedang digunakan untuk dashboard dan akses dokumen.
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('login.post') }}" class="btn btn-outline-primary mb-0">
                                <i class="fa-solid fa-sliders me-2"></i>Ubah Acting Context
                            </a>
                        </div>

                        <div class="acting-context-grid mt-4">
                            <div class="acting-context-item super-context-item">
                                <span>Jabatan</span>
                                <strong>{{ $contextSnapshot['nama_jabatan'] ?? '-' }}</strong>
                            </div>
                            <div class="acting-context-item super-context-item">
                                <span>Instansi</span>
                                <strong>{{ $contextSnapshot['nama_instansi'] ?? '-' }}</strong>
                            </div>
                            <div class="acting-context-item super-context-item">
                                <span>Unit Kerja</span>
                                <strong>{{ $contextSnapshot['nama_unit_kerja'] ?? '-' }}</strong>
                            </div>
                            <div class="acting-context-item super-context-item">
                                <span>Tahun Aktif</span>
                                <strong>{{ $contextSnapshot['tahun_aktif'] ?? $activeYear ?? now()->year }}</strong>
                            </div>
                        </div>
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
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <p class="text-uppercase text-xs fw-bold text-white opacity-8 mb-2">Informasi Posisi</p>
                                    <h5 class="text-white mb-2">Status pemilihan posisi</h5>
                                </div>
                                <button type="button" class="status-close" aria-label="Tutup notifikasi">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <p class="mb-0 text-white">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="col-12 mb-4">
                <div class="status-panel status-panel--error p-4 text-white" role="alert">
                    <div class="d-flex align-items-start gap-3">
                        <div class="status-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <p class="text-uppercase text-xs fw-bold text-white opacity-8 mb-2">Perlu Dicek</p>
                                    <h5 class="text-white mb-2">Posisi belum dapat dipilih</h5>
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
            @php
                $isCurrent = (string) $activeUserPositionId === (string) $position->getKey();
            @endphp

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card h-100 position-card {{ $isCurrent ? 'is-current is-active' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="icon icon-shape bg-gradient-{{ $isCurrent ? 'success' : 'primary' }} shadow text-center border-radius-md">
                                <i class="fa-solid fa-briefcase text-white"></i>
                            </div>
                            <span class="badge bg-gradient-{{ $isCurrent ? 'success' : 'secondary' }}">
                                {{ $isCurrent ? 'Sedang Aktif' : 'Tersedia' }}
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
                                <span>Terakhir Dipakai</span>
                                <strong>{{ $position->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</strong>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('positions.store') }}" class="mt-auto pt-4">
                            @csrf
                            <input type="hidden" name="user_position_id" value="{{ $position->getKey() }}">
                            <button type="submit" class="btn bg-gradient-{{ $isCurrent ? 'success' : 'primary' }} w-100 mb-0">
                                <i class="fa-solid fa-{{ $isCurrent ? 'circle-check' : 'repeat' }} me-2"></i>
                                {{ $isCurrent ? 'Lanjutkan dengan Posisi Ini' : 'Pakai Posisi Ini' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card empty-state">
                    <div class="card-body py-5 text-center">
                        <i class="fa-solid fa-circle-exclamation text-warning fa-2x mb-3"></i>
                        <h5 class="mb-2">Belum ada posisi yang tersedia</h5>
                        <p class="text-sm text-secondary mb-0">
                            Akun ini belum memiliki posisi aktif yang dapat digunakan. Silakan hubungi admin.
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
