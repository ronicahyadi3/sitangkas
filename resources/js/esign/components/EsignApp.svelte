<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { EsignApiClient } from '../api/client';
    import { EsignApiError, invalidResponseError, normalizeRequestError } from '../api/errors';
    import { dispatchEsignClosed } from '../events';
    import {
        createDefaultFooterPlan,
        resetFooterPlacements,
        validateFooterPlan,
    } from '../geometry/footer';
    import {
        createDefaultSignaturePlacement,
        normalizeSignatureOperationIndexes,
        resetSignaturePlacements,
    } from '../geometry/placement';
    import { serializeFooterPlan, serializeSignaturePlan } from '../geometry/serialization';
    import { validateGeometryPlan } from '../geometry/validation';
    import {
        stageBlocksClose,
        stageIsBusy,
        stagePresentation,
        type EsignShellStage,
    } from '../ui-state';
    import type {
        AcceptedEsignAttempt,
        EsignFrontendConfiguration,
        EsignUiAction,
        FooterPlan,
        NormalizedEsignError,
        PreparedSigningRendition,
        SignaturePlacement,
        SigningSession,
    } from '../types';
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

    let { configuration }: { configuration: EsignFrontendConfiguration } = $props();

    let apiClient = $derived(new EsignApiClient(configuration));

    let activeAction = $state<EsignUiAction | null>(null);
    let stage = $state<EsignShellStage>('loading');
    let signingSession = $state<SigningSession | null>(null);
    let loadError = $state<NormalizedEsignError | null>(null);
    let closeFeedback = $state('');
    let editorFeedback = $state('');
    let placements = $state<SignaturePlacement[]>([]);
    let footerPlan = $state<FooterPlan | null>(null);
    let activeEditorPage = $state(1);
    let selectedPlacementId = $state<string | null>(null);
    let selectedFooterPage = $state<number | null>(null);
    let editorReady = $state(false);
    let preparedRendition = $state<PreparedSigningRendition | null>(null);
    let preparedReady = $state(false);
    let passphrase = $state('');
    let acceptedAttempt = $state<AcceptedEsignAttempt | null>(null);
    let submitError = $state<NormalizedEsignError | null>(null);
    let submitIdempotencyKey = $state<string | null>(null);
    let resetConfirmationPending = $state(false);
    let modalElement: HTMLDivElement;
    let modalTitleElement: HTMLHeadingElement;
    let modal: BootstrapModal | null = null;
    let returnFocusTarget: HTMLElement | null = null;
    let requestController: AbortController | null = null;
    let requestSequence = 0;

    let isValidation = $derived(activeAction?.kind === 'validation');
    let presentation = $derived(stagePresentation(stage));
    let closeBlocked = $derived(!isValidation && stageBlocksClose(stage));
    let busy = $derived(!isValidation && stageIsBusy(stage));
    let signingPlanIssues = $derived.by(() => {
        const session = signingSession;

        if (session === null) {
            return ['Signing session belum tersedia.'];
        }

        const geometry = validateGeometryPlan(
            placements,
            footerPlan?.placements ?? [],
            session.pages,
            session.editor,
        );
        const footer = validateFooterPlan(footerPlan, session.pages, session.editor);

        return [
            ...geometry.issues.map((issue) => issue.message),
            ...footer.map((issue) => issue.message),
        ];
    });
    let signingPlanReady = $derived(editorReady && signingPlanIssues.length === 0);

    export async function open(action: EsignUiAction): Promise<void> {
        if (closeBlocked) {
            closeFeedback = 'Selesaikan proses yang sedang berjalan sebelum membuka dokumen lain.';

            return;
        }

        const previousSession = signingSession;
        const previousStage = stage;

        abortActiveRequest();
        forgetTemporarySession(previousSession, previousStage);
        returnFocusTarget = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        activeAction = action;
        stage = 'loading';
        signingSession = null;
        loadError = null;
        closeFeedback = '';
        resetEditorState();

        await tick();
        modal?.show();

        if (action.kind === 'signing') {
            await loadSigningSession(action);
        }
    }

    async function loadSigningSession(action: Extract<EsignUiAction, { kind: 'signing' }>): Promise<void> {
        requestController?.abort();

        const controller = new AbortController();
        const sequence = ++requestSequence;
        requestController = controller;

        try {
            const session = await apiClient.createSigningSession(
                action.detail.step_public_id,
                controller.signal,
            );

            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            signingSession = session;
            loadError = null;
            resetEditorState();
            stage = 'editing';
        } catch (error: unknown) {
            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            signingSession = null;
            loadError = normalizeRequestError(error);
            stage = 'load_failed';
        } finally {
            if (requestController === controller) {
                requestController = null;
            }
        }
    }

    function abortActiveRequest(): void {
        requestSequence += 1;
        requestController?.abort();
        requestController = null;
    }

    function forgetTemporarySession(session: SigningSession | null, closingStage: EsignShellStage): void {
        if (session === null || ![
            'editing',
            'preparing_rendition',
            'confirming_prepared',
        ].includes(closingStage)) {
            return;
        }

        void apiClient.closeSigningSession(session.session_url).catch(() => undefined);
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

    function fetchPdf(url: string, signal?: AbortSignal): Promise<ArrayBuffer> {
        return apiClient.fetchPdf(url, signal);
    }

    function fetchPng(url: string, signal?: AbortSignal): Promise<Blob> {
        return apiClient.fetchPng(url, signal);
    }

    function resetEditorState(): void {
        placements = [];
        footerPlan = null;
        activeEditorPage = 1;
        selectedPlacementId = null;
        selectedFooterPage = null;
        editorReady = false;
        preparedRendition = null;
        preparedReady = false;
        passphrase = '';
        acceptedAttempt = null;
        submitError = null;
        submitIdempotencyKey = null;
        editorFeedback = '';
        resetConfirmationPending = false;
    }

    function sameNumber(left: number, right: number): boolean {
        return Math.abs(left - right) <= 0.0001;
    }

    function sameGeometry(
        left: SignaturePlacement | FooterPlan['placements'][number],
        right: SignaturePlacement | FooterPlan['placements'][number],
    ): boolean {
        return left.page === right.page
            && left.page_rotation === right.page_rotation
            && sameNumber(left.page_width, right.page_width)
            && sameNumber(left.page_height, right.page_height)
            && sameNumber(left.origin_x, right.origin_x)
            && sameNumber(left.origin_y, right.origin_y)
            && sameNumber(left.width, right.width)
            && sameNumber(left.height, right.height);
    }

    function preparedMatchesPlan(
        rendition: PreparedSigningRendition,
        expectedPlacements: SignaturePlacement[],
        expectedFooter: FooterPlan | null,
    ): boolean {
        const operations = [...rendition.signature_operations]
            .sort((left, right) => left.operation_index - right.operation_index);

        if (rendition.signature_count !== expectedPlacements.length
            || operations.length !== expectedPlacements.length
            || operations.some((operation, index) => {
                const expected = expectedPlacements[index];

                return expected === undefined
                    || operation.client_id !== expected.client_id
                    || operation.operation_index !== expected.operation_index
                    || !sameGeometry(operation, expected);
            })) {
            return false;
        }

        if (expectedFooter === null || rendition.footer === null) {
            return expectedFooter === null && rendition.footer === null;
        }

        const actualFooter = rendition.footer;
        const actualPlacements = [...actualFooter.placements].sort((left, right) => left.page - right.page);

        return actualFooter.text === expectedFooter.text
            && actualFooter.font_key === expectedFooter.font_key
            && sameNumber(actualFooter.font_size_pt, expectedFooter.font_size_pt)
            && actualFooter.is_bold === expectedFooter.is_bold
            && actualFooter.is_italic === expectedFooter.is_italic
            && actualFooter.is_underline === expectedFooter.is_underline
            && actualPlacements.length === expectedFooter.placements.length
            && actualPlacements.every((placement, index) => {
                const expected = expectedFooter.placements[index];

                return expected !== undefined && sameGeometry(placement, expected);
            });
    }

    function focusBackendValidation(error: NormalizedEsignError): void {
        const field = Object.keys(error.field_errors)[0];
        const placementMatch = field?.match(/^placements\.(\d+)/u);
        const footerMatch = field?.match(/^footer\.placements\.(\d+)/u);

        if (placementMatch) {
            const index = Number(placementMatch[1]);
            const placement = placements[index];

            if (placement) {
                activeEditorPage = placement.page;
                selectedPlacementId = placement.client_id;
                selectedFooterPage = null;
            }
        } else if (footerMatch && footerPlan !== null) {
            const index = Number(footerMatch[1]);
            const placement = footerPlan.placements[index];

            if (placement) {
                activeEditorPage = placement.page;
                selectedPlacementId = null;
                selectedFooterPage = placement.page;
            }
        }
    }

    async function prepareRendition(): Promise<void> {
        const session = signingSession;

        if (session === null || !signingPlanReady) {
            editorFeedback = signingPlanIssues[0] ?? 'Rencana tanda tangan belum valid.';
            return;
        }

        const canonicalPlacements = serializeSignaturePlan(placements);
        const canonicalFooter = serializeFooterPlan(footerPlan);
        const controller = new AbortController();
        const sequence = ++requestSequence;

        requestController?.abort();
        requestController = controller;
        preparedRendition = null;
        preparedReady = false;
        passphrase = '';
        acceptedAttempt = null;
        submitError = null;
        submitIdempotencyKey = null;
        editorFeedback = '';
        closeFeedback = '';
        stage = 'preparing_rendition';

        try {
            const rendition = await apiClient.prepareSigningRendition(
                session.prepare_rendition_url,
                {
                    placements: canonicalPlacements,
                    footer: canonicalFooter,
                },
                controller.signal,
            );

            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            if (!preparedMatchesPlan(rendition, canonicalPlacements, canonicalFooter)) {
                throw new EsignApiError({
                    ...invalidResponseError(),
                    message: 'Prepared preview tidak sama dengan rencana posisi yang dikirim.',
                    code: 'prepared_plan_mismatch',
                });
            }

            placements = canonicalPlacements;
            footerPlan = canonicalFooter;
            preparedRendition = rendition;
            stage = 'confirming_prepared';
        } catch (error: unknown) {
            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            const normalized = normalizeRequestError(error);
            const firstValidationMessage = Object.values(normalized.field_errors).flat()[0];

            focusBackendValidation(normalized);
            editorFeedback = firstValidationMessage ?? normalized.message;
            preparedRendition = null;
            preparedReady = false;
            passphrase = '';
            stage = 'editing';
        } finally {
            if (requestController === controller) {
                requestController = null;
            }
        }
    }

    function returnToEditor(): void {
        preparedRendition = null;
        preparedReady = false;
        passphrase = '';
        acceptedAttempt = null;
        submitError = null;
        submitIdempotencyKey = null;
        closeFeedback = '';
        editorFeedback = 'Preview final dibatalkan. Siapkan ulang setelah posisi selesai diperiksa.';
        stage = 'editing';
    }

    function outcomeIsUnclear(error: NormalizedEsignError): boolean {
        return ['network', 'server', 'invalid_response'].includes(error.category)
            || error.code === 'esign.idempotency_payload_mismatch';
    }

    function requiresNewSigningSession(error: NormalizedEsignError): boolean {
        return ['authentication', 'authorization', 'not_found'].includes(error.category)
            || [
                'esign.signing_session_expired',
                'esign.signing_session_invalid',
                'esign.signing_session_context_changed',
                'esign.signing_session_role_changed',
                'esign.source_artifact_changed',
                'esign.visible_placement_not_required',
                'esign.prepared_revision_not_allowed',
                'esign.prepared_revision_required',
            ].includes(error.code ?? '');
    }

    function requiresNewPreparedRendition(error: NormalizedEsignError): boolean {
        return [
            'esign.prepared_rendition_not_found',
            'esign.prepared_rendition_changed',
            'esign.prepared_rendition_context_mismatch',
            'esign.preview_hash_mismatch',
        ].includes(error.code ?? '');
    }

    async function submitFinalSignature(): Promise<void> {
        const session = signingSession;
        const rendition = preparedRendition;

        if (stage !== 'confirming_prepared'
            || session === null
            || rendition === null
            || !preparedReady
            || passphrase.length === 0) {
            return;
        }

        const secret = passphrase;
        const idempotencyKey = submitIdempotencyKey ?? crypto.randomUUID();
        const controller = new AbortController();
        const sequence = ++requestSequence;

        requestController?.abort();
        requestController = controller;
        submitIdempotencyKey = idempotencyKey;
        submitError = null;
        closeFeedback = '';
        stage = 'submitting';

        try {
            const attempt = await apiClient.signDocument(
                session.sign_url,
                {
                    affirmed: true,
                    idempotency_key: idempotencyKey,
                    passphrase: secret,
                    prepared_revision: rendition.revision,
                    preview_sha256: rendition.sha256,
                },
                controller.signal,
            );

            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            acceptedAttempt = attempt;
            passphrase = '';
            stage = 'processing';
        } catch (error: unknown) {
            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            const normalized = normalizeRequestError(error);

            passphrase = '';
            acceptedAttempt = null;
            submitError = normalized;

            if (outcomeIsUnclear(normalized)) {
                stage = 'unknown';
            } else if (requiresNewSigningSession(normalized)) {
                preparedRendition = null;
                preparedReady = false;
                submitIdempotencyKey = null;
                loadError = normalized;
                stage = 'load_failed';
            } else if (requiresNewPreparedRendition(normalized)) {
                preparedRendition = null;
                preparedReady = false;
                submitIdempotencyKey = null;
                editorFeedback = normalized.message;
                stage = 'editing';
            } else {
                stage = 'confirming_prepared';
            }
        } finally {
            passphrase = '';

            if (requestController === controller) {
                requestController = null;
            }
        }
    }

    function addSignaturePlacement(): void {
        const session = signingSession;

        if (session === null || placements.length >= session.editor.maximum_signature_count) {
            return;
        }

        const page = session.pages.find((item) => item.page === activeEditorPage);

        if (page === undefined) {
            editorFeedback = 'Halaman aktif tidak tersedia. Pilih halaman lain lalu coba kembali.';
            return;
        }

        const shouldCreateRequiredFooter = placements.length === 0
            && footerPlan === null
            && session.editor.footer.allowed
            && session.editor.footer.required;
        const effectiveFooter = shouldCreateRequiredFooter
            ? createDefaultFooterPlan(session.editor.footer)
            : footerPlan;

        if (shouldCreateRequiredFooter && effectiveFooter === null) {
            editorFeedback = 'Konfigurasi footer wajib dari backend tidak lengkap.';
            return;
        }

        if (shouldCreateRequiredFooter
            && validateFooterPlan(effectiveFooter, session.pages, session.editor).length > 0) {
            editorFeedback = 'Konfigurasi footer wajib dari backend tidak valid untuk dokumen ini.';
            return;
        }

        const placement = createDefaultSignaturePlacement(
            page,
            placements,
            session.editor,
            crypto.randomUUID(),
            effectiveFooter?.placements ?? [],
        );

        if (placement === null) {
            editorFeedback = 'Tidak tersedia ruang aman untuk QR baru pada halaman ini.';
            return;
        }

        placements = normalizeSignatureOperationIndexes([...placements, placement]);
        footerPlan = effectiveFooter;
        selectedPlacementId = placement.client_id;
        selectedFooterPage = null;
        editorFeedback = '';
        resetConfirmationPending = false;
    }

    function requestPlacementReset(): void {
        const session = signingSession;

        if (session === null || placements.length === 0) {
            return;
        }

        if (placements.length > 1 && !resetConfirmationPending) {
            resetConfirmationPending = true;
            editorFeedback = 'Klik “Reset Posisi” sekali lagi untuk mengembalikan seluruh QR ke posisi aman.';
            return;
        }

        const resetFooter = footerPlan === null
            ? null
            : resetFooterPlacements(footerPlan, session.editor.footer);

        placements = resetSignaturePlacements(
            placements,
            session.pages,
            session.editor,
            resetFooter?.placements ?? [],
        );
        footerPlan = resetFooter;
        selectedPlacementId = placements[0]?.client_id ?? null;
        selectedFooterPage = null;
        editorFeedback = 'Posisi QR telah dikembalikan ke susunan aman.';
        resetConfirmationPending = false;
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
            const sessionToForget = signingSession;
            const closingStage = stage;

            abortActiveRequest();
            activeAction = null;
            stage = 'loading';
            signingSession = null;
            loadError = null;
            closeFeedback = '';
            resetEditorState();
            returnFocusTarget = null;
            forgetTemporarySession(sessionToForget, closingStage);
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
            const sessionToForget = signingSession;
            const closingStage = stage;

            abortActiveRequest();
            modalElement.removeEventListener('hide.bs.modal', handleHide);
            modalElement.removeEventListener('shown.bs.modal', handleShown);
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
            modal?.dispose();
            modal = null;
            forgetTemporarySession(sessionToForget, closingStage);
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
                            : signingSession
                                ? `${signingSession.signer_name} · NIK ${signingSession.masked_nik} · ${signingSession.pages.length} halaman`
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
                    <SigningShellBody
                        {stage}
                        session={signingSession}
                        error={loadError}
                        {fetchPdf}
                        {fetchPng}
                        {preparedRendition}
                        {acceptedAttempt}
                        {submitError}
                        bind:placements
                        bind:footerPlan
                        bind:activeEditorPage
                        bind:selectedPlacementId
                        bind:selectedFooterPage
                        bind:editorReady
                        bind:preparedReady
                        bind:passphrase
                    />
                {/if}
            </main>

            <footer class="modal-footer esign-shell__footer">
                {#if isValidation || stage === 'loading' || stage === 'load_failed'}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={requestClose}>
                        {stage === 'load_failed' ? 'Tutup' : 'Batal'}
                    </button>
                {:else if stage === 'editing'}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={requestClose}>
                        Batal
                    </button>
                    {#if editorFeedback !== ''}
                        <p class="esign-shell__editor-feedback mb-0" role="status" aria-live="polite">
                            {editorFeedback}
                        </p>
                    {/if}
                    <div class="esign-shell__footer-actions">
                        <button
                            type="button"
                            class="btn btn-outline-primary mb-0"
                            disabled={placements.length === 0}
                            onclick={requestPlacementReset}
                        >
                            Reset Posisi
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-primary mb-0"
                            disabled={!editorReady || signingSession === null || placements.length >= signingSession.editor.maximum_signature_count}
                            title={signingSession !== null && placements.length >= signingSession.editor.maximum_signature_count
                                ? `Maksimal ${signingSession.editor.maximum_signature_count} QR`
                                : 'Tambahkan QR pada halaman aktif'}
                            onclick={addSignaturePlacement}
                        >
                            Tambah QR{placements.length > 0 ? ` (${placements.length})` : ''}
                        </button>
                        <button
                            type="button"
                            class="btn bg-gradient-primary mb-0"
                            disabled={!signingPlanReady}
                            title={signingPlanReady
                                ? 'Siapkan preview final dari backend'
                                : (signingPlanIssues[0] ?? 'Editor belum siap')}
                            onclick={prepareRendition}
                        >
                            Lanjutkan
                        </button>
                    </div>
                {:else if stage === 'preparing_rendition' || stage === 'submitting'}
                    <button type="button" class="btn bg-gradient-primary mb-0" disabled>
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        Mohon tunggu
                    </button>
                {:else if stage === 'confirming_prepared'}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={returnToEditor}>
                        Kembali Edit
                    </button>
                    <button
                        type="button"
                        class="btn bg-gradient-primary mb-0"
                        disabled={!preparedReady || passphrase.length === 0}
                        title={!preparedReady
                            ? 'Tunggu preview final selesai diverifikasi'
                            : passphrase.length === 0
                                ? 'Masukkan passphrase'
                                : `Kirim ${preparedRendition?.signature_count ?? 0} operasi TTE ke server`}
                        onclick={submitFinalSignature}
                    >
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
