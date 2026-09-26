import {
    ESIGN_CLOSED_EVENT,
    ESIGN_COMPLETED_EVENT,
    isEsignClosedEventDetail,
    isEsignCompletedEventDetail,
} from '../esign/events';
import {
    PDF_VIEWER_CLOSED_EVENT,
    isSecurePdfViewerClosedDetail,
} from './pdf-viewer/events';

type ChildSurface = 'esign' | 'pdf-viewer';

interface BootstrapModal {
    hide(): void;
    show(): void;
}

interface BootstrapModalConstructor {
    getOrCreateInstance(element: HTMLElement): BootstrapModal;
}

interface BootstrapGlobal {
    Modal?: BootstrapModalConstructor;
}

interface DataTableAjaxApi {
    reload(callback?: ((json: unknown) => void) | null, resetPaging?: boolean): void;
}

interface DataTableApi {
    ajax?: DataTableAjaxApi;
}

interface DataTableConstructor {
    new (table: HTMLTableElement): DataTableApi;
    isDataTable(table: HTMLTableElement): boolean;
}

interface ScrollSnapshot {
    element: HTMLElement;
    left: number;
    top: number;
}

interface ActiveDetailContext {
    childKey: string;
    childSurface: ChildSurface;
    focusTarget: HTMLElement | null;
    modal: BootstrapModal;
    modalElement: HTMLElement;
    refreshOnRestore: boolean;
    scrollSnapshots: ScrollSnapshot[];
}

interface CoordinatedChildOpen {
    childKey: string;
    childSurface: ChildSurface;
    open(): boolean;
    trigger: Element;
}

type NotificationFunction = (payload: { status: number; message: string }) => void;

const DETAIL_MODAL_SELECTOR = '[data-document-detail-modal].modal.show';
const SUSPENDED_ATTRIBUTE = 'data-modal-coordinator-suspended';

let activeContext: ActiveDetailContext | null = null;
let removeListeners: (() => void) | null = null;

function notifyBusy(): void {
    const notification = (window as Window & { notification?: NotificationFunction }).notification;

    if (typeof notification === 'function') {
        notification({
            status: 409,
            message: 'Selesaikan atau tutup tampilan dokumen yang sedang aktif terlebih dahulu.',
        });
    }
}

function bootstrapModal(element: HTMLElement): BootstrapModal | null {
    const bootstrap = (window as Window & { bootstrap?: BootstrapGlobal }).bootstrap;
    const Modal = bootstrap?.Modal;

    return Modal === undefined ? null : Modal.getOrCreateInstance(element);
}

function captureScroll(modalElement: HTMLElement): ScrollSnapshot[] {
    const elements = new Set<HTMLElement>([
        modalElement,
        ...modalElement.querySelectorAll<HTMLElement>(
            '.modal-body, .table-responsive, .dt-scroll-body, [data-document-detail-scroll]',
        ),
    ]);

    return [...elements].map((element) => ({
        element,
        left: element.scrollLeft,
        top: element.scrollTop,
    }));
}

function restoreScroll(context: ActiveDetailContext): void {
    for (const snapshot of context.scrollSnapshots) {
        if (!snapshot.element.isConnected) {
            continue;
        }

        snapshot.element.scrollTo({
            behavior: 'auto',
            left: snapshot.left,
            top: snapshot.top,
        });
    }
}

function focusRestoredModal(context: ActiveDetailContext, preferTrigger: boolean): void {
    const focusTarget = context.focusTarget;

    if (preferTrigger
        && focusTarget?.isConnected
        && context.modalElement.contains(focusTarget)) {
        focusTarget.focus({ preventScroll: true });
        return;
    }

    context.modalElement.focus({ preventScroll: true });
}

function reloadContextTable(context: ActiveDetailContext, done: () => void): void {
    const selector = context.modalElement.dataset.documentDetailTable?.trim();
    const DataTable = (window as Window & { DataTable?: DataTableConstructor }).DataTable;

    if (selector === undefined || selector === '' || DataTable === undefined) {
        done();
        return;
    }

    let table: HTMLTableElement | null = null;

    try {
        table = context.modalElement.querySelector<HTMLTableElement>(selector);
    } catch {
        done();
        return;
    }

    if (table === null || !DataTable.isDataTable(table)) {
        done();
        return;
    }

    const instance = new DataTable(table);

    if (instance.ajax === undefined) {
        done();
        return;
    }

    instance.ajax.reload(() => done(), false);
}

function restoreParent(context: ActiveDetailContext): void {
    if (activeContext !== context) {
        return;
    }

    activeContext = null;
    context.modalElement.removeAttribute(SUSPENDED_ATTRIBUTE);

    if (!context.modalElement.isConnected) {
        return;
    }

    const handleShown = (): void => {
        restoreScroll(context);

        if (!context.refreshOnRestore) {
            focusRestoredModal(context, true);
            return;
        }

        reloadContextTable(context, () => {
            restoreScroll(context);
            focusRestoredModal(context, false);
        });
    };

    context.modalElement.addEventListener('shown.bs.modal', handleShown, { once: true });
    context.modal.show();
}

function closeChild(childSurface: ChildSurface, childKey: string): void {
    const context = activeContext;

    if (context === null
        || context.childSurface !== childSurface
        || context.childKey !== childKey) {
        return;
    }

    window.requestAnimationFrame(() => restoreParent(context));
}

function childOpenFailed(context: ActiveDetailContext): void {
    const notification = (window as Window & { notification?: NotificationFunction }).notification;

    if (typeof notification === 'function') {
        notification({
            status: 422,
            message: 'Tampilan dokumen tidak dapat dibuka. Silakan coba kembali.',
        });
    }

    restoreParent(context);
}

export function coordinateDocumentDetailChildOpen(request: CoordinatedChildOpen): boolean {
    const parentElement = request.trigger.closest<HTMLElement>(DETAIL_MODAL_SELECTOR);

    if (parentElement === null) {
        return request.open();
    }

    if (activeContext !== null) {
        notifyBusy();
        return false;
    }

    const modal = bootstrapModal(parentElement);

    if (modal === null) {
        return request.open();
    }

    const context: ActiveDetailContext = {
        childKey: request.childKey,
        childSurface: request.childSurface,
        focusTarget: request.trigger instanceof HTMLElement ? request.trigger : null,
        modal,
        modalElement: parentElement,
        refreshOnRestore: false,
        scrollSnapshots: captureScroll(parentElement),
    };

    activeContext = context;
    parentElement.setAttribute(SUSPENDED_ATTRIBUTE, 'true');
    parentElement.addEventListener('hidden.bs.modal', () => {
        if (activeContext !== context) {
            return;
        }

        try {
            if (!request.open()) {
                childOpenFailed(context);
            }
        } catch {
            childOpenFailed(context);
        }
    }, { once: true });
    modal.hide();

    return true;
}

export function shouldDeferEsignCompletionRefresh(stepPublicId: string): boolean {
    return activeContext?.childSurface === 'esign'
        && activeContext.childKey === `sign:${stepPublicId}`;
}

function handleEsignCompletion(event: Event): void {
    if (!(event instanceof CustomEvent) || !isEsignCompletedEventDetail(event.detail)) {
        return;
    }

    const context = activeContext;

    if (context?.childSurface === 'esign'
        && context.childKey === `sign:${event.detail.step_public_id}`) {
        context.refreshOnRestore = true;
    }
}

function handleEsignClosed(event: Event): void {
    if (!(event instanceof CustomEvent) || !isEsignClosedEventDetail(event.detail)) {
        return;
    }

    const childKey = event.detail.action === 'sign'
        ? `sign:${event.detail.step_public_id}`
        : `verify:${event.detail.artifact_public_id}`;

    closeChild('esign', childKey);
}

function handlePdfViewerClosed(event: Event): void {
    if (!(event instanceof CustomEvent) || !isSecurePdfViewerClosedDetail(event.detail)) {
        return;
    }

    closeChild(
        'pdf-viewer',
        `${event.detail.document_id}:${event.detail.resource}`,
    );
}

export function installDocumentDetailModalCoordinator(): () => void {
    if (removeListeners !== null) {
        return removeListeners;
    }

    window.addEventListener(ESIGN_COMPLETED_EVENT, handleEsignCompletion);
    window.addEventListener(ESIGN_CLOSED_EVENT, handleEsignClosed);
    window.addEventListener(PDF_VIEWER_CLOSED_EVENT, handlePdfViewerClosed);
    removeListeners = () => {
        window.removeEventListener(ESIGN_COMPLETED_EVENT, handleEsignCompletion);
        window.removeEventListener(ESIGN_CLOSED_EVENT, handleEsignClosed);
        window.removeEventListener(PDF_VIEWER_CLOSED_EVENT, handlePdfViewerClosed);

        const context = activeContext;
        removeListeners = null;

        if (context !== null) {
            restoreParent(context);
        }
    };

    return removeListeners;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListeners?.());
}
