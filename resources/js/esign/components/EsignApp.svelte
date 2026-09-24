<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { dispatchEsignClosed } from '../events';
    import type { EsignUiAction } from '../types';

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

    let activeAction = $state<EsignUiAction | null>(null);
    let modalElement: HTMLDivElement;
    let modal: BootstrapModal | null = null;
    let isValidation = $derived(activeAction?.kind === 'validation');

    export async function open(action: EsignUiAction): Promise<void> {
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

            if (closedAction?.kind === 'signing') {
                dispatchEsignClosed({
                    action: 'sign',
                    step_public_id: closedAction.detail.step_public_id,
                });
            }

            if (closedAction?.kind === 'validation') {
                dispatchEsignClosed({
                    action: 'verify',
                    artifact_public_id: closedAction.detail.artifact_public_id,
                });
            }
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
                    <p class="esign-shell__eyebrow mb-1">
                        {isValidation ? 'Validasi Dokumen' : 'Tanda Tangan Elektronik'}
                    </p>
                    <h2 class="modal-title h5 mb-0" id="esignSigningModalTitle">
                        {isValidation ? 'Menyiapkan validasi' : 'Menyiapkan dokumen'}
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
                        <p class="fw-semibold mb-1">
                            {isValidation ? 'Fondasi validasi siap digunakan' : 'Fondasi editor TTE siap digunakan'}
                        </p>
                        <p class="text-sm text-secondary mb-0">
                            {isValidation
                                ? 'Artifact dan hasil validasi akan disambungkan setelah endpoint canonical tersedia.'
                                : 'Sesi dokumen dan editor PDF akan disambungkan pada tahap berikutnya.'}
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
