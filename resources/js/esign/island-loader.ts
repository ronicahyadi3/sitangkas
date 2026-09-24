import { ESIGN_OPEN_EVENT, isEsignOpenEventDetail } from './events';
import type { EsignOpenEventDetail } from './types';

interface EsignAppApi {
    open(action: EsignOpenEventDetail): void;
}

type NotificationFunction = (payload: { status: number; message: string }) => void;

let appPromise: Promise<EsignAppApi> | null = null;
let removeListener: (() => void) | null = null;

async function resolveApp(): Promise<EsignAppApi> {
    if (appPromise !== null) {
        return appPromise;
    }

    const root = document.querySelector<HTMLElement>('[data-esign-app-root]');

    if (root === null) {
        throw new Error('Esign app root is not available.');
    }

    appPromise = import('./mount')
        .then(({ mountEsignApp }) => mountEsignApp(root))
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
            message: 'Editor TTE gagal dimuat. Silakan muat ulang halaman dan coba kembali.',
        });
    }
}

function handleOpenEvent(event: Event): void {
    if (!(event instanceof CustomEvent) || !isEsignOpenEventDetail(event.detail)) {
        return;
    }

    const action = Object.freeze({ ...event.detail });

    void resolveApp()
        .then((app) => app.open(action))
        .catch(() => reportLoadFailure());
}

export function installEsignIslandLoader(): () => void {
    if (removeListener !== null) {
        return removeListener;
    }

    window.addEventListener(ESIGN_OPEN_EVENT, handleOpenEvent);
    removeListener = () => {
        window.removeEventListener(ESIGN_OPEN_EVENT, handleOpenEvent);
        removeListener = null;
    };

    return removeListener;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
