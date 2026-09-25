<script lang="ts">
    import { onMount, tick } from 'svelte';
    import type { PDFDocumentProxy } from 'pdfjs-dist';
    import { invalidResponseError } from '../api/errors';
    import { normalizedRotation } from '../geometry/transform';
    import { loadPdfDocument, type LoadedPdfDocument } from '../pdf/load-document';
    import type { NormalizedEsignError, PdfPageGeometry } from '../types';
    import PdfPageCanvas from './PdfPageCanvas.svelte';

    let {
        previewUrl,
        fetchPdf,
    }: {
        previewUrl: string;
        fetchPdf: (url: string, signal?: AbortSignal) => Promise<ArrayBuffer>;
    } = $props();

    let workspaceElement: HTMLDivElement;
    let pagesElement = $state<HTMLDivElement>();
    let pdfDocument = $state<PDFDocumentProxy | null>(null);
    let pages = $state<PdfPageGeometry[]>([]);
    let viewerError = $state<NormalizedEsignError | null>(null);
    let loading = $state(true);
    let activePage = $state(1);
    let zoom = $state(1);
    let workspaceWidth = $state(0);
    let loadedDocument: LoadedPdfDocument | null = null;
    let loadGeneration = 0;

    let activeGeometry = $derived(pages[activePage - 1] ?? pages[0]);
    let fitScale = $derived.by(() => {
        const availableWidth = Math.max(240, workspaceWidth - 40);
        const pageWidth = activeGeometry?.rotation === 90 || activeGeometry?.rotation === 270
            ? activeGeometry.height
            : activeGeometry?.width;

        return Math.min(1.6, Math.max(0.25, availableWidth / Math.max(1, pageWidth ?? 1)));
    });
    let renderScale = $derived(fitScale * zoom);
    let visiblePages = $derived.by(() => {
        const visible = [activePage - 1, activePage, activePage + 1]
            .filter((page) => page >= 1 && page <= pages.length);

        return [...new Set(visible)];
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
        const url = previewUrl;
        const controller = new AbortController();
        const generation = ++loadGeneration;

        loading = true;
        viewerError = null;
        pdfDocument = null;
        pages = [];
        activePage = 1;
        zoom = 1;

        void (async (): Promise<void> => {
            let candidate: LoadedPdfDocument | null = null;

            try {
                const binary = await fetchPdf(url, controller.signal);
                candidate = await loadPdfDocument(binary, controller.signal);
                const geometries = await inspectPages(candidate.document, controller.signal);

                if (controller.signal.aborted || generation !== loadGeneration) {
                    await candidate.destroy();
                    candidate = null;
                    return;
                }

                loadedDocument = candidate;
                pdfDocument = candidate.document;
                pages = geometries;
                candidate = null;
                loading = false;
            } catch {
                if (candidate !== null) {
                    await candidate.destroy();
                }

                if (controller.signal.aborted || generation !== loadGeneration) {
                    return;
                }

                viewerError = {
                    ...invalidResponseError(),
                    message: 'Preview PDF canonical tidak dapat dimuat.',
                    code: 'verification_preview_unavailable',
                };
                loading = false;
            }
        })();

        return () => {
            loadGeneration += 1;
            controller.abort();

            const current = loadedDocument;
            loadedDocument = null;
            pdfDocument = null;
            pages = [];

            if (current !== null) {
                void current.destroy();
            }
        };
    });

    async function inspectPages(
        document: PDFDocumentProxy,
        signal: AbortSignal,
    ): Promise<PdfPageGeometry[]> {
        const geometries: PdfPageGeometry[] = [];

        for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber += 1) {
            signal.throwIfAborted();
            const page = await document.getPage(pageNumber);

            try {
                const viewport = page.getViewport({ scale: 1, rotation: 0 });
                const rotation = normalizedRotation(page.rotate);

                if (rotation === null || viewport.width <= 0 || viewport.height <= 0) {
                    throw new Error('Invalid PDF page geometry.');
                }

                geometries.push({
                    page: pageNumber,
                    width: viewport.width,
                    height: viewport.height,
                    rotation,
                });
            } finally {
                page.cleanup();
            }
        }

        if (geometries.length === 0) {
            throw new Error('PDF has no pages.');
        }

        return geometries;
    }

    async function goToPage(page: number): Promise<void> {
        activePage = Math.min(pages.length, Math.max(1, page));
        await tick();

        pagesElement
            ?.querySelector<HTMLElement>(`[data-page-number="${activePage}"]`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function handleRenderError(page: number): void {
        if (page !== activePage) {
            return;
        }

        viewerError = {
            ...invalidResponseError(),
            message: `Preview halaman ${page} gagal dirender.`,
            code: 'verification_preview_render_failed',
        };
    }
</script>

<section class="esign-verification-viewer" aria-label="Preview dokumen yang divalidasi" aria-busy={loading}>
    <div class="esign-workspace-toolbar" aria-label="Toolbar preview validasi">
        <div class="btn-group btn-group-sm" role="group" aria-label="Navigasi halaman">
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={activePage <= 1 || pdfDocument === null}
                aria-label="Halaman sebelumnya"
                onclick={() => goToPage(activePage - 1)}
            ><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={activePage >= pages.length || pdfDocument === null}
                aria-label="Halaman berikutnya"
                onclick={() => goToPage(activePage + 1)}
            ><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
        <span class="esign-workspace-toolbar__page">
            Halaman {activePage} dari {pages.length || '—'}
        </span>
        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Zoom preview validasi">
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={pdfDocument === null || zoom <= 0.5}
                aria-label="Perkecil"
                onclick={() => zoom = Math.max(0.5, Math.round((zoom - 0.1) * 10) / 10)}
            ><i class="fas fa-minus" aria-hidden="true"></i></button>
            <button
                type="button"
                class="btn btn-outline-secondary esign-zoom-value"
                disabled={pdfDocument === null}
                onclick={() => zoom = 1}
            >{Math.round(zoom * 100)}%</button>
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={pdfDocument === null || zoom >= 2.5}
                aria-label="Perbesar"
                onclick={() => zoom = Math.min(2.5, Math.round((zoom + 0.1) * 10) / 10)}
            ><i class="fas fa-plus" aria-hidden="true"></i></button>
        </div>
    </div>

    <div bind:this={workspaceElement} class="esign-pdf-workspace esign-verification-viewer__workspace">
        {#if loading}
            <div class="esign-pdf-viewer-state" role="status">
                <span class="spinner-border text-info" aria-hidden="true"></span>
                <div>
                    <p class="fw-semibold mb-1">Memuat PDF canonical</p>
                    <p class="text-sm text-secondary mb-0">File dibaca langsung dari penyimpanan private server.</p>
                </div>
            </div>
        {:else if viewerError}
            <div class="esign-pdf-viewer-state esign-pdf-viewer-state--error" role="alert">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    <p class="fw-semibold mb-1">Preview tidak tersedia</p>
                    <p class="text-sm text-secondary mb-0">{viewerError.message}</p>
                </div>
            </div>
        {:else if pdfDocument}
            <div bind:this={pagesElement} class="esign-pdf-pages">
                {#each visiblePages as page (page)}
                    <div class:esign-pdf-pages__item--active={page === activePage} class="esign-pdf-pages__item">
                        <div class="esign-pdf-pages__label"><span>Halaman {page}</span></div>
                        <PdfPageCanvas
                            document={pdfDocument}
                            pageNumber={page}
                            expectedGeometry={pages[page - 1]}
                            scale={renderScale}
                            onRenderError={handleRenderError}
                        />
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</section>
