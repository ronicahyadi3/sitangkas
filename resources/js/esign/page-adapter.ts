import { ESIGN_COMPLETED_EVENT, isEsignCompletedEventDetail } from './events';
import { shouldDeferEsignCompletionRefresh } from '../documents/detail-modal-coordinator';

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

let removeListener: (() => void) | null = null;

function reloadOptedInTables(): void {
    const DataTable = (window as Window & { DataTable?: DataTableConstructor }).DataTable;

    if (DataTable === undefined) {
        return;
    }

    document
        .querySelectorAll<HTMLTableElement>('table[data-esign-refresh-on-complete]')
        .forEach((table) => {
            if (!DataTable.isDataTable(table)) {
                return;
            }

            const instance = new DataTable(table);
            instance.ajax?.reload(null, false);
        });
}

function handleCompletion(event: Event): void {
    if (!(event instanceof CustomEvent) || !isEsignCompletedEventDetail(event.detail)) {
        return;
    }

    if (shouldDeferEsignCompletionRefresh(event.detail.step_public_id)) {
        return;
    }

    reloadOptedInTables();
}

export function installEsignPageAdapter(): () => void {
    if (removeListener !== null) {
        return removeListener;
    }

    window.addEventListener(ESIGN_COMPLETED_EVENT, handleCompletion);
    removeListener = () => {
        window.removeEventListener(ESIGN_COMPLETED_EVENT, handleCompletion);
        removeListener = null;
    };

    return removeListener;
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
