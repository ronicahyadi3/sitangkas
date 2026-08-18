@php
    $initialSummary = $presenceState['summary'] ?? [];
    $initialSessions = $presenceState['sessions'] ?? [];
@endphp

@extends('layouts.app')

@section('title', 'Online Monitoring - SITANGKAS')

@section('styling')
    <style>
        .realtime-page {
            color: #0f172a;
        }

        .realtime-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid rgba(100, 116, 139, .14);
            background: #fff;
            box-shadow: 0 12px 28px rgba(15, 47, 87, .06);
        }

        .realtime-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .55rem;
            color: #1b4f8c;
            font-size: .76rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .realtime-title {
            margin-bottom: .25rem;
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .realtime-subtitle {
            margin-bottom: 0;
            color: #64748b;
            font-size: .86rem;
            line-height: 1.6;
        }

        .realtime-connection {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            min-height: 36px;
            padding: .45rem .75rem;
            border-radius: 999px;
            background: rgba(100, 116, 139, .1);
            color: #475569;
            font-size: .78rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .realtime-connection.is-connected {
            background: rgba(34, 181, 115, .12);
            color: #168052;
        }

        .realtime-connection.is-error {
            background: rgba(239, 68, 68, .12);
            color: #b91c1c;
        }

        .realtime-summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .75rem;
            margin: 1rem 0;
        }

        .realtime-summary-item {
            min-height: 92px;
            padding: .9rem;
            border-radius: 8px;
            border: 1px solid rgba(100, 116, 139, .14);
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 47, 87, .04);
        }

        .realtime-summary-label {
            margin-bottom: .35rem;
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .realtime-summary-value {
            color: #1b4f8c;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .realtime-table-panel {
            border: 1px solid rgba(100, 116, 139, .14);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 28px rgba(15, 47, 87, .05);
            overflow: hidden;
        }

        .realtime-table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .9rem 1rem;
            border-bottom: 1px solid rgba(100, 116, 139, .14);
        }

        .realtime-table-title {
            margin-bottom: .1rem;
            color: #0f172a;
            font-size: .95rem;
            font-weight: 800;
        }

        .realtime-table-note {
            margin-bottom: 0;
            color: #64748b;
            font-size: .76rem;
        }

        .realtime-refresh {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .realtime-status {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .55rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .realtime-status--active {
            background: rgba(34, 181, 115, .12);
            color: #168052;
        }

        .realtime-status--idle {
            background: rgba(245, 158, 11, .14);
            color: #92400e;
        }

        .realtime-status--away {
            background: rgba(47, 141, 243, .12);
            color: #1b4f8c;
        }

        .realtime-status--offline {
            background: rgba(100, 116, 139, .13);
            color: #475569;
        }

        .realtime-user-cell {
            min-width: 190px;
        }

        .realtime-user-name {
            color: #0f172a;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .realtime-user-meta,
        .realtime-muted {
            color: #64748b;
            font-size: .75rem;
            line-height: 1.45;
        }

        .realtime-empty {
            padding: 2rem 1rem;
            color: #64748b;
            text-align: center;
        }

        body.dark-version .realtime-page,
        body.dark-version .realtime-title,
        body.dark-version .realtime-table-title,
        body.dark-version .realtime-user-name {
            color: var(--app-dark-heading);
        }

        body.dark-version .realtime-header,
        body.dark-version .realtime-summary-item,
        body.dark-version .realtime-table-panel {
            background: var(--app-dark-surface) !important;
            border-color: var(--app-dark-border) !important;
        }

        body.dark-version .realtime-subtitle,
        body.dark-version .realtime-summary-label,
        body.dark-version .realtime-table-note,
        body.dark-version .realtime-user-meta,
        body.dark-version .realtime-muted,
        body.dark-version .realtime-empty {
            color: var(--app-dark-muted);
        }

        body.dark-version .realtime-table-toolbar {
            border-color: var(--app-dark-border);
        }

        @media (max-width: 1199.98px) {
            .realtime-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .realtime-header,
            .realtime-table-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .realtime-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
@endsection

@section('content')
    <div class="realtime-page">
        <section class="realtime-header">
            <div>
                <div class="realtime-eyebrow">
                    <i class="fa-solid fa-tower-broadcast"></i>
                    Realtime monitoring
                </div>
                <h1 class="realtime-title">Online Monitoring</h1>
                <p class="realtime-subtitle">
                    Pantau user yang benar-benar hadir secara realtime. Session yang masih aktif tidak otomatis dianggap online.
                </p>
            </div>

            <span class="realtime-connection" id="onlinePresenceConnection">
                <i class="fa-solid fa-circle-nodes"></i>
                Menghubungkan
            </span>
        </section>

        <section class="realtime-summary" aria-label="Ringkasan online monitoring">
            <div class="realtime-summary-item">
                <div class="realtime-summary-label">Online</div>
                <div class="realtime-summary-value" data-presence-summary="online">{{ $initialSummary['online'] ?? 0 }}</div>
            </div>
            <div class="realtime-summary-item">
                <div class="realtime-summary-label">Active</div>
                <div class="realtime-summary-value" data-presence-summary="active">{{ $initialSummary['active'] ?? 0 }}</div>
            </div>
            <div class="realtime-summary-item">
                <div class="realtime-summary-label">Idle</div>
                <div class="realtime-summary-value" data-presence-summary="idle">{{ $initialSummary['idle'] ?? 0 }}</div>
            </div>
            <div class="realtime-summary-item">
                <div class="realtime-summary-label">Away</div>
                <div class="realtime-summary-value" data-presence-summary="away">{{ $initialSummary['away'] ?? 0 }}</div>
            </div>
            <div class="realtime-summary-item">
                <div class="realtime-summary-label">Offline Baru</div>
                <div class="realtime-summary-value" data-presence-summary="offline">{{ $initialSummary['offline'] ?? 0 }}</div>
            </div>
        </section>

        <section class="realtime-table-panel">
            <div class="realtime-table-toolbar">
                <div>
                    <h2 class="realtime-table-title">User Presence</h2>
                    <p class="realtime-table-note">
                        Heartbeat dikirim tiap 30 detik. User stale akan berubah offline setelah timeout.
                    </p>
                </div>
                <button type="button" class="btn btn-outline-primary realtime-refresh" id="onlinePresenceRefresh" title="Refresh">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-items-center mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Koneksi</th>
                            <th>Device</th>
                            <th>IP</th>
                            <th>Last Seen</th>
                            <th>Konteks</th>
                        </tr>
                    </thead>
                    <tbody id="onlinePresenceRows">
                        @forelse ($initialSessions as $session)
                            <tr>
                                <td class="realtime-user-cell">
                                    <div class="realtime-user-name">{{ $session['user_name'] ?? '-' }}</div>
                                    <div class="realtime-user-meta">ID: {{ $session['user_id'] ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="realtime-status realtime-status--{{ $session['status'] ?? 'offline' }}">
                                        {{ strtoupper($session['status'] ?? 'offline') }}
                                    </span>
                                </td>
                                <td class="realtime-muted">{{ $session['connection_count'] ?? 0 }}</td>
                                <td class="realtime-muted">
                                    {{ $session['browser_name'] ?? '-' }}
                                    @if (! empty($session['platform_name']))
                                        <br>{{ $session['platform_name'] }}
                                    @endif
                                </td>
                                <td class="realtime-muted">{{ $session['ip_address'] ?? '-' }}</td>
                                <td class="realtime-muted">{{ $session['last_seen_at'] ?? '-' }}</td>
                                <td class="realtime-muted">
                                    {{ data_get($session, 'position.nama_jabatan', '-') }}<br>
                                    {{ data_get($session, 'position.nama_unit_kerja', '-') }}
                                </td>
                            </tr>
                        @empty
                            <tr data-presence-empty>
                                <td colspan="7">
                                    <div class="realtime-empty">Belum ada heartbeat user yang tercatat.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@section('additionals')
    <script>
        window.sitangkasRealtimeAdmin = {
            stateUrl: @json(route('admin.realtime.online-users.state')),
            channel: 'admin.online-users',
        };
    </script>
@endsection
