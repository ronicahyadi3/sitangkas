<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { EsignApiClient } from '../api/client';
    import { EsignApiError, invalidResponseError, normalizeRequestError } from '../api/errors';
    import { dispatchEsignClosed, dispatchEsignCompleted } from '../events';
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
        ArtifactVerification,
        EsignAttemptDetails,
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
    import VerificationPanel from './VerificationPanel.svelte';

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

    interface RecoverableAttempt {
        accepted: AcceptedEsignAttempt;
        details: EsignAttemptDetails | null;
    }

    let { configuration }: { configuration: EsignFrontendConfiguration } = $props();

    let apiClient = $derived(new EsignApiClient(configuration));
    const recoverableAttempts = new Map<string, RecoverableAttempt>();
    const completedAttemptIds = new Set<string>();

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
    let attemptDetails = $state<EsignAttemptDetails | null>(null);
    let pollingError = $state<NormalizedEsignError | null>(null);
    let resumeError = $state<NormalizedEsignError | null>(null);
    let resumeRetryRemaining = $state(0);
    let validationResult = $state<ArtifactVerification | null>(null);
    let validationError = $state<NormalizedEsignError | null>(null);
    let validationLoading = $state(false);
    let submitError = $state<NormalizedEsignError | null>(null);
    let submitIdempotencyKey = $state<string | null>(null);
    let submitRetryRemaining = $state(0);
    let resetConfirmationPending = $state(false);
    let modalElement: HTMLDivElement;
    let modalTitleElement: HTMLHeadingElement;
    let modal: BootstrapModal | null = null;
    let returnFocusTarget: HTMLElement | null = null;
    let requestController: AbortController | null = null;
    let attemptPollController: AbortController | null = null;
    let attemptPollTimer: ReturnType<typeof setTimeout> | null = null;
    let submitRetryTimer: ReturnType<typeof setInterval> | null = null;
    let resumeRetryTimer: ReturnType<typeof setInterval> | null = null;
    let requestSequence = 0;
    let attemptPollGeneration = 0;
    let attemptPollFailureCount = 0;

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
    let finalSubmitReady = $derived(
        preparedReady
        && passphrase.length > 0
        && passphrase.length <= 255
        && submitRetryRemaining === 0,
    );
    let validationBadge = $derived.by(() => {
        if (validationLoading) {
            return { className: 'bg-gradient-info', label: 'Memvalidasi' };
        }

        if (validationError !== null) {
            return { className: 'bg-gradient-warning', label: 'Tidak tersedia' };
        }

        if (validationResult?.verification.status === 'valid') {
            return { className: 'bg-gradient-success', label: 'Valid' };
        }

        if (validationResult?.verification.status === 'invalid') {
            return { className: 'bg-gradient-danger', label: 'Tidak valid' };
        }

        return { className: 'bg-gradient-warning', label: 'Tanpa tanda tangan' };
    });

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
            const recoverable = recoverableAttempts.get(action.detail.step_public_id);

            if (recoverable) {
                restoreAttempt(recoverable);
            } else {
                await loadSigningSession(action);
            }
        } else {
            await loadArtifactVerification(action);
        }
    }

    async function loadArtifactVerification(
        action: Extract<EsignUiAction, { kind: 'validation' }>,
    ): Promise<void> {
        requestController?.abort();

        const controller = new AbortController();
        const sequence = ++requestSequence;
        requestController = controller;
        validationLoading = true;
        validationError = null;

        try {
            const result = await apiClient.showArtifactVerification(
                action.detail.verification_url,
                controller.signal,
            );

            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            if (result.artifact.public_id !== action.detail.artifact_public_id
                || absoluteUrl(result.preview_url) !== absoluteUrl(action.detail.preview_url)) {
                throw new EsignApiError({
                    ...invalidResponseError(),
                    message: 'Respons validasi tidak cocok dengan artifact yang dipilih.',
                    code: 'artifact_verification_identity_mismatch',
                });
            }

            validationResult = result;
        } catch (error: unknown) {
            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            validationResult = null;
            validationError = normalizeRequestError(error);
        } finally {
            if (sequence === requestSequence) {
                validationLoading = false;
            }

            if (requestController === controller) {
                requestController = null;
            }
        }
    }

    function retryArtifactVerification(): void {
        if (activeAction?.kind !== 'validation' || validationLoading) {
            return;
        }

        void loadArtifactVerification(activeAction);
    }

    function absoluteUrl(value: string): string {
        return new URL(value, window.location.origin).toString();
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
            'load_failed',
        ].includes(closingStage)) {
            return;
        }

        void apiClient.closeSigningSession(session.session_url).catch(() => undefined);
    }

    function requestClose(): void {
        if (closeBlocked) {
            closeFeedback = stage === 'submitting'
                ? 'Permintaan TTE sedang dikirim. Tunggu hingga server menerima permintaan.'
                : stage === 'resuming'
                    ? 'Passphrase baru sedang dikirim. Tunggu hingga server menerima permintaan.'
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

    function clearSubmitRetry(): void {
        if (submitRetryTimer !== null) {
            clearInterval(submitRetryTimer);
            submitRetryTimer = null;
        }

        submitRetryRemaining = 0;
    }

    function scheduleSubmitRetry(seconds: number | null): void {
        clearSubmitRetry();

        if (seconds === null || seconds <= 0) {
            return;
        }

        const retryAt = Date.now() + (seconds * 1000);
        const update = (): void => {
            submitRetryRemaining = Math.max(0, Math.ceil((retryAt - Date.now()) / 1000));

            if (submitRetryRemaining === 0) {
                clearSubmitRetry();
            }
        };

        update();
        submitRetryTimer = setInterval(update, 500);
    }

    function clearResumeRetry(): void {
        if (resumeRetryTimer !== null) {
            clearInterval(resumeRetryTimer);
            resumeRetryTimer = null;
        }

        resumeRetryRemaining = 0;
    }

    function scheduleResumeRetry(seconds: number | null): void {
        clearResumeRetry();

        if (seconds === null || seconds <= 0) {
            return;
        }

        const retryAt = Date.now() + (seconds * 1000);
        const update = (): void => {
            resumeRetryRemaining = Math.max(0, Math.ceil((retryAt - Date.now()) / 1000));

            if (resumeRetryRemaining === 0) {
                clearResumeRetry();
            }
        };

        update();
        resumeRetryTimer = setInterval(update, 500);
    }

    function stopAttemptPolling(): void {
        attemptPollGeneration += 1;

        if (attemptPollTimer !== null) {
            clearTimeout(attemptPollTimer);
            attemptPollTimer = null;
        }

        attemptPollController?.abort();
        attemptPollController = null;
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
        attemptDetails = null;
        pollingError = null;
        resumeError = null;
        submitError = null;
        submitIdempotencyKey = null;
        validationResult = null;
        validationError = null;
        validationLoading = false;
        clearSubmitRetry();
        clearResumeRetry();
        stopAttemptPolling();
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
        clearSubmitRetry();
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
        clearSubmitRetry();
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

    function activeStepPublicId(): string | null {
        return activeAction?.kind === 'signing'
            ? activeAction.detail.step_public_id
            : null;
    }

    function rememberAttempt(): void {
        const stepPublicId = activeStepPublicId();

        if (stepPublicId === null || acceptedAttempt === null) {
            return;
        }

        recoverableAttempts.set(stepPublicId, {
            accepted: acceptedAttempt,
            details: attemptDetails,
        });
    }

    function forgetRememberedAttempt(): void {
        const stepPublicId = activeStepPublicId();

        if (stepPublicId !== null) {
            recoverableAttempts.delete(stepPublicId);
        }
    }

    function attemptStage(details: EsignAttemptDetails): EsignShellStage {
        if (details.requires_reconciliation || details.status === 'unknown') {
            return 'unknown';
        }

        if (details.requires_passphrase && details.resume_url !== null) {
            return 'requires_passphrase';
        }

        if (details.status === 'partially_signed') {
            return 'unknown';
        }

        if (details.status === 'succeeded') {
            return details.result_artifact_id === null ? 'unknown' : 'succeeded';
        }

        if (details.status === 'failed') {
            return 'failed';
        }

        return 'processing';
    }

    function dispatchAttemptCompleted(details: EsignAttemptDetails): void {
        const stepPublicId = activeStepPublicId();
        const resultArtifactId = details.result_artifact_id;

        if (stepPublicId === null
            || resultArtifactId === null
            || completedAttemptIds.has(details.attempt_id)) {
            return;
        }

        completedAttemptIds.add(details.attempt_id);
        dispatchEsignCompleted({
            step_public_id: stepPublicId,
            attempt_id: details.attempt_id,
            result_artifact_id: resultArtifactId,
            result: 'succeeded',
        });
    }

    function applyAttemptDetails(details: EsignAttemptDetails): boolean {
        const attempt = acceptedAttempt;

        if (attempt === null || attempt.attempt_id !== details.attempt_id) {
            throw new EsignApiError({
                ...invalidResponseError(),
                message: 'Status attempt tidak cocok dengan permintaan TTE aktif.',
                code: 'attempt_identity_mismatch',
            });
        }

        attemptDetails = details;
        pollingError = null;
        attemptPollFailureCount = 0;
        stage = attemptStage(details);
        rememberAttempt();

        if (details.status === 'succeeded') {
            if (details.result_artifact_id === null) {
                pollingError = {
                    ...invalidResponseError(),
                    message: 'TTE selesai tetapi artifact hasil belum tersedia. Status perlu diperiksa administrator.',
                    code: 'result_artifact_missing',
                };
                stage = 'unknown';
                rememberAttempt();

                return false;
            }

            forgetRememberedAttempt();
            dispatchAttemptCompleted(details);

            return false;
        }

        if (details.status === 'failed') {
            forgetRememberedAttempt();

            return false;
        }

        if (stage === 'unknown' || stage === 'requires_passphrase') {
            return false;
        }

        if (details.next_poll_after_ms === null) {
            pollingError = {
                ...invalidResponseError(),
                message: 'Backend tidak memberikan jadwal pembaruan untuk attempt yang masih berjalan.',
                code: 'attempt_poll_schedule_missing',
            };
            stage = 'unknown';
            rememberAttempt();

            return false;
        }

        return true;
    }

    function scheduleAttemptPoll(statusUrl: string, delayMs: number, generation: number): void {
        if (generation !== attemptPollGeneration || document.visibilityState === 'hidden') {
            return;
        }

        if (attemptPollTimer !== null) {
            clearTimeout(attemptPollTimer);
        }

        attemptPollTimer = setTimeout(() => {
            attemptPollTimer = null;
            void pollAttempt(statusUrl, generation);
        }, Math.max(0, delayMs));
    }

    async function pollAttempt(statusUrl: string, generation: number): Promise<void> {
        if (generation !== attemptPollGeneration || document.visibilityState === 'hidden') {
            return;
        }

        const controller = new AbortController();
        attemptPollController = controller;

        try {
            const details = await apiClient.showAttempt(statusUrl, controller.signal);

            if (controller.signal.aborted || generation !== attemptPollGeneration) {
                return;
            }

            const shouldContinue = applyAttemptDetails(details);

            if (shouldContinue && details.next_poll_after_ms !== null) {
                scheduleAttemptPoll(
                    statusUrl,
                    Math.max(500, details.next_poll_after_ms),
                    generation,
                );
            }
        } catch (error: unknown) {
            if (controller.signal.aborted || generation !== attemptPollGeneration) {
                return;
            }

            const normalized = normalizeRequestError(error);
            const retryableRead = ['network', 'server', 'rate_limited'].includes(normalized.category);

            pollingError = normalized;

            if (retryableRead) {
                attemptPollFailureCount += 1;
                const retryDelay = normalized.retry_after_seconds !== null
                    ? normalized.retry_after_seconds * 1000
                    : Math.min(15_000, 2_000 * (2 ** Math.min(3, attemptPollFailureCount - 1)));

                scheduleAttemptPoll(statusUrl, Math.max(1_000, retryDelay), generation);
            } else {
                stage = 'unknown';
                rememberAttempt();
            }
        } finally {
            if (attemptPollController === controller) {
                attemptPollController = null;
            }
        }
    }

    function startAttemptPolling(statusUrl: string): void {
        stopAttemptPolling();
        attemptPollFailureCount = 0;
        const generation = attemptPollGeneration;

        scheduleAttemptPoll(statusUrl, 0, generation);
    }

    function startAttemptTracking(attempt: AcceptedEsignAttempt): void {
        acceptedAttempt = attempt;
        attemptDetails = null;
        pollingError = null;
        resumeError = null;
        clearResumeRetry();
        stage = 'processing';
        rememberAttempt();
        startAttemptPolling(attempt.status_url);
    }

    function restoreAttempt(recoverable: RecoverableAttempt): void {
        acceptedAttempt = recoverable.accepted;
        attemptDetails = recoverable.details;
        pollingError = null;
        resumeError = null;
        stage = recoverable.details === null
            ? 'processing'
            : attemptStage(recoverable.details);
        startAttemptPolling(recoverable.accepted.status_url);
    }

    async function resumePartialAttempt(secret: string): Promise<void> {
        const details = attemptDetails;
        const attempt = acceptedAttempt;

        if (stage !== 'requires_passphrase'
            || details === null
            || attempt === null
            || details.resume_url === null
            || details.requires_reconciliation
            || secret.length < 1
            || secret.length > 255
            || resumeRetryRemaining > 0) {
            return;
        }

        stopAttemptPolling();
        const controller = new AbortController();
        const sequence = ++requestSequence;

        requestController?.abort();
        requestController = controller;
        resumeError = null;
        clearResumeRetry();
        closeFeedback = '';
        stage = 'resuming';

        try {
            const resumed = await apiClient.resumeAttempt(
                details.resume_url,
                { affirmed: true, passphrase: secret },
                controller.signal,
            );

            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            if (resumed.attempt_id !== attempt.attempt_id) {
                throw new EsignApiError({
                    ...invalidResponseError(),
                    message: 'Respons resume tidak cocok dengan attempt yang sedang ditampilkan.',
                    code: 'resume_attempt_identity_mismatch',
                });
            }

            startAttemptTracking(resumed);
        } catch (error: unknown) {
            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            const normalized = normalizeRequestError(error);

            resumeError = normalized;
            scheduleResumeRetry(
                normalized.category === 'rate_limited'
                    ? normalized.retry_after_seconds
                    : null,
            );

            if (normalized.category === 'validation' || normalized.category === 'rate_limited') {
                stage = 'requires_passphrase';
            } else {
                pollingError = normalized;
                stage = 'unknown';
                rememberAttempt();
            }
        } finally {
            secret = '';

            if (requestController === controller) {
                requestController = null;
            }
        }
    }

    async function submitFinalSignature(): Promise<void> {
        const session = signingSession;
        const rendition = preparedRendition;

        if (stage !== 'confirming_prepared'
            || session === null
            || rendition === null
            || !preparedReady
            || passphrase.length === 0
            || passphrase.length > 255
            || submitRetryRemaining > 0) {
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
        clearSubmitRetry();
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

            passphrase = '';
            clearSubmitRetry();
            startAttemptTracking(attempt);
        } catch (error: unknown) {
            if (controller.signal.aborted || sequence !== requestSequence) {
                return;
            }

            const normalized = normalizeRequestError(error);

            passphrase = '';
            acceptedAttempt = null;
            submitError = normalized;
            scheduleSubmitRetry(
                normalized.category === 'rate_limited'
                    ? normalized.retry_after_seconds
                    : null,
            );

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

        const handleVisibilityChange = (): void => {
            if (document.visibilityState === 'hidden') {
                stopAttemptPolling();

                return;
            }

            if (acceptedAttempt !== null && [
                'processing',
                'requires_passphrase',
                'unknown',
            ].includes(stage)) {
                startAttemptPolling(acceptedAttempt.status_url);
            }
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
        document.addEventListener('visibilitychange', handleVisibilityChange);

        if (activeAction !== null) {
            modal.show();
        }

        return () => {
            const sessionToForget = signingSession;
            const closingStage = stage;

            abortActiveRequest();
            clearSubmitRetry();
            clearResumeRetry();
            stopAttemptPolling();
            modalElement.removeEventListener('hide.bs.modal', handleHide);
            modalElement.removeEventListener('shown.bs.modal', handleShown);
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
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
                        class="badge {isValidation ? validationBadge.className : presentation.badgeClass}"
                        aria-live="polite"
                    >
                        {isValidation ? validationBadge.label : presentation.label}
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
                    {#if activeAction?.kind === 'validation'}
                        <VerificationPanel
                            action={activeAction.detail}
                            result={validationResult}
                            loading={validationLoading}
                            error={validationError}
                            {fetchPdf}
                        />
                    {/if}
                {:else}
                    <SigningShellBody
                        {stage}
                        session={signingSession}
                        error={loadError}
                        {fetchPdf}
                        {fetchPng}
                        {preparedRendition}
                        {acceptedAttempt}
                        {attemptDetails}
                        {pollingError}
                        {resumeError}
                        {resumeRetryRemaining}
                        {submitError}
                        onResume={resumePartialAttempt}
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
                {#if isValidation}
                    {#if validationError !== null}
                        <button
                            type="button"
                            class="btn btn-outline-primary mb-0"
                            disabled={validationLoading}
                            onclick={retryArtifactVerification}
                        >
                            <i class="fas fa-rotate me-2" aria-hidden="true"></i>
                            Coba Lagi
                        </button>
                    {/if}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={requestClose}>
                        Tutup
                    </button>
                {:else if stage === 'loading' || stage === 'load_failed'}
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
                {:else if stage === 'preparing_rendition' || stage === 'submitting' || stage === 'resuming'}
                    <button type="button" class="btn bg-gradient-primary mb-0" disabled>
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {stage === 'resuming' ? 'Melanjutkan TTE' : 'Mohon tunggu'}
                    </button>
                {:else if stage === 'confirming_prepared'}
                    <button type="button" class="btn btn-outline-secondary mb-0" onclick={returnToEditor}>
                        Kembali Edit
                    </button>
                    <button
                        type="button"
                        class="btn bg-gradient-primary mb-0"
                        disabled={!finalSubmitReady}
                        title={!preparedReady
                            ? 'Tunggu preview final selesai diverifikasi'
                            : submitRetryRemaining > 0
                                ? `Tunggu ${submitRetryRemaining} detik sebelum mencoba kembali`
                            : passphrase.length === 0
                                ? 'Masukkan passphrase'
                                : passphrase.length > 255
                                    ? 'Passphrase maksimal 255 karakter'
                                : `Kirim ${preparedRendition?.signature_count ?? 0} operasi TTE ke server`}
                        onclick={submitFinalSignature}
                    >
                        {submitRetryRemaining > 0
                            ? `Tunggu ${submitRetryRemaining} detik`
                            : 'Tandatangani Sekarang'}
                    </button>
                {:else if stage === 'processing' || stage === 'requires_passphrase' || stage === 'unknown'}
                    <p class="esign-shell__footer-note mb-0">
                        {stage === 'processing'
                            ? 'Proses tetap berjalan di server meskipun modal ditutup.'
                            : stage === 'requires_passphrase'
                                ? 'Operasi yang sudah selesai tidak akan diulang.'
                                : 'Tidak ada retry otomatis selama status belum pasti.'}
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
