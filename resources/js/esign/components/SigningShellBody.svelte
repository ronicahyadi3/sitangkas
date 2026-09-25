<script lang="ts">
    import type { EsignShellStage } from '../ui-state';

    let { stage }: { stage: EsignShellStage } = $props();
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
{:else if stage === 'editing'}
    <section class="esign-editor-shell" aria-label="Area pengaturan posisi tanda tangan">
        <aside class="esign-editor-shell__thumbnails" aria-label="Daftar halaman">
            <div class="esign-panel-heading">
                <span>Halaman</span>
                <span class="badge bg-light text-dark">0</span>
            </div>
            <div class="esign-empty-panel">
                Thumbnail PDF akan tampil setelah dokumen dimuat.
            </div>
        </aside>

        <div class="esign-editor-shell__workspace">
            <div class="esign-workspace-toolbar" aria-label="Toolbar PDF">
                <div class="btn-group btn-group-sm" role="group" aria-label="Navigasi halaman">
                    <button class="btn btn-outline-secondary" type="button" disabled aria-label="Halaman sebelumnya">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" disabled aria-label="Halaman berikutnya">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <span class="esign-workspace-toolbar__page">Halaman — dari —</span>
                <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Pengaturan zoom">
                    <button class="btn btn-outline-secondary" type="button" disabled aria-label="Perkecil">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" disabled>100%</button>
                    <button class="btn btn-outline-secondary" type="button" disabled aria-label="Perbesar">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="esign-pdf-placeholder">
                <i class="far fa-file-pdf" aria-hidden="true"></i>
                <p class="mb-0">Viewer PDF akan dimuat pada tahap F6.</p>
            </div>
        </div>

        <aside class="esign-editor-shell__inspector" aria-label="Pengaturan QR dan footer">
            <div class="esign-panel-heading">Pengaturan</div>
            <div class="esign-empty-panel">
                Pilih atau tambahkan QR untuk melihat pengaturan posisi dan footer.
            </div>
        </aside>
    </section>
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
    <section class="esign-confirmation-shell" aria-label="Konfirmasi tanda tangan">
        <div class="esign-confirmation-shell__preview">
            <div class="esign-panel-heading">Preview final dari backend</div>
            <div class="esign-pdf-placeholder">
                <i class="far fa-file-pdf" aria-hidden="true"></i>
                <p class="mb-0">Prepared rendition akan ditampilkan di area ini.</p>
            </div>
        </div>
        <aside class="esign-confirmation-shell__summary">
            <div class="esign-panel-heading">Informasi dokumen</div>
            <dl class="esign-summary-list">
                <div><dt>Dokumen</dt><dd>Menunggu data sesi</dd></div>
                <div><dt>Penandatangan</dt><dd>Ditentukan backend</dd></div>
                <div><dt>Posisi QR</dt><dd>—</dd></div>
            </dl>
            <div class="alert alert-light border text-sm mb-0" role="note">
                Passphrase akan tersedia setelah prepared rendition berhasil dimuat.
            </div>
        </aside>
    </section>
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
{:else if stage === 'processing'}
    <section class="esign-process-shell" aria-live="polite">
        <div class="esign-process-shell__icon">
            <span class="spinner-border text-primary" aria-hidden="true"></span>
        </div>
        <h3 class="h5 mb-2">Tanda tangan sedang diproses</h3>
        <p class="text-sm text-secondary mb-4">
            Dokumen diproses di server. Modal boleh ditutup setelah attempt terbentuk.
        </p>
        <div class="progress esign-progress" role="progressbar" aria-label="Progres TTE" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <div class="progress-bar bg-gradient-info" style="width: 0%"></div>
        </div>
        <p class="text-xs text-secondary mt-2 mb-0">Menunggu status operasi dari backend.</p>
    </section>
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
            Ringkasan hasil authoritative akan ditampilkan dari status attempt backend.
        </p>
    </section>
{/if}
