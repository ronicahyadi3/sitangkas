import {
    dispatchSecurePdfViewerOpen,
    isSecurePdfViewerOpenDetail,
} from './events';
import { coordinateDocumentDetailChildOpen } from '../detail-modal-coordinator';

const PAYLOAD_ACTION_SELECTOR = '[data-document-pdf-action="view"][data-pdf-viewer-payload]';
const CONTRACT_ACTION_SELECTOR = '[data-document-pdf-action="contract"][data-pdf-viewer-contract-url]';
const ACTION_SELECTOR = `${PAYLOAD_ACTION_SELECTOR}, ${CONTRACT_ACTION_SELECTOR}`;

type NotificationFunction = (payload: { status: number; message: string }) => void;

let removeListener: (() => void) | null = null;

function notifyInvalidAction(): void {
    const notification = (window as Window & { notification?: NotificationFunction }).notification;

    if (typeof notification === 'function') {
        notification({
            status: 422,
            message: 'Informasi dokumen tidak valid. Muat ulang Detail Dokumen dan coba kembali.',
        });
    }
}

function notifyFailure(message: string): void {
    const notification = (window as Window & { notification?: NotificationFunction }).notification;

    if (typeof notification === 'function') {
        notification({ status: 422, message });
    }
}

function openViewer(payload: ReturnType<typeof parsePayload>, target: HTMLButtonElement): void {
    if (payload === null) {
        notifyInvalidAction();
        return;
    }

    if (target.closest('[data-document-detail-modal]') === null) {
        dispatchSecurePdfViewerOpen(payload);
        return;
    }

    coordinateDocumentDetailChildOpen({
        childKey: `${payload.document_id}:${payload.resource}`,
        childSurface: 'pdf-viewer',
        trigger: target,
        open: () => dispatchSecurePdfViewerOpen(payload),
    });
}

function parsePayload(payload: unknown) {
    return isSecurePdfViewerOpenDetail(payload) ? payload : null;
}

function controlledContractUrl(value: string): string | null {
    try {
        const url = new URL(value, window.location.origin);
        const validPath = /\/document\/(?:pdf\/[^/]+\/(?:document|billing|spj_fungsional)|history-pdf\/[^/]+)\/viewer\/?$/.test(url.pathname);

        return url.origin === window.location.origin
            && ['http:', 'https:'].includes(url.protocol)
            && validPath
            ? url.toString()
            : null;
    } catch {
        return null;
    }
}

async function fetchContract(url: string): Promise<ReturnType<typeof parsePayload>> {
    const response = await window.fetch(url, {
        method: 'GET',
        headers: new Headers({ Accept: 'application/json' }),
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'follow',
    });

    if (!response.ok) {
        if (response.status === 401) {
            throw new Error('Sesi login berakhir. Silakan masuk kembali.');
        }

        if ([403, 404].includes(response.status)) {
            throw new Error('Dokumen tidak ditemukan atau tidak dapat diakses.');
        }

        throw new Error('Informasi dokumen tidak dapat dimuat dari server.');
    }

    const contentType = response.headers.get('Content-Type')?.toLowerCase() ?? '';
    if (!contentType.includes('application/json')) {
        throw new Error('Format informasi dokumen tidak dikenali.');
    }

    const envelope: unknown = await response.json();
    if (typeof envelope !== 'object' || envelope === null || !('data' in envelope)) {
        return null;
    }

    return parsePayload((envelope as { data: unknown }).data);
}

async function handleAction(event: Event): Promise<void> {
    const target = event.target instanceof Element
        ? event.target.closest(ACTION_SELECTOR)
        : null;

    if (!(target instanceof HTMLButtonElement) || target.disabled) {
        return;
    }

    event.preventDefault();
    const encodedPayload = target.dataset.pdfViewerPayload;

    if (typeof encodedPayload === 'string' && encodedPayload !== '') {
        try {
            openViewer(parsePayload(JSON.parse(decodeURIComponent(encodedPayload))), target);
        } catch {
            notifyInvalidAction();
        }

        return;
    }

    const contractUrl = typeof target.dataset.pdfViewerContractUrl === 'string'
        ? controlledContractUrl(target.dataset.pdfViewerContractUrl)
        : null;
    if (contractUrl === null) {
        notifyInvalidAction();
        return;
    }

    target.disabled = true;
    target.setAttribute('aria-busy', 'true');

    try {
        const payload = await fetchContract(contractUrl);
        if (payload === null) {
            notifyInvalidAction();
            return;
        }

        openViewer(payload, target);
    } catch (error: unknown) {
        notifyFailure(error instanceof Error
            ? error.message
            : 'Informasi dokumen tidak dapat dimuat.');
    } finally {
        target.disabled = false;
        target.removeAttribute('aria-busy');
    }
}

export function installPdfViewerActionBridge(): () => void {
    if (removeListener !== null) {
        return removeListener;
    }

    const listener = (event: Event): void => {
        void handleAction(event);
    };

    document.addEventListener('click', listener);
    removeListener = () => {
        document.removeEventListener('click', listener);
        removeListener = null;
    };

    return removeListener;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
