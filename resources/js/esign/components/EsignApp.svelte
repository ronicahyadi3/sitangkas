<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { dispatchEsignClosed } from '../events';
    import {
        stageBlocksClose,
        stageIsBusy,
        stagePresentation,
        type EsignShellStage,
    } from '../ui-state';
    import type { EsignUiAction } from '../types';
    import SigningShellBody from './SigningShellBody.svelte';
    import SigningStepper from './SigningStepper.svelte';

    interface BootstrapModal {
        dispose(): void;
        hide(): void;
        show(): void;
    }

    interface BootstrapModalConstructor {
        getOrCreateInstance(
            element: HTMLElement,
            options?: {
                backdrop?: boolean | 'static';
                focus?: boolean;
                keyboard?: boolean;
            },
        ): BootstrapModal;
    }

    interface BootstrapGlobal {
        Modal?: BootstrapModalConstructor;
    }

    let activeAction = $state<EsignUiAction | null>(null);
    let stage = $state<EsignShellStage>('loading');
    let closeFeedback = $state('');
    let modalElement: HTMLDivElement;
    let modalTitleElement: HTMLHeadingElement;
    let modal: BootstrapModal | null = null;
    let returnFocusTarget: HTMLElement | null = null;

    let isValidation = $derived(activeAction?.kind === 'validation');
    let presentation = $derived(stagePresentation(stage));
    let closeBlocked = $derived(!isValidation && stageBlocksClose(stage));
    let busy = $derived(!isValidation && stageIsBusy(stage));

    export async function open(action: EsignUiAction): Promise<void> {
        if (closeBlocked) {
            closeFeedback = 'Selesaikan proses yang sedang berjalan sebelum membuka dokumen lain.';

            return;
        }

        returnFocusTarget = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        activeAction = action;
        stage = 'loading';
        closeFeedback = '';

        await tick();
        modal?.show();
    }

    function requestClose(): void {
        if (closeBlocked) {
            closeFeedback = stage === 'submitting'
                ? 'Permintaan TTE sedang dikirim. Tunggu hingga server menerima permintaan.'
                : 'Dokumen sedang dipersiapkan. Tunggu proses ini selesai.';

            return;
        }

        modal?.hide();
    }

    function dispatchClosedAction(action: EsignUiAction | null): void {
        if (action?.kind === 'signing') {
            dispatchEsignClosed({
                action: 'sign',
                step_public_id: action.detail.step_public_id,
            });
        }

        if (action?.kind === 'validation') {
            dispatchEsignClosed({
                action: 'verify',
                artifact_public_id: action.detail.artifact_public_id,
            });
        }
    }

    onMount(() => {
        const bootstrap = (window as Window & { bootstrap?: BootstrapGlobal }).bootstrap;
        const Modal = bootstrap?.Modal;

        if (Modal === undefined) {
            throw new Error('Bootstrap Modal is not available.');
        }

        modal = Modal.getOrCreateInstance(modalElement, {
            backdrop: true,
            focus: true,
            keyboard: true,
        });

        const handleHide = (event: Event): void => {
            if (!closeBlocked) {
                return;
            }

            event.preventDefault();
            requestClose();
        };

        const handleShown = (): void => {
            modalTitleElement.focus({ preventScroll: true });
        };

        const handleHidden = (): void => {
            const closedAction = activeAction;
            const focusTarget = returnFocusTarget;

            activeAction = null;
            stage = 'loading';
            closeFeedback = '';
            returnFocusTarget = null;
            dispatchClosedAction(closedAction);

            window.requestAnimationFrame(() => {
                if (focusTarget?.isConnected) {
                    focusTarget.focus({ preventScroll: true });
                }
            });
        };

        modalElement.addEventListener('hide.bs.modal', handleHide);
        modalElement.addEventListener('shown.bs.modal', handleShown);
        modalElement.addEventListener('hidden.bs.modal', handleHidden);

        if (activeAction !== null) {
            modal.show();
        }

        return () => {
            modalElement.removeEventListener('hide.bs.modal', handleHide);
            modalElement.removeEventListener('shown.bs.modal', handleShown);
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
    role="dialog"
    aria-labelledby="esignSigningModalTitle"
    aria-describedby="esignSigningModalDescription"
    aria-hidden="true"
    aria-busy={busy}
>
    <div
        class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down"
        role="document"
    >
        <div class="modal-content esign-shell">
            <header class="modal-header esign-shell__header">
                <div class="esign-shell__heading">
                    <p class="esign-shell__eyebrow mb-1">
                        {isValidation ? 'Validasi Dokumen' : 'Tanda Tangan Elektronik'}
                    </p>
                    <h2
                        bind:this={modalTitleElement}
                        class="modal-title h5 mb-1"
                        id="esignSigningModalTitle"
                        tabindex="-1"
                    >
                        {isValidation ? 'Memeriksa dokumen elektronik' : 'Dokumen siap diproses'}
                    </h2>
                    <p class="esign-shell__description mb-0" id="esignSigningModalDescription">
                        {isValidation
                            ? 'Informasi validasi akan dimuat dari artifact dokumen yang dipilih.'
                            : 'Atur posisi QR dan footer, konfirmasi, lalu kirim proses TTE.'}
                    </p>
                </div>

                <div class="esign-shell__header-actions">
                    <span
                        class="badge {isValidation ? 'bg-gradient-info' : presentation.badgeClass}"
                        aria-live="polite"
                    >
                        {isValidation ? 'Memuat validasi' : presentation.label}
                    </span>
                    <button
                        type="button"
                        class="btn-close"
                        class:esign-shell__close--blocked={closeBlocked}
                        aria-disabled={closeBlocked}
                        aria-label="Tutup editor TTE"
                        onclick={requestClose}
                    ></button>
                </div>
            </header>

            {#if !isValidation}
                <SigningStepper currentStep={presentation.step} />
            {/if}

            {#if closeFeedback !== ''}
                <div class="alert alert-warning esign-shell__close-feedback mb-0" role="alert" aria-live="assertive">
                    <i class="fas fa-lock me-2" aria-hidden="true"></i>
                    {closeFeedback}
                </div>
            {/if}

            <main class="modal-body esign-shell__body" aria-live="polite">
                {#if isValidation}
                    <section class="esign-state esign-state--centered" role="status">
                        <span class="spinner-border text-info" aria-hidden="true"></span>
                        <div>
                            <h3 class="h6 mb-1">Menyiapkan informasi validasi</h3>
                            <p class="text-sm text-secondary mb-0">
                                Endpoint validasi canonical akan dihubungkan pada tahap integrasi validasi.
                            </p>
                        </div>
                    </section>
                {:else}
                    <SigningShellBody {stage} />
                {/if}
            </main>

            <footer class="modal-footer esign-shell__footer">
                {#if isValidation || stage === 'loading'}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={requestClose}>
                        Batal
                    </button>
                {:else if stage === 'editing'}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={requestClose}>
                        Batal
                    </button>
                    <div class="esign-shell__footer-actions">
                        <button type="button" class="btn btn-outline-primary mb-0" disabled>
                            Reset Posisi
                        </button>
                        <button type="button" class="btn btn-outline-primary mb-0" disabled>
                            Tambah QR
                        </button>
                        <button type="button" class="btn bg-gradient-primary mb-0" disabled>
                            Lanjutkan
                        </button>
                    </div>
                {:else if stage === 'preparing_rendition' || stage === 'submitting'}
                    <button type="button" class="btn bg-gradient-primary mb-0" disabled>
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        Mohon tunggu
                    </button>
                {:else if stage === 'confirming_prepared'}
                    <button type="button" class="btn btn-outline-secondary mb-0" disabled>
                        Kembali Edit
                    </button>
                    <button type="button" class="btn bg-gradient-primary mb-0" disabled>
                        Tandatangani Sekarang
                    </button>
                {:else if stage === 'processing'}
                    <p class="esign-shell__footer-note mb-0">
                        Proses tetap berjalan di server meskipun modal ditutup.
                    </p>
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={requestClose}>
                        Tutup
                    </button>
                {:else}
                    <button type="button" class="btn bg-gradient-primary mb-0" onclick={requestClose}>
                        Selesai
                    </button>
                {/if}
            </footer>
        </div>
    </div>
</div>
