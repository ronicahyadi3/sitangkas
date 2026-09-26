import {
    dispatchSecurePdfViewerOpen,
    isSecurePdfViewerOpenDetail,
} from './events';

const ACTION_SELECTOR = '[data-document-pdf-action="view"][data-pdf-viewer-payload]';

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

function handleAction(event: Event): void {
    const target = event.target instanceof Element
        ? event.target.closest(ACTION_SELECTOR)
        : null;

    if (!(target instanceof HTMLButtonElement) || target.disabled) {
        return;
    }

    event.preventDefault();
    const encodedPayload = target.dataset.pdfViewerPayload;

    if (typeof encodedPayload !== 'string' || encodedPayload === '') {
        notifyInvalidAction();
        return;
    }

    try {
        const payload: unknown = JSON.parse(decodeURIComponent(encodedPayload));

        if (!isSecurePdfViewerOpenDetail(payload) || !dispatchSecurePdfViewerOpen(payload)) {
            notifyInvalidAction();
        }
    } catch {
        notifyInvalidAction();
    }
}

export function installPdfViewerActionBridge(): () => void {
    if (removeListener !== null) {
        return removeListener;
    }

    document.addEventListener('click', handleAction);
    removeListener = () => {
        document.removeEventListener('click', handleAction);
        removeListener = null;
    };

    return removeListener;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
