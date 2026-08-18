@php
    use Illuminate\Support\Js;

    $eventLabels = $eventTypes;
    $resultLabels = $resultTypes;
    $resourceLabels = $resourceTypes;

    $auditDetails = $events->getCollection()
        ->mapWithKeys(function ($event) use ($eventLabels, $resultLabels, $resourceLabels) {
            return [
                $event->id => [
                    'event_uuid' => $event->event_uuid,
                    'event_type' => $event->event_type,
                    'event_label' => $eventLabels[$event->event_type] ?? $event->event_type,
                    'result' => $event->result,
                    'result_label' => $resultLabels[$event->result] ?? $event->result,
                    'actor' => [
                        'nama' => $event->actor?->nama,
                        'nik' => $event->actor?->nik,
                        'email' => $event->actor?->email,
                    ],
                    'actor_position' => [
                        'jabatan' => $event->actorPosition?->jabatan?->nama,
                        'instansi' => $event->actorPosition?->instansi?->nama_singkat ?: $event->actorPosition?->instansi?->nama,
                        'unit_kerja' => $event->actorPosition?->unitKerja?->nama_singkat ?: $event->actorPosition?->unitKerja?->nama,
                    ],
                    'target_user' => [
                        'nama' => $event->targetUser?->nama,
                        'nik' => $event->targetUser?->nik,
                        'email' => $event->targetUser?->email,
                    ],
                    'target_position' => [
                        'jabatan' => $event->targetPosition?->jabatan?->nama,
                        'instansi' => $event->targetPosition?->instansi?->nama_singkat ?: $event->targetPosition?->instansi?->nama,
                        'unit_kerja' => $event->targetPosition?->unitKerja?->nama_singkat ?: $event->targetPosition?->unitKerja?->nama,
                    ],
                    'resource' => [
                        'type' => $resourceLabels[$event->resource_type] ?? $event->resource_type,
                        'id' => $event->resource_id,
                    ],
                    'reason' => $event->reason,
                    'message' => $event->message,
                    'changed_fields' => $event->changed_fields,
                    'before_state' => $event->before_state,
                    'after_state' => $event->after_state,
                    'metadata' => $event->metadata,
                    'request' => [
                        'request_id' => $event->request_id,
                        'route_name' => $event->route_name,
                        'request_path' => $event->request_path,
                        'http_method' => $event->http_method,
                        'http_status' => $event->http_status,
                        'ip_address' => $event->ip_address,
                        'user_agent' => $event->user_agent,
                    ],
                    'occurred_at' => $event->occurred_at?->format('d/m/Y H:i:s'),
                ],
            ];
        })
        ->all();

    $resultBadgeClass = [
        'success' => 'audit-badge audit-badge--success',
        'failed' => 'audit-badge audit-badge--failed',
        'blocked' => 'audit-badge audit-badge--blocked',
    ];
@endphp

@extends('layouts.app')

@section('title', 'Audit Trail Management Users - SITANGKAS')

@section('styling')
    <style>
        .audit-page {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            color: #0f172a;
        }

        .audit-header,
        .audit-filter,
        .audit-panel {
            border: 1px solid rgba(100, 116, 139, .14);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 28px rgba(15, 47, 87, .05);
        }

        .audit-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
        }

        .audit-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .55rem;
            color: #1b4f8c;
            font-size: .76rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .audit-title {
            margin-bottom: .25rem;
            color: #0f172a;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .audit-subtitle {
            margin-bottom: 0;
            color: #64748b;
            font-size: .86rem;
            line-height: 1.6;
        }

        .audit-scope-pill,
        .audit-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .audit-scope-pill {
            padding: .48rem .78rem;
            color: #1b4f8c;
            background: rgba(47, 141, 243, .1);
            border: 1px solid rgba(47, 141, 243, .14);
        }

        .audit-filter {
            padding: 1rem;
        }

        .audit-filter label {
            margin-bottom: .35rem;
            color: #475569;
            font-size: .75rem;
            font-weight: 800;
        }

        .audit-filter .form-control,
        .audit-filter .form-select {
            border-radius: 8px;
            font-size: .86rem;
        }

        .audit-panel-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1rem;
            border-bottom: 1px solid rgba(100, 116, 139, .14);
        }

        .audit-panel-title {
            margin-bottom: .1rem;
            color: #0f172a;
            font-size: .95rem;
            font-weight: 800;
        }

        .audit-panel-note {
            margin-bottom: 0;
            color: #64748b;
            font-size: .76rem;
        }

        .audit-table th {
            color: #475569;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .audit-table td {
            vertical-align: middle;
        }

        .audit-event-title {
            color: #0f172a;
            font-weight: 800;
        }

        .audit-muted,
        .audit-meta {
            color: #64748b;
            font-size: .76rem;
            line-height: 1.45;
        }

        .audit-badge {
            padding: .32rem .58rem;
        }

        .audit-badge--success {
            color: #168052;
            background: rgba(34, 181, 115, .12);
        }

        .audit-badge--failed {
            color: #b91c1c;
            background: rgba(239, 68, 68, .12);
        }

        .audit-badge--blocked {
            color: #92400e;
            background: rgba(245, 158, 11, .14);
        }

        .audit-detail-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .audit-empty {
            padding: 2rem 1rem;
            color: #64748b;
            text-align: center;
        }

        .audit-json {
            min-height: 180px;
            max-height: 420px;
            margin: 0;
            padding: .9rem;
            overflow: auto;
            border-radius: 8px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: .78rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .audit-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .audit-detail-item {
            padding: .75rem;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid rgba(100, 116, 139, .12);
        }

        .audit-detail-label {
            margin-bottom: .2rem;
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .audit-detail-value {
            color: #0f172a;
            font-size: .86rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        body.dark-version .audit-page,
        body.dark-version .audit-title,
        body.dark-version .audit-panel-title,
        body.dark-version .audit-event-title,
        body.dark-version .audit-detail-value {
            color: var(--app-dark-heading);
        }

        body.dark-version .audit-header,
        body.dark-version .audit-filter,
        body.dark-version .audit-panel,
        body.dark-version .modal-content {
            background: var(--app-dark-surface) !important;
            border-color: var(--app-dark-border) !important;
        }

        body.dark-version .audit-subtitle,
        body.dark-version .audit-panel-note,
        body.dark-version .audit-muted,
        body.dark-version .audit-meta,
        body.dark-version .audit-empty,
        body.dark-version .audit-detail-label,
        body.dark-version .audit-filter label {
            color: var(--app-dark-muted);
        }

        body.dark-version .audit-detail-item {
            background: rgba(15, 23, 42, .52);
            border-color: var(--app-dark-border);
        }

        body.dark-version .audit-panel-toolbar {
            border-color: var(--app-dark-border);
        }

        @media (max-width: 991.98px) {
            .audit-header,
            .audit-panel-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .audit-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content')
    <div class="audit-page">
        <section class="audit-header">
            <div>
                <div class="audit-eyebrow">
                    <i class="fa-solid fa-clipboard-list"></i>
                    Management users
                </div>
                <h1 class="audit-title">Audit Trail</h1>
                <p class="audit-subtitle">
                    Riwayat aksi administrasi user, posisi, izin tahun historis, dan keamanan akun.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <span class="audit-scope-pill">
                    <i class="fa-solid fa-user-shield"></i>
                    {{ $isFullAdmin ? 'Admin Super' : $scopeLabel }}
                </span>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i>Users
                </a>
            </div>
        </section>

        <form class="audit-filter" method="GET" action="{{ route('users.audit-trail') }}">
            <div class="row g-3">
                <div class="col-md-3 col-lg-2">
                    <label for="date_from">Dari</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="date_to">Sampai</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </div>
                <div class="col-md-6 col-lg-3">
                    <label for="event_type">Event</label>
                    <select id="event_type" name="event_type" class="form-select">
                        <option value="">Semua event</option>
                        @foreach ($eventTypes as $value => $label)
                            <option value="{{ $value }}" @selected($filters['event_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label for="result">Result</label>
                    <select id="result" name="result" class="form-select">
                        <option value="">Semua result</option>
                        @foreach ($resultTypes as $value => $label)
                            <option value="{{ $value }}" @selected($filters['result'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-3">
                    <label for="resource_type">Resource</label>
                    <select id="resource_type" name="resource_type" class="form-select">
                        <option value="">Semua resource</option>
                        @foreach ($resourceTypes as $value => $label)
                            <option value="{{ $value }}" @selected($filters['resource_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label for="actor_user_id">Actor ID</label>
                    <input type="number" min="1" id="actor_user_id" name="actor_user_id" value="{{ $filters['actor_user_id'] }}" class="form-control">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label for="target_user_id">Target ID</label>
                    <input type="number" min="1" id="target_user_id" name="target_user_id" value="{{ $filters['target_user_id'] }}" class="form-control">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label for="ip_address">IP</label>
                    <input type="text" id="ip_address" name="ip_address" value="{{ $filters['ip_address'] }}" class="form-control" placeholder="127.0.0.1">
                </div>
                <div class="col-md-8 col-lg-4">
                    <label for="search">Search</label>
                    <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="UUID, request, message, reason">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label for="per_page">Rows</label>
                    <select id="per_page" name="per_page" class="form-select">
                        @foreach ([15, 25, 50, 100] as $perPage)
                            <option value="{{ $perPage }}" @selected($filters['per_page'] === $perPage)>{{ $perPage }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 col-lg-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary mb-0">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('users.audit-trail') }}" class="btn btn-outline-secondary mb-0">
                        <i class="fa-solid fa-rotate-left me-1"></i>Reset
                    </a>
                </div>
            </div>
        </form>

        <section class="audit-panel">
            <div class="audit-panel-toolbar">
                <div>
                    <h2 class="audit-panel-title">Event Log</h2>
                    <p class="audit-panel-note">
                        {{ number_format($events->total()) }} event ditemukan.
                    </p>
                </div>
                <div class="audit-muted">
                    {{ $events->firstItem() ?? 0 }}-{{ $events->lastItem() ?? 0 }} dari {{ $events->total() }}
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-items-center mb-0 audit-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Event</th>
                            <th>Actor</th>
                            <th>Target</th>
                            <th>Request</th>
                            <th class="text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td>
                                    <div class="audit-event-title">{{ $event->occurred_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                    <div class="audit-meta">{{ $event->event_uuid }}</div>
                                </td>
                                <td>
                                    <div class="audit-event-title">{{ $eventLabels[$event->event_type] ?? $event->event_type }}</div>
                                    <span class="{{ $resultBadgeClass[$event->result] ?? 'audit-badge' }}">
                                        {{ $resultLabels[$event->result] ?? $event->result }}
                                    </span>
                                    @if ($event->message)
                                        <div class="audit-meta mt-1">{{ $event->message }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="audit-event-title">{{ $event->actor?->nama ?? 'System' }}</div>
                                    <div class="audit-meta">
                                        {{ $event->actorPosition?->jabatan?->nama ?? '-' }}
                                        @if ($event->actorPosition?->unitKerja)
                                            <br>{{ $event->actorPosition->unitKerja->nama_singkat ?: $event->actorPosition->unitKerja->nama }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="audit-event-title">{{ $event->targetUser?->nama ?? '-' }}</div>
                                    <div class="audit-meta">
                                        {{ $event->targetPosition?->jabatan?->nama ?? ($resourceLabels[$event->resource_type] ?? $event->resource_type ?? '-') }}
                                        @if ($event->targetPosition?->unitKerja)
                                            <br>{{ $event->targetPosition->unitKerja->nama_singkat ?: $event->targetPosition->unitKerja->nama }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="audit-event-title">{{ $event->http_method ?? '-' }} {{ $event->http_status ?? '' }}</div>
                                    <div class="audit-meta">
                                        {{ $event->route_name ?? $event->request_path ?? '-' }}
                                        @if ($event->ip_address)
                                            <br>{{ $event->ip_address }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-primary btn-sm audit-detail-btn" data-audit-id="{{ $event->id }}" title="Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="audit-empty">Belum ada audit event yang sesuai filter.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="p-3 border-top">
                    {{ $events->links() }}
                </div>
            @endif
        </section>
    </div>

    <div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="auditDetailTitle">Audit Detail</h5>
                        <p class="audit-muted mb-0" id="auditDetailSubtitle"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="audit-detail-grid" id="auditDetailGrid"></div>

                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#auditBeforeState" type="button" role="tab">Before</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#auditAfterState" type="button" role="tab">After</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#auditMetadata" type="button" role="tab">Metadata</button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="auditBeforeState" role="tabpanel">
                            <pre class="audit-json" id="auditBeforeJson"></pre>
                        </div>
                        <div class="tab-pane fade" id="auditAfterState" role="tabpanel">
                            <pre class="audit-json" id="auditAfterJson"></pre>
                        </div>
                        <div class="tab-pane fade" id="auditMetadata" role="tabpanel">
                            <pre class="audit-json" id="auditMetadataJson"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('additionals')
    <script>
        window.userManagementAuditDetails = {{ Js::from($auditDetails) }};

        document.addEventListener('DOMContentLoaded', function() {
            const modalElement = document.getElementById('auditDetailModal');
            const modal = new bootstrap.Modal(modalElement);
            const details = window.userManagementAuditDetails || {};

            const formatJson = function(value) {
                if (value === null || value === undefined) {
                    return '-';
                }

                return JSON.stringify(value, null, 2);
            };

            const detailItem = function(label, value) {
                return `
                    <div class="audit-detail-item">
                        <div class="audit-detail-label">${label}</div>
                        <div class="audit-detail-value">${value || '-'}</div>
                    </div>
                `;
            };

            document.querySelectorAll('[data-audit-id]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const event = details[this.dataset.auditId];

                    if (!event) {
                        return;
                    }

                    document.getElementById('auditDetailTitle').textContent = event.event_label || event.event_type || 'Audit Detail';
                    document.getElementById('auditDetailSubtitle').textContent = `${event.occurred_at || '-'} | ${event.event_uuid || '-'}`;

                    document.getElementById('auditDetailGrid').innerHTML = [
                        detailItem('Result', event.result_label || event.result),
                        detailItem('Actor', event.actor?.nama || 'System'),
                        detailItem('Actor Position', [event.actor_position?.jabatan, event.actor_position?.unit_kerja].filter(Boolean).join(' - ')),
                        detailItem('Target User', event.target_user?.nama),
                        detailItem('Target Position', [event.target_position?.jabatan, event.target_position?.unit_kerja].filter(Boolean).join(' - ')),
                        detailItem('Resource', [event.resource?.type, event.resource?.id].filter(Boolean).join(' #')),
                        detailItem('Reason', event.reason),
                        detailItem('Request', [event.request?.http_method, event.request?.route_name || event.request?.request_path].filter(Boolean).join(' ')),
                        detailItem('HTTP/IP', [event.request?.http_status, event.request?.ip_address].filter(Boolean).join(' | ')),
                        detailItem('Request ID', event.request?.request_id),
                    ].join('');

                    document.getElementById('auditBeforeJson').textContent = formatJson(event.before_state);
                    document.getElementById('auditAfterJson').textContent = formatJson(event.after_state);
                    document.getElementById('auditMetadataJson').textContent = formatJson({
                        changed_fields: event.changed_fields,
                        metadata: event.metadata,
                        request: event.request,
                    });

                    modal.show();
                });
            });
        });
    </script>
@endsection
