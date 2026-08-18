const heartbeatIntervalMs = 30000;
const adminRefreshIntervalMs = 30000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function jsonHeaders() {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (window.Echo?.socketId()) {
        headers['X-Socket-ID'] = window.Echo.socketId();
    }

    return headers;
}

function visibilityState() {
    return document.visibilityState === 'hidden' ? 'hidden' : 'visible';
}

function bootHeartbeat() {
    const config = window.sitangkasRealtime;

    if (!config?.heartbeatUrl) {
        return;
    }

    let lastActivityAt = Date.now();

    const markActivity = () => {
        lastActivityAt = Date.now();
    };

    ['click', 'keydown', 'pointerdown', 'scroll'].forEach((eventName) => {
        window.addEventListener(eventName, markActivity, { passive: true });
    });

    const payload = () => {
        const idleSeconds = Math.max(0, Math.floor((Date.now() - lastActivityAt) / 1000));
        const currentVisibility = visibilityState();

        return {
            visibility_state: currentVisibility,
            idle_seconds: idleSeconds,
            activity_state: currentVisibility === 'hidden' ? 'away' : (idleSeconds >= 300 ? 'idle' : 'active'),
        };
    };

    const sendHeartbeat = () => fetch(config.heartbeatUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(payload()),
    }).catch(() => {});

    const sendLeave = () => {
        if (!config.leaveUrl) {
            return;
        }

        const body = new URLSearchParams({
            reason: 'closed',
            _token: csrfToken(),
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon(config.leaveUrl, body);
            return;
        }

        fetch(config.leaveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonHeaders(),
            body: JSON.stringify({ reason: 'closed' }),
            keepalive: true,
        }).catch(() => {});
    };

    document.addEventListener('visibilitychange', sendHeartbeat);
    window.addEventListener('beforeunload', sendLeave);

    sendHeartbeat();
    window.setInterval(sendHeartbeat, heartbeatIntervalMs);
}

function statusLabel(status) {
    return String(status || 'offline').toUpperCase();
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function text(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
}

function escapeHtml(value) {
    return text(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function setConnection(status, label) {
    const element = document.getElementById('onlinePresenceConnection');

    if (!element) {
        return;
    }

    element.classList.remove('is-connected', 'is-error');

    if (status === 'connected') {
        element.classList.add('is-connected');
    }

    if (status === 'error') {
        element.classList.add('is-error');
    }

    element.innerHTML = `<i class="fa-solid fa-circle-nodes"></i>${label}`;
}

function notifyRealtime(payload, fallbackMessage = 'Notifikasi realtime diterima.') {
    const severity = text(payload?.severity).toLowerCase();
    const status = {
        success: 200,
        info: 200,
        warning: 422,
        error: 500,
    }[severity] ?? 200;
    const title = payload?.title || payload?.body || payload?.message || fallbackMessage;

    window.dispatchEvent(new CustomEvent('sitangkas:realtime-notification', {
        detail: payload,
    }));

    if (typeof window.notification === 'function') {
        window.notification({
            status,
            message: title,
        });
    }
}

function notifyRealtimeMessage(message) {
    window.dispatchEvent(new CustomEvent('sitangkas:realtime-message', {
        detail: message,
    }));

    notifyRealtime(message, 'Pesan realtime diterima.');
}

function bootPrivateRealtime() {
    const config = window.sitangkasRealtimeUser;

    if (!window.Echo || !config?.id) {
        return;
    }

    window.Echo.private(`App.Models.User.${config.id}`)
        .notification((payload) => notifyRealtime(payload))
        .listen('.realtime.message.created', (message) => notifyRealtimeMessage(message))
        .error(() => {});
}

function renderSummary(summary) {
    Object.entries(summary || {}).forEach(([key, value]) => {
        const element = document.querySelector(`[data-presence-summary="${key}"]`);

        if (element) {
            element.textContent = value;
        }
    });
}

function renderRows(sessions) {
    const tbody = document.getElementById('onlinePresenceRows');

    if (!tbody) {
        return;
    }

    if (!Array.isArray(sessions) || sessions.length === 0) {
        tbody.innerHTML = `
            <tr data-presence-empty>
                <td colspan="7">
                    <div class="realtime-empty">Belum ada heartbeat user yang tercatat.</div>
                </td>
            </tr>
        `;

        return;
    }

    tbody.innerHTML = sessions.map((session) => {
        const status = text(session.status).toLowerCase();
        const position = session.position || {};
        const browser = [session.browser_name, session.browser_version].filter(Boolean).join(' ');
        const platform = [session.platform_name, session.platform_version].filter(Boolean).join(' ');

        return `
            <tr data-presence-id="${escapeHtml(session.presence_id)}">
                <td class="realtime-user-cell">
                    <div class="realtime-user-name">${escapeHtml(session.user_name)}</div>
                    <div class="realtime-user-meta">ID: ${escapeHtml(session.user_id)}</div>
                </td>
                <td>
                    <span class="realtime-status realtime-status--${status}">
                        ${escapeHtml(statusLabel(status))}
                    </span>
                </td>
                <td class="realtime-muted">${escapeHtml(session.connection_count)}</td>
                <td class="realtime-muted">
                    ${escapeHtml(browser || session.device_type)}
                    <br>${escapeHtml(platform || session.device_name)}
                </td>
                <td class="realtime-muted">${escapeHtml(session.ip_address)}</td>
                <td class="realtime-muted">${escapeHtml(formatDate(session.last_seen_at))}</td>
                <td class="realtime-muted">
                    ${escapeHtml(position.nama_jabatan)}
                    <br>${escapeHtml(position.nama_unit_kerja)}
                </td>
            </tr>
        `;
    }).join('');
}

function bootAdminDashboard() {
    const config = window.sitangkasRealtimeAdmin;

    if (!config?.stateUrl) {
        return;
    }

    const refresh = () => fetch(config.stateUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => response.ok ? response.json() : Promise.reject(response))
        .then((state) => {
            renderSummary(state.summary);
            renderRows(state.sessions);
        })
        .catch(() => setConnection('error', 'Gagal sinkron'));

    document.getElementById('onlinePresenceRefresh')?.addEventListener('click', refresh);

    if (window.Echo && config.channel) {
        window.Echo.join(config.channel)
            .here(() => {
                setConnection('connected', 'Realtime aktif');
                refresh();
            })
            .joining(() => refresh())
            .leaving(() => refresh())
            .listen('.user.presence.changed', () => refresh())
            .error(() => setConnection('error', 'Channel gagal'));
    } else {
        setConnection('error', 'Echo tidak tersedia');
    }

    refresh();
    window.setInterval(refresh, adminRefreshIntervalMs);
}

document.addEventListener('DOMContentLoaded', () => {
    bootHeartbeat();
    bootPrivateRealtime();
    bootAdminDashboard();
});
