<script lang="ts">
    import type {
        ArtifactVerification,
        EsignValidationOpenEventDetail,
        NormalizedEsignError,
    } from '../types';
    import VerificationPdfViewer from './VerificationPdfViewer.svelte';
    import VerificationSignerTable from './VerificationSignerTable.svelte';

    let {
        action,
        result,
        loading,
        error,
        fetchPdf,
    }: {
        action: EsignValidationOpenEventDetail;
        result: ArtifactVerification | null;
        loading: boolean;
        error: NormalizedEsignError | null;
        fetchPdf: (url: string, signal?: AbortSignal) => Promise<ArrayBuffer>;
    } = $props();

    function statusTitle(): string {
        if (result?.verification.status === 'valid') {
            return 'Tanda tangan elektronik valid';
        }

        if (result?.verification.status === 'invalid') {
            return 'Tanda tangan elektronik tidak valid';
        }

        return 'Dokumen belum memiliki tanda tangan elektronik';
    }

    function statusDescription(): string {
        if (result?.verification.description) {
            return result.verification.description;
        }

        if (result?.verification.status === 'valid') {
            return 'Integritas dokumen dan informasi tanda tangan telah diterima dari layanan BSrE.';
        }

        if (result?.verification.status === 'invalid') {
            return 'Dokumen tidak lolos pemeriksaan tanda tangan elektronik BSrE.';
        }

        return 'Tidak ditemukan signature elektronik pada PDF yang diperiksa.';
    }

    function formatTimestamp(value: string): string {
        const date = new Date(value);

        return Number.isNaN(date.getTime())
            ? '—'
            : new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
    }
</script>

<section class="esign-verification-shell" aria-label="Hasil validasi tanda tangan elektronik">
    <div class="esign-verification-shell__preview">
        <div class="esign-panel-heading">Dokumen canonical</div>
        <VerificationPdfViewer previewUrl={action.preview_url} {fetchPdf} />
    </div>

    <aside class="esign-verification-shell__summary">
        <div class="esign-panel-heading">Hasil validasi</div>

        {#if loading}
            <div class="esign-verification-loading" role="status">
                <span class="spinner-border spinner-border-sm text-info" aria-hidden="true"></span>
                <div>
                    <strong>Memeriksa tanda tangan</strong>
                    <small>Layanan BSrE sedang memvalidasi artifact.</small>
                </div>
            </div>
        {:else if error}
            <div class="alert alert-warning esign-verification-error mb-0" role="alert">
                <i class="fas fa-cloud-arrow-down" aria-hidden="true"></i>
                <div>
                    <strong>Hasil validasi belum tersedia</strong>
                    <p class="mb-1">{error.message}</p>
                    <small>Dokumen tidak dinyatakan invalid. Pemeriksaan layanan sedang tidak dapat diselesaikan.</small>
                </div>
            </div>
        {:else if result}
            <div
                class:esign-verification-status--valid={result.verification.status === 'valid'}
                class:esign-verification-status--invalid={result.verification.status === 'invalid'}
                class:esign-verification-status--unsigned={result.verification.status === 'no_signature'}
                class="esign-verification-status"
                role="status"
            >
                <span class="esign-verification-status__icon" aria-hidden="true">
                    <i class={result.verification.status === 'valid'
                        ? 'fas fa-shield-circle-check'
                        : result.verification.status === 'invalid'
                            ? 'fas fa-shield-circle-xmark'
                            : 'fas fa-file-circle-question'}></i>
                </span>
                <div>
                    <h3 class="h6 mb-1">{statusTitle()}</h3>
                    <p class="text-sm mb-0">{statusDescription()}</p>
                </div>
            </div>

            <dl class="esign-summary-list esign-verification-metadata">
                <div><dt>Nomor dokumen</dt><dd>{result.document.number ?? '—'}</dd></div>
                <div><dt>Jenis dokumen</dt><dd>{result.document.type ?? '—'} / {result.document.payment_type ?? '—'}</dd></div>
                <div><dt>Nama file</dt><dd>{result.artifact.original_name ?? '—'}</dd></div>
                <div><dt>Versi artifact</dt><dd>{result.artifact.version}</dd></div>
                <div><dt>Jumlah tanda tangan</dt><dd>{result.verification.signature_count}</dd></div>
                <div><dt>Diperiksa</dt><dd>{formatTimestamp(result.verification.checked_at)}</dd></div>
            </dl>

            {#if result.verification.cached}
                <div class="alert alert-light border text-xs mb-0" role="note">
                    <i class="fas fa-bolt me-2" aria-hidden="true"></i>
                    Hasil berasal dari cache artifact immutable yang sama.
                </div>
            {/if}

            <div class="esign-panel-heading esign-panel-heading--section">
                Penandatangan
            </div>
            <VerificationSignerTable signatures={result.verification.signatures} />
        {/if}
    </aside>
</section>
