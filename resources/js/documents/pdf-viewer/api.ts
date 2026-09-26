import { isApiEnvelope, isArtifactVerification } from '../../esign/api/guards';
import type { ArtifactVerification } from '../../esign/types';

function sameOriginUrl(value: string): string {
    const url = new URL(value, window.location.origin);

    if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) {
        throw new Error('URL dokumen tidak valid.');
    }

    return url.toString();
}

async function responseMessage(response: Response): Promise<string> {
    if (response.status === 401) {
        return 'Sesi login berakhir. Silakan masuk kembali.';
    }

    if (response.status === 403) {
        return 'Posisi aktif tidak diizinkan membuka dokumen ini.';
    }

    if (response.status === 404) {
        return 'Dokumen tidak ditemukan atau sudah tidak tersedia.';
    }

    if (response.status === 409) {
        return 'Keadaan dokumen berubah. Tutup viewer lalu buka kembali.';
    }

    return 'Dokumen tidak dapat dimuat dari server.';
}

export async function fetchPdfBinary(url: string, signal: AbortSignal): Promise<ArrayBuffer> {
    const response = await window.fetch(sameOriginUrl(url), {
        method: 'GET',
        headers: new Headers({ Accept: 'application/pdf' }),
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'follow',
        signal,
    });
    const contentType = response.headers.get('Content-Type')?.toLowerCase() ?? '';

    if (!response.ok) {
        throw new Error(await responseMessage(response));
    }

    if (response.status !== 200 || !contentType.startsWith('application/pdf')) {
        throw new Error('Server tidak mengirimkan dokumen PDF yang valid.');
    }

    return response.arrayBuffer();
}

export async function fetchArtifactVerification(
    url: string,
    signal: AbortSignal,
): Promise<ArtifactVerification> {
    const response = await window.fetch(sameOriginUrl(url), {
        method: 'GET',
        headers: new Headers({ Accept: 'application/json' }),
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'follow',
        signal,
    });

    if (!response.ok) {
        throw new Error(await responseMessage(response));
    }

    const contentType = response.headers.get('Content-Type')?.toLowerCase() ?? '';

    if (response.status !== 200 || !contentType.includes('application/json')) {
        throw new Error('Server mengirimkan hasil validasi yang tidak dikenali.');
    }

    const payload: unknown = await response.json();

    if (!isApiEnvelope(payload, isArtifactVerification)) {
        throw new Error('Format hasil validasi dokumen tidak sesuai kontrak.');
    }

    return payload.data;
}
