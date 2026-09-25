<script lang="ts">
    import { onDestroy } from 'svelte';
    import type {
        NormalizedEsignError,
        PreparedSigningRendition,
        SigningSession,
    } from '../types';
    import PreparedPdfViewer from './PreparedPdfViewer.svelte';

    let {
        session,
        rendition,
        fetchPdf,
        fetchPng,
        error,
        preparedReady = $bindable(false),
        passphrase = $bindable(''),
    }: {
        session: SigningSession;
        rendition: PreparedSigningRendition;
        fetchPdf: (url: string, signal?: AbortSignal) => Promise<ArrayBuffer>;
        fetchPng: (url: string, signal?: AbortSignal) => Promise<Blob>;
        error: NormalizedEsignError | null;
        preparedReady: boolean;
        passphrase: string;
    } = $props();

    let revealPassphrase = $state(false);
    let revealTimer: ReturnType<typeof setTimeout> | null = null;

    let signaturePages = $derived([
        ...new Set(rendition.signature_operations.map((operation) => operation.page)),
    ].sort((left, right) => left - right));

    function pageSummary(pages: number[]): string {
        return pages.length === 0 ? '—' : pages.join(', ');
    }

    function expirySummary(value: string): string {
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

    onDestroy(() => {
        if (revealTimer !== null) {
            clearTimeout(revealTimer);
        }

        passphrase = '';
        preparedReady = false;
    });
</script>

<section class="esign-confirmation-shell" aria-label="Konfirmasi tanda tangan">
    <div class="esign-confirmation-shell__preview">
        <div class="esign-panel-heading">
            <span>Preview final dari backend</span>
            <span
                class:esign-prepared-status--ready={preparedReady}
                class="esign-prepared-status"
                role="status"
            >
                <i
                    class={preparedReady ? 'fas fa-circle-check' : 'fas fa-spinner fa-spin'}
                    aria-hidden="true"
                ></i>
                {preparedReady ? 'Siap dikonfirmasi' : 'Memverifikasi preview'}
            </span>
        </div>
        <PreparedPdfViewer
            {session}
            {rendition}
            {fetchPdf}
            {fetchPng}
            bind:preparedReady
        />
    </div>

    <aside class="esign-confirmation-shell__summary">
        <div class="esign-panel-heading">
            <span>Ringkasan TTE</span>
            <span class="esign-confirmation-shell__status">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                Preview final
            </span>
        </div>

        <div class="esign-confirmation-signer">
            <span class="esign-confirmation-signer__icon" aria-hidden="true">
                <i class="fas fa-user-check"></i>
            </span>
            <div>
                <span>Penandatangan</span>
                <strong>{session.signer_name}</strong>
                <small>NIK {session.masked_nik}</small>
            </div>
        </div>

        <dl class="esign-summary-list esign-confirmation-metadata">
            <div class="esign-confirmation-metadata__document">
                <dt>Dokumen</dt>
                <dd>{session.pages.length} halaman <span>· versi {session.artifact_version}</span></dd>
            </div>
            <div>
                <dt>TTE sebelumnya</dt>
                <dd>{session.verified_signature_count}</dd>
            </div>
            <div>
                <dt>QR baru</dt>
                <dd>{rendition.signature_count} <span>di halaman {pageSummary(signaturePages)}</span></dd>
            </div>
            <div>
                <dt>Footer</dt>
                <dd>
                    {rendition.footer === null || rendition.footer.placements.length === 0
                        ? 'Tidak ada'
                        : `${rendition.footer.placements.length} halaman`}
                </dd>
            </div>
            <div class="esign-confirmation-metadata__expiry">
                <dt>Preview berlaku sampai</dt>
                <dd>{expirySummary(rendition.expires_at)}</dd>
            </div>
        </dl>

        <div class="esign-confirmation-shell__authorization">
            <div class="esign-confirmation-shell__authorization-heading">
                <span class="esign-confirmation-shell__authorization-icon" aria-hidden="true">
                    <i class="fas fa-key"></i>
                </span>
                <div>
                    <strong>Konfirmasi penandatangan</strong>
                    <small>Masukkan passphrase untuk memulai proses TTE.</small>
                </div>
            </div>
            <div class="esign-passphrase-form">
                <label class="form-label text-sm" for="esign-confirmation-passphrase">
                    Passphrase BSrE
                </label>
                <div class="input-group">
                    <input
                        id="esign-confirmation-passphrase"
                        class="form-control"
                        type={revealPassphrase ? 'text' : 'password'}
                        bind:value={passphrase}
                        disabled={!preparedReady}
                        autocomplete="off"
                        autocapitalize="none"
                        maxlength="255"
                        spellcheck="false"
                        placeholder={preparedReady ? 'Masukkan passphrase' : 'Tunggu preview siap'}
                        aria-describedby="esign-confirmation-passphrase-help"
                    />
                    <button
                        type="button"
                        class="btn btn-outline-secondary mb-0"
                        disabled={!preparedReady || passphrase.length === 0}
                        aria-label={revealPassphrase ? 'Sembunyikan passphrase' : 'Tampilkan passphrase'}
                        aria-pressed={revealPassphrase}
                        onclick={togglePassphraseVisibility}
                    >
                        <i class={revealPassphrase ? 'fas fa-eye-slash' : 'fas fa-eye'} aria-hidden="true"></i>
                    </button>
                </div>
                <p id="esign-confirmation-passphrase-help" class="esign-passphrase-form__help mb-0">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    Satu klik TTE akan memproses {rendition.signature_count} QR secara berurutan di server.
                </p>
            </div>
        </div>

        {#if error}
            <div class="alert alert-danger esign-submit-error text-sm" role="alert" aria-live="assertive">
                <strong class="d-block mb-1">Permintaan belum dapat diterima</strong>
                <span>{error.message}</span>
                {#if error.retry_after_seconds !== null}
                    <span class="d-block mt-1">
                        Coba kembali setelah sekitar {error.retry_after_seconds} detik.
                    </span>
                {/if}
                {#if Object.values(error.field_errors).flat().length > 0}
                    <ul class="mb-0 mt-2 ps-3">
                        {#each Object.values(error.field_errors).flat().slice(0, 3) as message}
                            <li>{message}</li>
                        {/each}
                    </ul>
                {/if}
            </div>
        {/if}

        <div class="esign-confirmation-note" role="note">
            <i class="fas fa-shield-halved me-2" aria-hidden="true"></i>
            Pastikan nama penandatangan dan seluruh posisi QR pada preview sudah benar.
        </div>
    </aside>
</section>
