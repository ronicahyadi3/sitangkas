@php
    $argonCssVersion = file_exists(public_path('assets/css/argon-dashboard.css'))
        ? filemtime(public_path('assets/css/argon-dashboard.css'))
        : '2.0.7';

    $argonJsVersion = file_exists(public_path('assets/js/argon-dashboard.js'))
        ? filemtime(public_path('assets/js/argon-dashboard.js'))
        : '2.0.5';

    $additionalsJsVersion = file_exists(public_path('assets/js/additionals.js'))
        ? filemtime(public_path('assets/js/additionals.js'))
        : '0.3';

    $layoutContext = auth()->check()
        ? app(\App\Services\Auth\CurrentUserContext::class)->snapshot(request())
        : null;

    $layoutCanWrite = true;
    $layoutIsReadOnly = $layoutContext !== null && !$layoutCanWrite;
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Aplikasi Tanda Tangan Keuangan dan Aset">
    <meta name="author" content="Pemerintah Kota Malang">
    <title>@yield('title', 'SITANGKAS')</title>
    <link rel="icon" href="{{ asset('assets/img/Logo_Kota_Malang_color.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://code.jquery.com" crossorigin>
    <link rel="preconnect" href="https://cdn.datatables.net" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">

    <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/nucleo-svg.css') }}" rel="stylesheet">
    <link id="pagestyle" href="{{ asset('assets/css/argon-dashboard.css') }}?v={{ $argonCssVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.6/css/responsive.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/3.0.1/css/select.dataTables.min.css">
    <link rel="stylesheet" href="https://unpkg.com/wenk/dist/wenk.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@eonasdan/tempus-dominus@6.9.4/dist/css/tempus-dominus.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <script>
        (function() {
            try {
                if (localStorage.getItem('darkMode') === 'enabled') {
                    document.documentElement.classList.add('app-dark-mode-pending');
                }
            } catch (error) {}
        })();
    </script>

    <style>
        html.app-dark-mode-pending body {
            background-color: #1f283e;
        }

        body.app-layout-booting,
        body.app-layout-booting * {
            animation: none !important;
            transition: none !important;
        }

        .toggle-password {
            cursor: pointer;
            user-select: none;
        }

        .app-layout-feedback {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin: 0 1.5rem 1.25rem;
            padding: 1rem 1.1rem;
            border-radius: 1rem;
            border: 1px solid rgba(245, 158, 11, 0.24);
            background: linear-gradient(135deg, rgba(255, 247, 237, 0.98) 0%, rgba(255, 251, 235, 0.98) 100%);
            color: #9a3412;
            box-shadow: 0 16px 30px rgba(245, 158, 11, 0.08);
        }

        .app-layout-feedback__title {
            margin-bottom: .2rem;
            font-size: .95rem;
            font-weight: 700;
        }

        .app-layout-feedback__desc {
            margin-bottom: 0;
            color: #9a3412;
            font-size: .88rem;
            line-height: 1.55;
        }

        .app-layout-feedback__pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(245, 158, 11, 0.18);
            color: #92400e;
            font-size: .82rem;
            font-weight: 700;
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .app-layout-feedback {
                flex-direction: column;
                margin-inline: 1rem;
            }
        }
    </style>
    @yield('styling')
    @include('inc.dark-mode-overrides')
</head>

<body class="g-sidenav-show bg-gray-100 g-sidenav-hidden app-layout-booting"
    data-year-readonly="{{ $layoutIsReadOnly ? '1' : '0' }}">
    <script>
        (function() {
            try {
                if (document.documentElement.classList.contains('app-dark-mode-pending')) {
                    document.body.classList.add('dark-version');
                }
            } catch (error) {}
        })();
    </script>

    <div class="min-height-300 bg-primary position-absolute w-100"></div>

    @auth
        @include('inc.sidebar')
    @endauth

    <main class="main-content position-relative border-radius-lg">
        @include('inc.navbar')

        @if (session('auth_context_feedback'))
            <div class="app-layout-feedback" id="authContextFeedbackBanner">
                <div>
                    <div class="app-layout-feedback__title">Akses Ditolak</div>
                    <p class="app-layout-feedback__desc">
                        {{ session('auth_context_feedback.message') }}
                    </p>
                </div>
                <div class="app-layout-feedback__pill">
                    <i class="fa fa-ban"></i> Konteks
                </div>
            </div>
        @endif

        <div class="container-fluid py-4">
            @yield('content')
            @include('inc.footer')
        </div>
    </main>

    @include('inc.rightbar')

    @auth
        <div id="esign-app-root" data-esign-app-root></div>
    @endauth

    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/smooth-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/argon-dashboard.js') }}?v={{ $argonJsVersion }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.0.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.6/js/dataTables.responsive.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.6/js/responsive.bootstrap5.js"></script>
    <script src="https://cdn.datatables.net/select/3.0.1/js/dataTables.select.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.0.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@eonasdan/tempus-dominus@6.9.4/dist/js/tempus-dominus.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/autonumeric/4.10.9/autoNumeric.min.js"></script>
    <script src="{{ asset('assets/js/additionals.js') }}?v={{ $additionalsJsVersion }}"></script>

    @if ($layoutContext)
        <script>
            window.currentUserContext = @json($layoutContext);
        </script>
    @endif

    @if (session('auth_context_feedback'))
        <script>
            window.authContextFeedback = @json(session('auth_context_feedback'));
        </script>
    @endif

    @auth
        @php
            $disableRealtimePresence = request()->routeIs(
                'positions.*',
                'login.context.*',
                'login.mfa',
                'login.mfa.store',
                'login.mfa.setup',
                'login.mfa.setup.store',
                'login.no_active_position',
                'login.post',
                'login.post.store',
                'login.post.options.*',
                'password.change',
                'password.change.save'
            );
        @endphp

        @if (!$disableRealtimePresence && \Illuminate\Support\Facades\Route::has('realtime.presence.heartbeat') && \Illuminate\Support\Facades\Route::has('realtime.presence.leave'))
            <script>
                window.sitangkasRealtime = {
                    heartbeatUrl: @json(route('realtime.presence.heartbeat')),
                    leaveUrl: @json(route('realtime.presence.leave')),
                };

                window.sitangkasRealtimeUser = {
                    id: @json(auth()->id()),
                };
            </script>
        @endif
    @endauth

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.authContextFeedback) {
                if (typeof notification === 'function') {
                    notification(window.authContextFeedback);
                    return;
                }

                if (window.Swal) {
                    Swal.fire('Akses Ditolak', window.authContextFeedback.message || 'Akses ditolak.', 'error');
                }
            }
        });
    </script>

    @yield('additionals')
    @vite('resources/js/app.js')
    @stack('scripts')
</body>

</html>
