<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { ESIGN_CLOSED_EVENT } from '../events';
    import type { EsignOpenEventDetail } from '../types';

    interface BootstrapModal {
        dispose(): void;
        hide(): void;
        show(): void;
    }

    interface BootstrapModalConstructor {
        getOrCreateInstance(
            element: HTMLElement,
            options?: { backdrop?: boolean | 'static'; keyboard?: boolean },
        ): BootstrapModal;
    }

    interface BootstrapGlobal {
        Modal?: BootstrapModalConstructor;
    }

    let activeAction = $state<EsignOpenEventDetail | null>(null);
    let modalElement: HTMLDivElement;
    let modal: BootstrapModal | null = null;

    export async function open(action: EsignOpenEventDetail): Promise<void> {
        activeAction = action;
        await tick();
        modal?.show();
    }

    onMount(() => {
        const bootstrap = (window as Window & { bootstrap?: BootstrapGlobal }).bootstrap;
        const Modal = bootstrap?.Modal;

        if (Modal === undefined) {
            throw new Error('Bootstrap Modal is not available.');
        }

        modal = Modal.getOrCreateInstance(modalElement, {
            backdrop: 'static',
            keyboard: true,
        });

        const handleHidden = (): void => {
            const closedAction = activeAction;
            activeAction = null;

            window.dispatchEvent(new CustomEvent(ESIGN_CLOSED_EVENT, {
                detail: closedAction === null
                    ? null
                    : Object.freeze({ step_public_id: closedAction.step_public_id }),
            }));
        };

        modalElement.addEventListener('hidden.bs.modal', handleHidden);

        if (activeAction !== null) {
            modal.show();
        }

        return () => {
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
            modal?.dispose();
            modal = null;
        };
    });
</script>

<div
    bind:this={modalElement}
    class="modal fade esign-ui"
    id="esignSigningModal"
    tabindex="-1"
    aria-labelledby="esignSigningModalTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content esign-shell">
            <div class="modal-header esign-shell__header">
                <div>
                    <p class="esign-shell__eyebrow mb-1">Tanda Tangan Elektronik</p>
                    <h2 class="modal-title h5 mb-0" id="esignSigningModalTitle">
                        Menyiapkan dokumen
                    </h2>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Tutup editor TTE"
                ></button>
            </div>

            <div class="modal-body esign-shell__body" aria-live="polite">
                <div class="esign-shell__loading" role="status">
                    <span class="spinner-border text-primary" aria-hidden="true"></span>
                    <div>
                        <p class="fw-semibold mb-1">Fondasi editor TTE siap digunakan</p>
                        <p class="text-sm text-secondary mb-0">
                            Sesi dokumen dan editor PDF akan disambungkan pada tahap berikutnya.
                        </p>
                    </div>
                </div>
            </div>

            <div class="modal-footer esign-shell__footer">
                <button type="button" class="btn btn-outline-secondary mb-0" data-bs-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
