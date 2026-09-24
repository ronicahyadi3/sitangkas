import {
    ESIGN_OPEN_EVENT,
    ESIGN_VALIDATION_OPEN_EVENT,
    isEsignOpenEventDetail,
    isEsignValidationOpenEventDetail,
} from './events';
import type { EsignUiAction } from './types';

interface EsignAppApi {
    destroy(): Promise<void>;
    open(action: EsignUiAction): void;
}

type NotificationFunction = (payload: { status: number; message: string }) => void;

let appPromise: Promise<EsignAppApi> | null = null;
let appInstance: EsignAppApi | null = null;
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
        .then(({ mountEsignApp }) => {
            appInstance = mountEsignApp(root);

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
            message: 'Editor TTE gagal dimuat. Silakan muat ulang halaman dan coba kembali.',
        });
    }
}

function openIsland(action: EsignUiAction): void {
    void resolveApp()
        .then((app) => app.open(action))
        .catch(() => reportLoadFailure());
}

function handleSigningOpenEvent(event: Event): void {
    if (!(event instanceof CustomEvent) || !isEsignOpenEventDetail(event.detail)) {
        return;
    }

    openIsland({
        kind: 'signing',
        detail: Object.freeze({ ...event.detail }),
    });
}

function handleValidationOpenEvent(event: Event): void {
    if (!(event instanceof CustomEvent) || !isEsignValidationOpenEventDetail(event.detail)) {
        return;
    }

    openIsland({
        kind: 'validation',
        detail: Object.freeze({ ...event.detail }),
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
            .then(async (app) => {
                await app.destroy();

                if (appInstance === app) {
                    appInstance = null;
                }
            })
            .catch(() => undefined);
    }
}

export function installEsignIslandLoader(): () => void {
    if (removeListener !== null) {
        return removeListener;
    }

    window.addEventListener(ESIGN_OPEN_EVENT, handleSigningOpenEvent);
    window.addEventListener(ESIGN_VALIDATION_OPEN_EVENT, handleValidationOpenEvent);
    removeListener = () => {
        window.removeEventListener(ESIGN_OPEN_EVENT, handleSigningOpenEvent);
        window.removeEventListener(ESIGN_VALIDATION_OPEN_EVENT, handleValidationOpenEvent);
        destroyIsland();
        removeListener = null;
    };

    return removeListener;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
