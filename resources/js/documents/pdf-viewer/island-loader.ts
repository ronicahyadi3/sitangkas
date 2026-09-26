import {
    PDF_VIEWER_OPEN_EVENT,
    dispatchSecurePdfViewerClosed,
    isSecurePdfViewerOpenDetail,
} from './events';
import type { SecurePdfViewerOpenDetail } from './types';

interface SecurePdfViewerApi {
    destroy(): Promise<void>;
    open(action: SecurePdfViewerOpenDetail): Promise<void>;
}

type NotificationFunction = (payload: { status: number; message: string }) => void;

let appPromise: Promise<SecurePdfViewerApi> | null = null;
let appInstance: SecurePdfViewerApi | null = null;
let removeListener: (() => void) | null = null;

async function resolveApp(): Promise<SecurePdfViewerApi> {
    if (appPromise !== null) {
        return appPromise;
    }

    const root = document.querySelector<HTMLElement>('[data-secure-pdf-viewer-root]');

    if (root === null) {
        throw new Error('Secure PDF viewer root is not available.');
    }

    appPromise = import('./mount')
        .then(({ mountSecurePdfViewer }) => {
            appInstance = mountSecurePdfViewer(root);

            return appInstance;
        })
        .catch((error: unknown) => {
            appPromise = null;
            throw error;
        });

    return appPromise;
}

function reportLoadFailure(): void {
    const notification = (window as Window & { notification?: NotificationFunction }).notification;

    if (typeof notification === 'function') {
        notification({
            status: 500,
            message: 'Viewer PDF gagal dimuat. Silakan muat ulang halaman dan coba kembali.',
        });
    }
}

function handleOpen(event: Event): void {
    if (!(event instanceof CustomEvent) || !isSecurePdfViewerOpenDetail(event.detail)) {
        return;
    }

    void resolveApp()
        .then((app) => app.open(Object.freeze({ ...event.detail })))
        .catch(() => {
            reportLoadFailure();
            dispatchSecurePdfViewerClosed({
                document_id: event.detail.document_id,
                resource: event.detail.resource,
            });
        });
}

function destroyIsland(): void {
    const mountedApp = appInstance;
    const pendingApp = appPromise;

    appInstance = null;
    appPromise = null;

    if (mountedApp !== null) {
        void mountedApp.destroy();
        return;
    }

    if (pendingApp !== null) {
        void pendingApp
            .then((app) => app.destroy())
            .catch(() => undefined);
    }
}

export function installPdfViewerIslandLoader(): () => void {
    if (removeListener !== null) {
        return removeListener;
    }

    window.addEventListener(PDF_VIEWER_OPEN_EVENT, handleOpen);
    removeListener = () => {
        window.removeEventListener(PDF_VIEWER_OPEN_EVENT, handleOpen);
        destroyIsland();
        removeListener = null;
    };

    return removeListener;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
