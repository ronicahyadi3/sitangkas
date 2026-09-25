<script lang="ts">
    import type {
        AcceptedEsignAttempt,
        EsignAttemptDetails,
        FooterPlan,
        NormalizedEsignError,
        PreparedSigningRendition,
        SignaturePlacement,
        SignaturePlacementTarget,
        SigningSession,
    } from '../types';
    import type { EsignShellStage } from '../ui-state';
    import PdfViewer from './PdfViewer.svelte';
    import AttemptProgressPanel from './AttemptProgressPanel.svelte';
    import PreparedConfirmation from './PreparedConfirmation.svelte';

    let {
        stage,
        session,
        error,
        fetchPdf,
        fetchPng,
        preparedRendition,
        acceptedAttempt,
        attemptDetails,
        pollingError,
        resumeError,
        resumeRetryRemaining,
        submitError,
        onResume,
        onAddSignature,
        onResetPlacements,
        placements = $bindable(),
        footerPlan = $bindable(null),
        activeEditorPage = $bindable(1),
        selectedPlacementId = $bindable(null),
        selectedFooterPage = $bindable(null),
        editorReady = $bindable(false),
        preparedReady = $bindable(false),
        passphrase = $bindable(''),
    }: {
        stage: EsignShellStage;
        session: SigningSession | null;
        error: NormalizedEsignError | null;
        fetchPdf: (url: string, signal?: AbortSignal) => Promise<ArrayBuffer>;
        fetchPng: (url: string, signal?: AbortSignal) => Promise<Blob>;
        preparedRendition: PreparedSigningRendition | null;
        acceptedAttempt: AcceptedEsignAttempt | null;
        attemptDetails: EsignAttemptDetails | null;
        pollingError: NormalizedEsignError | null;
        resumeError: NormalizedEsignError | null;
        resumeRetryRemaining: number;
        submitError: NormalizedEsignError | null;
        onResume: (passphrase: string) => void;
        onAddSignature: (target?: SignaturePlacementTarget) => void;
        onResetPlacements: () => void;
        placements: SignaturePlacement[];
        footerPlan: FooterPlan | null;
        activeEditorPage: number;
        selectedPlacementId: string | null;
        selectedFooterPage: number | null;
        editorReady: boolean;
        preparedReady: boolean;
        passphrase: string;
    } = $props();

    function errorTitle(category: NormalizedEsignError['category'] | undefined): string {
        if (category === 'authentication') {
            return 'Sesi login perlu diperbarui';
        }

        if (category === 'authorization') {
            return 'Akses TTE tidak tersedia';
        }

        if (category === 'rate_limited') {
            return 'Permintaan terlalu sering';
        }

        if (category === 'network') {
            return 'Server belum dapat dijangkau';
        }

        return 'Sesi TTE tidak dapat dibuka';
    }

    function validationMessages(value: NormalizedEsignError | null): string[] {
        return value === null
            ? []
            : Object.values(value.field_errors).flat().slice(0, 5);
    }

</script>

{#if stage === 'loading'}
    <section class="esign-state esign-state--centered" role="status">
        <span class="spinner-border text-primary" aria-hidden="true"></span>
        <div>
            <h3 class="h6 mb-1">Menyiapkan sesi TTE</h3>
            <p class="text-sm text-secondary mb-0">
                Sistem sedang memeriksa dokumen, penandatangan, dan konfigurasi editor.
            </p>
        </div>
    </section>
{:else if stage === 'load_failed'}
    <section class="esign-result esign-result--danger" role="alert">
        <div class="esign-result__icon" aria-hidden="true">
            <i class="fas fa-triangle-exclamation"></i>
        </div>
        <h3 class="h5 mb-2">{errorTitle(error?.category)}</h3>
        <p class="text-sm text-secondary mb-2">
            {error?.message ?? 'Respons layanan TTE tidak dapat diverifikasi.'}
        </p>
        {#if error?.retry_after_seconds !== null && error?.retry_after_seconds !== undefined}
            <p class="text-xs text-secondary mb-2">
                Coba kembali setelah sekitar {error.retry_after_seconds} detik.
            </p>
        {/if}
        {#if error?.code}
            <p class="text-xs text-secondary mb-2">Kode: {error.code}</p>
        {/if}
        {#if validationMessages(error).length > 0}
            <ul class="esign-error-list text-sm text-start mb-0">
                {#each validationMessages(error) as message}
                    <li>{message}</li>
                {/each}
            </ul>
        {/if}
    </section>
{:else if stage === 'editing'}
    {#if session}
        <PdfViewer
            {session}
            {fetchPdf}
            {onAddSignature}
            {onResetPlacements}
            bind:placements
            bind:footerPlan
            bind:activePage={activeEditorPage}
            bind:selectedPlacementId
            bind:selectedFooterPage
            bind:editorReady
        />
    {:else}
        <section class="esign-result esign-result--danger" role="alert">
            <div class="esign-result__icon" aria-hidden="true">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h3 class="h5 mb-2">Signing session tidak tersedia</h3>
            <p class="text-sm text-secondary mb-0">Tutup modal dan buka kembali proses TTE.</p>
        </section>
    {/if}
{:else if stage === 'preparing_rendition'}
    <section class="esign-state esign-state--centered" role="status">
        <span class="spinner-border text-primary" aria-hidden="true"></span>
        <div>
            <h3 class="h6 mb-1">Menyiapkan dokumen final</h3>
            <p class="text-sm text-secondary mb-0">
                Posisi QR dan footer sedang divalidasi. Jangan tutup modal terlebih dahulu.
            </p>
        </div>
    </section>
{:else if stage === 'confirming_prepared'}
    {#if session && preparedRendition}
        <PreparedConfirmation
            {session}
            rendition={preparedRendition}
            {fetchPdf}
            {fetchPng}
            error={submitError}
            bind:preparedReady
            bind:passphrase
        />
    {:else}
        <section class="esign-result esign-result--danger" role="alert">
            <div class="esign-result__icon" aria-hidden="true">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h3 class="h5 mb-2">Prepared rendition tidak tersedia</h3>
            <p class="text-sm text-secondary mb-0">Kembali ke editor dan siapkan ulang dokumen.</p>
        </section>
    {/if}
{:else if stage === 'submitting'}
    <section class="esign-state esign-state--centered" role="status">
        <span class="spinner-border text-primary" aria-hidden="true"></span>
        <div>
            <h3 class="h6 mb-1">Mengirim permintaan tanda tangan</h3>
            <p class="text-sm text-secondary mb-0">
                Tunggu sampai server menerima permintaan dan memberikan nomor attempt.
            </p>
        </div>
    </section>
{:else if acceptedAttempt && ['processing', 'resuming', 'requires_passphrase', 'unknown', 'succeeded', 'failed'].includes(stage)}
    <AttemptProgressPanel
        {stage}
        attempt={acceptedAttempt}
        details={attemptDetails}
        {pollingError}
        {resumeError}
        {resumeRetryRemaining}
        {onResume}
    />
{:else}
    <section
        class:esign-result--success={stage === 'succeeded'}
        class:esign-result--warning={stage === 'requires_passphrase' || stage === 'unknown'}
        class:esign-result--danger={stage === 'failed'}
        class="esign-result"
        aria-live="assertive"
    >
        <div class="esign-result__icon" aria-hidden="true">
            {#if stage === 'succeeded'}
                <i class="fas fa-check"></i>
            {:else if stage === 'failed'}
                <i class="fas fa-xmark"></i>
            {:else}
                <i class="fas fa-triangle-exclamation"></i>
            {/if}
        </div>
        <h3 class="h5 mb-2">
            {#if stage === 'succeeded'}
                Dokumen berhasil ditandatangani
            {:else if stage === 'requires_passphrase'}
                Proses membutuhkan passphrase kembali
            {:else if stage === 'unknown'}
                Status perlu diperiksa sistem
            {:else}
                Tanda tangan belum berhasil
            {/if}
        </h3>
        <p class="text-sm text-secondary mb-0">
            {submitError?.message ?? 'Ringkasan hasil authoritative akan ditampilkan dari status attempt backend.'}
        </p>
        {#if submitError?.code}
            <p class="text-xs text-secondary mt-2 mb-0">Kode: {submitError.code}</p>
        {/if}
    </section>
{/if}
