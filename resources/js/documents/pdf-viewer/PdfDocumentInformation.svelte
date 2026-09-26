<script lang="ts">
    import type { ArtifactVerification } from '../../esign/types';
    import type { SecurePdfViewerOpenDetail } from './types';

    let {
        action,
        pageCount,
        verification,
        verificationLoading,
        verificationError,
        onClose,
    }: {
        action: SecurePdfViewerOpenDetail;
        pageCount: number;
        verification: ArtifactVerification | null;
        verificationLoading: boolean;
        verificationError: string | null;
        onClose: () => void;
    } = $props();

    let verificationLabel = $derived.by(() => {
        if (verificationLoading) {
            return { className: 'secure-pdf-status--info', icon: 'fa-spinner fa-spin', label: 'Memeriksa' };
        }

        if (verificationError !== null) {
            return { className: 'secure-pdf-status--warning', icon: 'fa-triangle-exclamation', label: 'Tidak tersedia' };
        }

        if (verification?.verification.status === 'valid') {
            return { className: 'secure-pdf-status--success', icon: 'fa-circle-check', label: 'TTE valid' };
        }

        if (verification?.verification.status === 'invalid') {
            return { className: 'secure-pdf-status--danger', icon: 'fa-circle-xmark', label: 'TTE tidak valid' };
        }

        if (verification?.verification.status === 'no_signature') {
            return { className: 'secure-pdf-status--warning', icon: 'fa-file-circle-question', label: 'Belum ada TTE' };
        }

        return { className: 'secure-pdf-status--muted', icon: 'fa-shield-halved', label: 'Belum tersedia' };
    });

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

    function sourceLabel(): string {
        if (action.source_state === 'canonical') {
            return 'Artifact canonical';
        }

        if (action.source_state === 'legacy_private_pending') {
            return 'Dokumen legacy private';
        }

        if (action.source_state === 'legacy_public_pending') {
            return 'Dokumen legacy transisi';
        }

        return 'Sumber tidak tersedia';
    }

    function integrityLabel(value: boolean | null): string {
        if (value === null) {
            return 'Tidak dilaporkan';
        }

        return value ? 'Valid' : 'Tidak valid';
    }

    function deliveryModeLabel(): string {
        if (action.delivery_mode === 'identified_watermarked') {
            return 'Salinan berwatermark';
        }

        if (action.delivery_mode === 'public_watermarked') {
            return 'Salinan publik berwatermark';
        }

        return 'Dokumen original';
    }
</script>

<aside class="secure-pdf-information" aria-label="Informasi dan validasi dokumen">
    <header class="secure-pdf-information__header">
        <div>
            <span>Informasi dokumen</span>
            <small>Data dari server</small>
        </div>
        <button type="button" class="btn btn-link" aria-label="Tutup panel informasi" onclick={onClose}>
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </header>

    <div class="secure-pdf-information__content">
        <section class="secure-pdf-info-card">
            <div class="secure-pdf-info-card__title">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
                <span>Identitas</span>
            </div>
            <dl class="secure-pdf-metadata">
                <div><dt>Jenis</dt><dd>{action.document_type || action.title}</dd></div>
                <div><dt>Payment</dt><dd>{action.payment_type || '—'}</dd></div>
                <div><dt>Halaman</dt><dd>{pageCount || '—'}</dd></div>
                <div><dt>Sumber</dt><dd>{sourceLabel()}</dd></div>
                <div><dt>Mode</dt><dd>{deliveryModeLabel()}</dd></div>
            </dl>
        </section>

        <section class="secure-pdf-info-card">
            <div class="secure-pdf-info-card__title secure-pdf-info-card__title--split">
                <span><i class="fas fa-shield-halved" aria-hidden="true"></i> Validasi TTE</span>
                <span class="secure-pdf-status {verificationLabel.className}">
                    <i class="fas {verificationLabel.icon}" aria-hidden="true"></i>
                    {verificationLabel.label}
                </span>
            </div>

            {#if verificationLoading}
                <div class="secure-pdf-information__message">
                    Hasil validasi sedang dimuat tanpa menghambat tampilan PDF.
                </div>
            {:else if verificationError}
                <div class="secure-pdf-information__message secure-pdf-information__message--warning">
                    {verificationError}
                </div>
            {:else if verification}
                <p class="secure-pdf-verification-conclusion">{verification.verification.conclusion}</p>
                <dl class="secure-pdf-metadata secure-pdf-metadata--verification">
                    <div><dt>Jumlah TTE</dt><dd>{verification.verification.signature_count}</dd></div>
                    <div><dt>Diperiksa</dt><dd>{formatTimestamp(verification.verification.checked_at)}</dd></div>
                </dl>

                <div class="secure-pdf-signers">
                    {#if verification.verification.signatures.length === 0}
                        <div class="secure-pdf-signers__empty">Belum ada penandatangan elektronik.</div>
                    {:else}
                        {#each verification.verification.signatures as signature (signature.index)}
                            <article class="secure-pdf-signer">
                                <span class="secure-pdf-signer__index">{signature.index + 1}</span>
                                <div>
                                    <strong>{signature.signer_name}</strong>
                                    <small>{formatTimestamp(signature.signed_at)}</small>
                                    <span class:secure-pdf-signer__integrity--invalid={signature.integrity_valid === false} class="secure-pdf-signer__integrity">
                                        <i class="fas fa-fingerprint" aria-hidden="true"></i>
                                        Integritas {integrityLabel(signature.integrity_valid)}
                                    </span>
                                </div>
                            </article>
                        {/each}
                    {/if}
                </div>
            {:else}
                <div class="secure-pdf-information__message">
                    {action.actions.verify.reason?.message ?? 'Validasi BSrE belum tersedia untuk dokumen ini.'}
                </div>
            {/if}
        </section>

        <section class="secure-pdf-security-note">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <p><strong>Akses terlindungi</strong><span>Lokasi file private tidak dikirim ke browser. Hak akses diperiksa ulang oleh server.</span></p>
        </section>
    </div>
</aside>
