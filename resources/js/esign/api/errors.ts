import type { EsignErrorCategory, NormalizedEsignError } from '../types';
import { isRecord } from './guards';

const FALLBACK_MESSAGES: Record<EsignErrorCategory, string> = {
    authentication: 'Sesi login sudah tidak aktif. Muat ulang halaman dan masuk kembali.',
    authorization: 'Anda tidak lagi memiliki izin untuk memproses dokumen ini.',
    not_found: 'Sesi atau dokumen sudah tidak tersedia. Buka kembali proses TTE.',
    conflict: 'Kondisi dokumen telah berubah. Muat ulang data sebelum melanjutkan.',
    validation: 'Data TTE belum valid. Periksa kembali pengaturan dokumen.',
    rate_limited: 'Permintaan terlalu sering. Tunggu sebentar sebelum mencoba kembali.',
    server: 'Layanan TTE sedang mengalami gangguan. Coba kembali setelah beberapa saat.',
    network: 'Koneksi ke server terputus. Periksa jaringan sebelum mencoba kembali.',
    invalid_response: 'Respons layanan TTE tidak dapat diverifikasi. Proses dihentikan untuk keamanan.',
};

export class EsignApiError extends Error {
    public constructor(public readonly details: NormalizedEsignError) {
        super(details.message);
        this.name = 'EsignApiError';
    }
}

function safeMessage(payload: unknown): string | null {
    if (!isRecord(payload) || typeof payload.message !== 'string') {
        return null;
    }

    const message = payload.message.trim();

    return message !== '' && message.length <= 500 ? message : null;
}

function fieldErrors(payload: unknown): Record<string, string[]> {
    if (!isRecord(payload) || !isRecord(payload.errors)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(payload.errors)
            .filter(([, messages]) => Array.isArray(messages))
            .map(([field, messages]) => [
                field,
                (messages as unknown[])
                    .filter((message): message is string => typeof message === 'string')
                    .map((message) => message.trim())
                    .filter((message) => message !== '')
                    .slice(0, 10),
            ])
            .filter(([, messages]) => messages.length > 0),
    );
}

function conflictCode(payload: unknown): string | null {
    if (!isRecord(payload) || !isRecord(payload.error)) {
        return null;
    }

    return typeof payload.error.code === 'string' && payload.error.code.trim() !== ''
        ? payload.error.code
        : null;
}

function conflictMessage(code: string | null): string | null {
    if (code === null) {
        return null;
    }

    if ([
        'esign.signing_session_expired',
        'esign.signing_session_invalid',
        'esign.signing_session_context_changed',
        'esign.signing_session_role_changed',
        'esign.source_artifact_changed',
    ].includes(code)) {
        return 'Sesi TTE sudah tidak berlaku karena konteks atau dokumen berubah. Buka kembali proses TTE.';
    }

    if (code === 'esign.prepared_rendition_busy') {
        return 'Dokumen final masih dipersiapkan. Tunggu sebentar sebelum mengulangi konfirmasi.';
    }

    if ([
        'esign.prepared_rendition_not_found',
        'esign.prepared_rendition_changed',
        'esign.prepared_rendition_context_mismatch',
        'esign.preview_hash_mismatch',
    ].includes(code)) {
        return 'Preview final sudah berubah atau kedaluwarsa. Kembali ke editor dan siapkan ulang dokumen.';
    }

    if (code === 'esign.idempotency_payload_mismatch') {
        return 'Permintaan TTE tidak cocok dengan intent sebelumnya. Jangan kirim ulang sebelum status diperiksa.';
    }

    if (code === 'esign.visible_worker_not_ready') {
        return 'Worker TTE visible belum siap. Hubungi administrator sebelum mencoba kembali.';
    }

    return null;
}

function retryAfterSeconds(response: Response): number | null {
    const header = response.headers.get('Retry-After');

    if (header === null) {
        return null;
    }

    const seconds = Number(header);

    if (Number.isFinite(seconds) && seconds >= 0) {
        return Math.ceil(seconds);
    }

    const retryAt = Date.parse(header);

    return Number.isNaN(retryAt)
        ? null
        : Math.max(0, Math.ceil((retryAt - Date.now()) / 1000));
}

function categoryForStatus(status: number): EsignErrorCategory {
    if (status === 401) {
        return 'authentication';
    }

    if (status === 403) {
        return 'authorization';
    }

    if (status === 404) {
        return 'not_found';
    }

    if (status === 409) {
        return 'conflict';
    }

    if (status === 422) {
        return 'validation';
    }

    if (status === 429) {
        return 'rate_limited';
    }

    return status >= 500 ? 'server' : 'invalid_response';
}

export function normalizeHttpError(response: Response, payload: unknown): NormalizedEsignError {
    const category = categoryForStatus(response.status);
    const code = category === 'conflict' ? conflictCode(payload) : null;
    const backendMessage = category === 'server' ? null : safeMessage(payload);

    return {
        category,
        status: response.status,
        message: conflictMessage(code) ?? backendMessage ?? FALLBACK_MESSAGES[category],
        code,
        field_errors: category === 'validation' ? fieldErrors(payload) : {},
        retry_after_seconds: category === 'rate_limited' ? retryAfterSeconds(response) : null,
    };
}

export function invalidResponseError(): NormalizedEsignError {
    return {
        category: 'invalid_response',
        status: null,
        message: FALLBACK_MESSAGES.invalid_response,
        code: null,
        field_errors: {},
        retry_after_seconds: null,
    };
}

export function normalizeRequestError(error: unknown): NormalizedEsignError {
    if (error instanceof EsignApiError) {
        return error.details;
    }

    const aborted = error instanceof DOMException && error.name === 'AbortError';

    return {
        category: 'network',
        status: null,
        message: aborted ? 'Permintaan dibatalkan.' : FALLBACK_MESSAGES.network,
        code: aborted ? 'request_aborted' : null,
        field_errors: {},
        retry_after_seconds: null,
    };
}
