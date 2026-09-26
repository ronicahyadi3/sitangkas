<script lang="ts">
    import { onMount, tick } from 'svelte';
    import type { ArtifactVerification } from '../../esign/types';
    import { fetchArtifactVerification } from './api';
    import { dispatchSecurePdfViewerClosed } from './events';
    import PdfDocumentInformation from './PdfDocumentInformation.svelte';
    import PdfDocumentViewport from './PdfDocumentViewport.svelte';
    import type { SecurePdfViewerOpenDetail } from './types';

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

    let activeAction = $state<SecurePdfViewerOpenDetail | null>(null);
    let pageCount = $state(0);
    let viewerError = $state<string | null>(null);
    let verification = $state<ArtifactVerification | null>(null);
    let verificationError = $state<string | null>(null);
    let verificationLoading = $state(false);
    let showInformation = $state(true);
    let modalElement: HTMLDivElement;
    let titleElement: HTMLHeadingElement;
    let modal: BootstrapModal | null = null;
    let returnFocusTarget: HTMLElement | null = null;
    let verificationController: AbortController | null = null;
    let requestGeneration = 0;

    export async function open(action: SecurePdfViewerOpenDetail): Promise<void> {
        verificationController?.abort();
        requestGeneration += 1;
        returnFocusTarget = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        activeAction = action;
        pageCount = 0;
        viewerError = null;
        verification = null;
        verificationError = null;
        verificationLoading = false;
        showInformation = window.matchMedia('(min-width: 992px)').matches;

        await tick();
        modal?.show();
        void loadVerification(action);
    }

    async function loadVerification(action: SecurePdfViewerOpenDetail): Promise<void> {
        const verifyAction = action.actions.verify;

        if (!verifyAction.allowed || verifyAction.verification_url === null) {
            return;
        }

        const controller = new AbortController();
        const generation = ++requestGeneration;
        verificationController = controller;
        verificationLoading = true;

        try {
            const result = await fetchArtifactVerification(
                verifyAction.verification_url,
                controller.signal,
            );

            if (controller.signal.aborted || generation !== requestGeneration) {
                return;
            }

            if (result.artifact.public_id !== verifyAction.artifact_public_id) {
                throw new Error('Identitas artifact hasil validasi tidak sesuai dokumen.');
            }

            verification = result;
        } catch (error: unknown) {
            if (controller.signal.aborted || generation !== requestGeneration) {
                return;
            }

            verificationError = error instanceof Error
                ? error.message
                : 'Hasil validasi TTE tidak dapat dimuat.';
        } finally {
            if (generation === requestGeneration) {
                verificationLoading = false;
                verificationController = null;
            }
        }
    }

    function requestClose(): void {
        modal?.hide();
    }

    function closeInformation(): void {
        showInformation = false;
    }

    function toggleInformation(): void {
        showInformation = !showInformation;
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

        const handleShown = (): void => {
            titleElement.focus({ preventScroll: true });
        };

        const handleHidden = (): void => {
            const closedAction = activeAction;
            const focusTarget = returnFocusTarget;

            verificationController?.abort();
            verificationController = null;
            requestGeneration += 1;
            activeAction = null;
            pageCount = 0;
            viewerError = null;
            verification = null;
            verificationError = null;
            verificationLoading = false;
            returnFocusTarget = null;

            if (closedAction !== null) {
                dispatchSecurePdfViewerClosed({
                    document_id: closedAction.document_id,
                    resource: closedAction.resource,
                });
            }

            window.requestAnimationFrame(() => {
                if (focusTarget?.isConnected) {
                    focusTarget.focus({ preventScroll: true });
                }
            });
        };

        modalElement.addEventListener('shown.bs.modal', handleShown);
        modalElement.addEventListener('hidden.bs.modal', handleHidden);

        return () => {
            verificationController?.abort();
            requestGeneration += 1;
            modalElement.removeEventListener('shown.bs.modal', handleShown);
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
            modal?.dispose();
            modal = null;
        };
    });
</script>

<div
    bind:this={modalElement}
    class="modal fade secure-pdf-viewer"
    id="securePdfViewerModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="securePdfViewerTitle"
    aria-describedby="securePdfViewerDescription"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down" role="document">
        <div class="modal-content secure-pdf-shell">
            <header class="modal-header secure-pdf-shell__header">
                <div class="secure-pdf-shell__identity">
                    <span class="secure-pdf-shell__icon"><i class="fas fa-file-pdf" aria-hidden="true"></i></span>
                    <div>
                        <p class="secure-pdf-shell__eyebrow mb-0">Tampilan Dokumen</p>
                        <h2 bind:this={titleElement} class="modal-title" id="securePdfViewerTitle" tabindex="-1">
                            {activeAction?.title ?? 'Dokumen PDF'}
                        </h2>
                        <p class="secure-pdf-shell__description mb-0" id="securePdfViewerDescription">
                            {#if activeAction}
                                {[activeAction.payment_type, activeAction.document_type].filter(Boolean).join(' · ') || 'Dokumen SITANGKAS'}
                            {:else}
                                Dokumen SITANGKAS
                            {/if}
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" aria-label="Tutup tampilan dokumen" onclick={requestClose}>×</button>
            </header>

            <div class="modal-body secure-pdf-shell__body">
                {#if activeAction}
                    <div class:secure-pdf-layout--with-information={showInformation} class="secure-pdf-layout">
                        <PdfDocumentViewport
                            contentUrl={activeAction.actions.view.url ?? ''}
                            downloadUrl={activeAction.actions.download.allowed
                                ? activeAction.actions.download.url
                                : null}
                            {showInformation}
                            onInformationToggle={toggleInformation}
                            onReady={(count) => {
                                pageCount = count;
                                viewerError = null;
                            }}
                            onError={(message) => viewerError = message}
                        />

                        {#if showInformation}
                            <PdfDocumentInformation
                                action={activeAction}
                                {pageCount}
                                {verification}
                                {verificationLoading}
                                {verificationError}
                                onClose={closeInformation}
                            />
                        {/if}
                    </div>
                {/if}
            </div>

            {#if viewerError}
                <footer class="modal-footer secure-pdf-shell__footer" role="status">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>{viewerError}</span>
                </footer>
            {/if}
        </div>
    </div>
</div>
