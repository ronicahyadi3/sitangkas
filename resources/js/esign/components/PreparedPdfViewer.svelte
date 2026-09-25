<script lang="ts">
    import { onMount, tick } from 'svelte';
    import type { PDFDocumentProxy } from 'pdfjs-dist';
    import { EsignApiError, invalidResponseError } from '../api/errors';
    import { verifyPdfDocumentGeometry } from '../geometry/pdf-document';
    import { loadPdfDocument, type LoadedPdfDocument } from '../pdf/load-document';
    import type {
        NormalizedEsignError,
        PreparedSignatureOperation,
        PreparedSigningRendition,
        SigningSession,
    } from '../types';
    import PdfPageCanvas from './PdfPageCanvas.svelte';

    let {
        session,
        rendition,
        fetchPdf,
        fetchPng,
        preparedReady = $bindable(false),
    }: {
        session: SigningSession;
        rendition: PreparedSigningRendition;
        fetchPdf: (url: string, signal?: AbortSignal) => Promise<ArrayBuffer>;
        fetchPng: (url: string, signal?: AbortSignal) => Promise<Blob>;
        preparedReady: boolean;
    } = $props();

    let workspaceElement: HTMLDivElement;
    let pagesElement = $state<HTMLDivElement>();
    let document = $state<PDFDocumentProxy | null>(null);
    let viewerError = $state<NormalizedEsignError | null>(null);
    let loading = $state(true);
    let activePage = $state(1);
    let zoom = $state(1);
    let workspaceWidth = $state(0);
    let qrImages = $state<Record<number, string>>({});
    let loadedDocument: LoadedPdfDocument | null = null;
    let objectUrls: string[] = [];
    let loadGeneration = 0;

    let activeGeometry = $derived(session.pages[activePage - 1] ?? session.pages[0]);
    let fitScale = $derived.by(() => {
        const availableWidth = Math.max(240, workspaceWidth - 40);
        const pageWidth = activeGeometry?.rotation === 90 || activeGeometry?.rotation === 270
            ? activeGeometry.height
            : activeGeometry?.width;

        return Math.min(1.6, Math.max(0.25, availableWidth / Math.max(1, pageWidth ?? 1)));
    });
    let renderScale = $derived(fitScale * zoom);
    let visiblePages = $derived.by(() => {
        const pages = [activePage - 1, activePage, activePage + 1]
            .filter((page) => page >= 1 && page <= session.pages.length);

        return [...new Set(pages)];
    });

    onMount(() => {
        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];

            if (entry) {
                workspaceWidth = entry.contentRect.width;
            }
        });

        observer.observe(workspaceElement);

        return () => observer.disconnect();
    });

    $effect(() => {
        const previewUrl = rendition.preview_url;
        const operations = rendition.signature_operations;
        const controller = new AbortController();
        const generation = ++loadGeneration;

        preparedReady = false;
        loading = true;
        viewerError = null;
        document = null;
        activePage = 1;
        zoom = 1;
        releaseObjectUrls();
        qrImages = {};

        void (async (): Promise<void> => {
            let candidate: LoadedPdfDocument | null = null;
            const createdUrls: string[] = [];

            try {
                const binary = await fetchPdf(previewUrl, controller.signal);
                candidate = await loadPdfDocument(binary, controller.signal);

                if (controller.signal.aborted || generation !== loadGeneration) {
                    await candidate.destroy();
                    candidate = null;
                    return;
                }

                const geometryIssue = await verifyPdfDocumentGeometry(
                    candidate.document,
                    session.pages,
                    controller.signal,
                );

                if (geometryIssue !== null) {
                    throw new EsignApiError({
                        ...invalidResponseError(),
                        message: geometryIssue.message,
                        code: geometryIssue.code,
                    });
                }

                const entryResults = await Promise.allSettled(operations.map(async (operation) => {
                    const blob = await fetchPng(operation.qr_image_url, controller.signal);

                    if (blob.size < 1) {
                        throw new EsignApiError({
                            ...invalidResponseError(),
                            message: `QR operasi ${operation.operation_index + 1} kosong.`,
                            code: 'prepared_qr_empty',
                        });
                    }

                    const objectUrl = URL.createObjectURL(blob);
                    createdUrls.push(objectUrl);
                    await verifyQrImage(objectUrl, operation, controller.signal);

                    return [operation.operation_index, objectUrl] as const;
                }));
                const failedEntry = entryResults.find((result) => result.status === 'rejected');

                if (failedEntry?.status === 'rejected') {
                    throw failedEntry.reason;
                }

                const entries = entryResults.map((result) => {
                    if (result.status !== 'fulfilled') {
                        throw new EsignApiError(invalidResponseError());
                    }

                    return result.value;
                });

                if (controller.signal.aborted || generation !== loadGeneration) {
                    await candidate.destroy();
                    candidate = null;
                    createdUrls.forEach((url) => URL.revokeObjectURL(url));
                    return;
                }

                objectUrls = createdUrls;
                qrImages = Object.fromEntries(entries);
                loadedDocument = candidate;
                document = candidate.document;
                candidate = null;
                loading = false;
            } catch (error: unknown) {
                if (candidate !== null) {
                    await candidate.destroy();
                }

                createdUrls.forEach((url) => URL.revokeObjectURL(url));

                if (controller.signal.aborted || generation !== loadGeneration) {
                    return;
                }

                viewerError = error instanceof EsignApiError
                    ? error.details
                    : {
                        ...invalidResponseError(),
                        message: 'Prepared preview atau QR authoritative tidak dapat dimuat.',
                        code: 'prepared_preview_initialization_failed',
                    };
                loading = false;
            }
        })();

        return () => {
            loadGeneration += 1;
            controller.abort();
            preparedReady = false;

            const current = loadedDocument;
            loadedDocument = null;
            document = null;
            releaseObjectUrls();

            if (current !== null) {
                void current.destroy();
            }
        };
    });

    function releaseObjectUrls(): void {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls = [];
    }

    function verifyQrImage(
        objectUrl: string,
        operation: PreparedSignatureOperation,
        signal: AbortSignal,
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            const image = new Image();

            const cleanup = (): void => {
                signal.removeEventListener('abort', handleAbort);
                image.onload = null;
                image.onerror = null;
            };
            const handleAbort = (): void => {
                cleanup();
                image.src = '';
                reject(new DOMException('Permintaan dibatalkan.', 'AbortError'));
            };

            image.onload = () => {
                cleanup();
                resolve();
            };
            image.onerror = () => {
                cleanup();
                reject(new EsignApiError({
                    ...invalidResponseError(),
                    message: `QR operasi ${operation.operation_index + 1} bukan gambar PNG yang dapat ditampilkan.`,
                    code: 'prepared_qr_invalid',
                }));
            };
            signal.addEventListener('abort', handleAbort, { once: true });
            image.src = objectUrl;
        });
    }

    function operationsForPage(page: number): PreparedSignatureOperation[] {
        return rendition.signature_operations.filter((operation) => operation.page === page);
    }

    async function goToPage(page: number): Promise<void> {
        activePage = Math.min(session.pages.length, Math.max(1, page));
        await tick();

        pagesElement
            ?.querySelector<HTMLElement>(`[data-page-number="${activePage}"]`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function handleRenderReady(page: number): void {
        if (page === activePage && Object.keys(qrImages).length === rendition.signature_count) {
            preparedReady = true;
        }
    }

    function handleRenderError(page: number): void {
        if (page !== activePage) {
            return;
        }

        preparedReady = false;
        viewerError = {
            ...invalidResponseError(),
            message: `Prepared preview halaman ${page} gagal dirender.`,
            code: 'prepared_preview_render_failed',
        };
    }
</script>

<section class="esign-prepared-viewer" aria-label="Prepared preview authoritative" aria-busy={loading}>
    <div class="esign-workspace-toolbar" aria-label="Toolbar prepared preview">
        <div class="btn-group btn-group-sm" role="group" aria-label="Navigasi halaman prepared preview">
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={activePage <= 1 || document === null}
                aria-label="Halaman sebelumnya"
                onclick={() => goToPage(activePage - 1)}
            ><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={activePage >= session.pages.length || document === null}
                aria-label="Halaman berikutnya"
                onclick={() => goToPage(activePage + 1)}
            ><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
        <span class="esign-workspace-toolbar__page">
            Halaman {activePage} dari {session.pages.length}
        </span>
        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Zoom prepared preview">
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={document === null || zoom <= 0.5}
                aria-label="Perkecil"
                onclick={() => zoom = Math.max(0.5, Math.round((zoom - 0.1) * 10) / 10)}
            ><i class="fas fa-minus" aria-hidden="true"></i></button>
            <button
                type="button"
                class="btn btn-outline-secondary esign-zoom-value"
                disabled={document === null}
                onclick={() => zoom = 1}
            >{Math.round(zoom * 100)}%</button>
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={document === null || zoom >= 2.5}
                aria-label="Perbesar"
                onclick={() => zoom = Math.min(2.5, Math.round((zoom + 0.1) * 10) / 10)}
            ><i class="fas fa-plus" aria-hidden="true"></i></button>
        </div>
    </div>

    <div bind:this={workspaceElement} class="esign-pdf-workspace esign-prepared-viewer__workspace">
        {#if loading}
            <div class="esign-pdf-viewer-state" role="status">
                <span class="spinner-border text-primary" aria-hidden="true"></span>
                <div>
                    <p class="fw-semibold mb-1">Memuat preview final backend</p>
                    <p class="text-sm text-secondary mb-0">PDF dan QR authoritative sedang diverifikasi.</p>
                </div>
            </div>
        {:else if viewerError}
            <div class="esign-pdf-viewer-state esign-pdf-viewer-state--error" role="alert">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    <p class="fw-semibold mb-1">Preview final tidak dapat digunakan</p>
                    <p class="text-sm text-secondary mb-1">{viewerError.message}</p>
                    {#if viewerError.code}
                        <p class="text-xs text-secondary mb-0">Kode: {viewerError.code}</p>
                    {/if}
                </div>
            </div>
        {:else if document}
            <div bind:this={pagesElement} class="esign-pdf-pages">
                {#each visiblePages as page (page)}
                    <div class:esign-pdf-pages__item--active={page === activePage} class="esign-pdf-pages__item">
                        <div class="esign-pdf-pages__label">
                            <span>Halaman {page}</span>
                            {#if operationsForPage(page).length > 0}
                                <span class="badge bg-gradient-primary">
                                    {operationsForPage(page).length} QR
                                </span>
                            {/if}
                        </div>
                        <PdfPageCanvas
                            {document}
                            pageNumber={page}
                            expectedGeometry={session.pages[page - 1]}
                            scale={renderScale}
                            preparedOperations={operationsForPage(page)}
                            preparedQrImages={qrImages}
                            onRenderReady={handleRenderReady}
                            onRenderError={handleRenderError}
                        />
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</section>
