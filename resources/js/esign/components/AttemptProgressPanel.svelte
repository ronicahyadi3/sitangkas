<script lang="ts">
    import { onDestroy } from 'svelte';
    import type {
        AcceptedEsignAttempt,
        EsignAttemptDetails,
        EsignSignatureOperationStatus,
        NormalizedEsignError,
    } from '../types';
    import type { EsignShellStage } from '../ui-state';

    let {
        stage,
        attempt,
        details,
        pollingError,
        resumeError,
        resumeRetryRemaining,
        onResume,
    }: {
        stage: EsignShellStage;
        attempt: AcceptedEsignAttempt;
        details: EsignAttemptDetails | null;
        pollingError: NormalizedEsignError | null;
        resumeError: NormalizedEsignError | null;
        resumeRetryRemaining: number;
        onResume: (passphrase: string) => void;
    } = $props();

    let passphrase = $state('');
    let revealPassphrase = $state(false);
    let revealTimer: ReturnType<typeof setTimeout> | null = null;

    let progressPercentage = $derived.by(() => {
        if (details === null || details.progress.planned < 1) {
            return 0;
        }

        return Math.min(100, Math.round(
            (details.progress.completed / details.progress.planned) * 100,
        ));
    });
    let orderedOperations = $derived.by(() => (
        details === null
            ? []
            : [...details.progress.operations].sort((left, right) => left.index - right.index)
    ));
    let resumeReady = $derived(
        stage === 'requires_passphrase'
        && details?.requires_passphrase === true
        && details.resume_url !== null
        && !details.requires_reconciliation
        && passphrase.length > 0
        && passphrase.length <= 255
        && resumeRetryRemaining === 0,
    );

    function attemptHeading(): string {
        if (details?.error_code === 'esign.invalid_passphrase') {
            return 'Passphrase tidak sesuai';
        }

        if (stage === 'succeeded') {
            return 'Dokumen berhasil ditandatangani';
        }

        if (stage === 'failed') {
            return 'Proses tanda tangan berhenti';
        }

        if (stage === 'unknown') {
            return 'Status membutuhkan pemeriksaan';
        }

        if (stage === 'requires_passphrase') {
            return 'Lanjutkan operasi tanda tangan';
        }

        if (stage === 'resuming') {
            return 'Mengirim passphrase baru';
        }

        return 'Tanda tangan sedang diproses';
    }

    function attemptDescription(): string {
        if (details?.error_code === 'esign.invalid_passphrase') {
            return stage === 'requires_passphrase'
                ? 'BSrE menolak passphrase sebelumnya. Masukkan passphrase yang benar untuk melanjutkan tanpa mengulang QR yang sudah selesai.'
                : 'BSrE menolak passphrase yang digunakan. Mulai ulang TTE dan masukkan passphrase yang benar.';
        }

        if (details?.requires_reconciliation) {
            return 'Sistem menahan retry karena hasil provider belum dapat dipastikan.';
        }

        if (stage === 'succeeded') {
            return 'Seluruh operasi selesai dan artifact hasil sudah dicatat oleh backend.';
        }

        if (stage === 'failed') {
            return details?.retryable
                ? 'Kegagalan tercatat sebagai retryable, tetapi browser tidak akan mengulang otomatis.'
                : 'Kegagalan bersifat terminal untuk attempt ini.';
        }

        if (stage === 'requires_passphrase') {
            return 'Operasi yang selesai tidak akan diulang. Masukkan passphrase baru untuk operasi tersisa.';
        }

        if (stage === 'unknown') {
            return 'Jangan membuat permintaan TTE baru sampai status ini direkonsiliasi.';
        }

        return 'Worker server memproses setiap QR secara berurutan. Modal boleh ditutup.';
    }

    function attemptIcon(): string {
        if (stage === 'succeeded') {
            return 'fas fa-check';
        }

        if (stage === 'failed') {
            return 'fas fa-xmark';
        }

        if (stage === 'unknown' || stage === 'requires_passphrase') {
            return 'fas fa-triangle-exclamation';
        }

        return 'fas fa-spinner fa-spin';
    }

    function operationLabel(status: EsignSignatureOperationStatus): string {
        const labels: Record<EsignSignatureOperationStatus, string> = {
            pending: 'Menunggu',
            signing: 'Sedang ditandatangani',
            output_received: 'Hasil diterima',
            completed: 'Selesai',
            failed: 'Gagal',
            unknown: 'Perlu pemeriksaan',
        };

        return labels[status];
    }

    function operationIcon(status: EsignSignatureOperationStatus): string {
        if (status === 'completed') {
            return 'fas fa-check';
        }

        if (status === 'failed') {
            return 'fas fa-xmark';
        }

        if (status === 'unknown') {
            return 'fas fa-question';
        }

        if (status === 'signing' || status === 'output_received') {
            return 'fas fa-spinner fa-spin';
        }

        return 'fas fa-clock';
    }

    function formatTimestamp(value: string | null): string {
        if (value === null) {
            return '—';
        }

        const date = new Date(value);

        return Number.isNaN(date.getTime())
            ? '—'
            : new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
    }

    function togglePassphraseVisibility(): void {
        revealPassphrase = !revealPassphrase;

        if (revealTimer !== null) {
            clearTimeout(revealTimer);
            revealTimer = null;
        }

        if (revealPassphrase) {
            revealTimer = setTimeout(() => {
                revealPassphrase = false;
                revealTimer = null;
            }, 12_000);
        }
    }

    function submitResume(event: SubmitEvent): void {
        event.preventDefault();

        if (!resumeReady) {
            return;
        }

        const secret = passphrase;

        passphrase = '';
        revealPassphrase = false;
        onResume(secret);
    }

    onDestroy(() => {
        if (revealTimer !== null) {
            clearTimeout(revealTimer);
        }

        passphrase = '';
    });
</script>

<section
    class:esign-attempt-panel--success={stage === 'succeeded'}
    class:esign-attempt-panel--danger={stage === 'failed'}
    class:esign-attempt-panel--warning={stage === 'unknown' || stage === 'requires_passphrase'}
    class="esign-attempt-panel"
    aria-live="polite"
>
    <header class="esign-attempt-panel__header">
        <div class="esign-attempt-panel__icon" aria-hidden="true">
            <i class={attemptIcon()}></i>
        </div>
        <div>
            <h3 class="h5 mb-1">{attemptHeading()}</h3>
            <p class="text-sm text-secondary mb-0">{attemptDescription()}</p>
        </div>
    </header>

    {#if details}
        <div class="esign-attempt-progress">
            <div class="esign-attempt-progress__heading">
                <strong>{details.progress.completed} dari {details.progress.planned} QR selesai</strong>
                <span>{progressPercentage}%</span>
            </div>
            <div
                class="progress esign-progress"
                role="progressbar"
                aria-label="Progres operasi tanda tangan"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow={progressPercentage}
            >
                <div
                    class="progress-bar {stage === 'failed' ? 'bg-gradient-danger' : 'bg-gradient-info'}"
                    style:width={`${progressPercentage}%`}
                ></div>
            </div>
            <div class="esign-attempt-progress__meta">
                <span>Attempt #{details.attempt_number}</span>
                <span>Mulai: {formatTimestamp(details.started_at)}</span>
                {#if details.completed_at}
                    <span>Selesai: {formatTimestamp(details.completed_at)}</span>
                {/if}
            </div>
        </div>

        <ol class="esign-operation-list" aria-label="Status operasi QR">
            {#each orderedOperations as operation (operation.index)}
                <li
                    class:esign-operation-list__item--active={operation.index === details.progress.current_index && operation.status !== 'completed'}
                    class:esign-operation-list__item--completed={operation.status === 'completed'}
                    class:esign-operation-list__item--danger={operation.status === 'failed' || operation.status === 'unknown'}
                    class="esign-operation-list__item"
                >
                    <span class="esign-operation-list__icon" aria-hidden="true">
                        <i class={operationIcon(operation.status)}></i>
                    </span>
                    <span>
                        <strong>QR {operation.index + 1}</strong>
                        <small>{operationLabel(operation.status)}</small>
                    </span>
                    {#if operation.retryable && operation.status !== 'completed'}
                        <span class="badge bg-light text-dark">Dapat dilanjutkan</span>
                    {/if}
                </li>
            {/each}
        </ol>

        {#if details.requires_reconciliation}
            <div class="alert alert-warning esign-attempt-alert mb-0" role="alert">
                <strong class="d-block mb-1">Rekonsiliasi diperlukan</strong>
                Hasil provider ambigu. Retry dan resume dinonaktifkan untuk mencegah tanda tangan ganda.
                {#if details.error_code}
                    <span class="d-block mt-1">Kode: {details.error_code}</span>
                {/if}
            </div>
        {:else if stage === 'requires_passphrase' && details.resume_url}
            <form class="esign-resume-form" onsubmit={submitResume}>
                <div>
                    <h4 class="h6 mb-1">Passphrase diperlukan kembali</h4>
                    <p class="text-xs text-secondary mb-3">
                        {details.progress.completed} operasi yang sudah selesai dipertahankan.
                    </p>
                </div>
                <label class="form-label text-sm" for="esign-resume-passphrase">Passphrase BSrE</label>
                <div class="input-group">
                    <input
                        id="esign-resume-passphrase"
                        class="form-control"
                        type={revealPassphrase ? 'text' : 'password'}
                        bind:value={passphrase}
                        maxlength="255"
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="Masukkan passphrase baru"
                    />
                    <button
                        type="button"
                        class="btn btn-outline-secondary mb-0"
                        disabled={passphrase.length === 0}
                        aria-label={revealPassphrase ? 'Sembunyikan passphrase' : 'Tampilkan passphrase'}
                        aria-pressed={revealPassphrase}
                        onclick={togglePassphraseVisibility}
                    >
                        <i class={revealPassphrase ? 'fas fa-eye-slash' : 'fas fa-eye'} aria-hidden="true"></i>
                    </button>
                </div>
                {#if resumeError}
                    <div class="alert alert-danger text-sm mt-3 mb-0" role="alert">
                        {resumeError.message}
                    </div>
                {/if}
                <button
                    type="submit"
                    class="btn bg-gradient-primary w-100 mt-3 mb-0"
                    disabled={!resumeReady}
                >
                    {resumeRetryRemaining > 0
                        ? `Tunggu ${resumeRetryRemaining} detik`
                        : 'Lanjutkan Operasi Tersisa'}
                </button>
            </form>
        {:else if stage === 'failed'}
            <div class="alert alert-danger esign-attempt-alert mb-0" role="alert">
                {details.retryable
                    ? 'Attempt ini dapat diulang melalui tindakan TTE baru setelah penyebab kegagalan diperbaiki.'
                    : 'Attempt ini tidak dapat dilanjutkan dari browser.'}
                {#if details.error_code}
                    <span class="d-block mt-1">Kode: {details.error_code}</span>
                {/if}
            </div>
        {/if}
    {:else}
        <div class="esign-attempt-progress esign-attempt-progress--waiting" role="status">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <span>Menunggu status authoritative pertama dari backend.</span>
        </div>
    {/if}

    {#if pollingError && (stage === 'processing' || stage === 'unknown')}
        <div class="alert alert-warning esign-attempt-alert mb-0" role="status">
            <strong class="d-block mb-1">Status sementara belum dapat diperbarui</strong>
            {pollingError.message}
            {stage === 'processing'
                ? ' Pembacaan status akan dicoba kembali tanpa mengulang TTE.'
                : ' Polling dihentikan agar tidak menyimpulkan hasil secara keliru.'}
        </div>
    {/if}

    <p class="esign-attempt-panel__id mb-0">ID attempt: {attempt.attempt_id}</p>
</section>
