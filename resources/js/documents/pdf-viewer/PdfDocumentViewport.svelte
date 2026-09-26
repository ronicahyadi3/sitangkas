<script lang="ts">
    import { onMount, tick } from 'svelte';
    import type { PDFDocumentProxy } from 'pdfjs-dist';
    import { normalizedRotation } from '../../esign/geometry/transform';
    import { loadPdfDocument, type LoadedPdfDocument } from '../../esign/pdf/load-document';
    import type { PdfPageGeometry } from '../../esign/types';
    import { fetchPdfBinary } from './api';
    import ReadOnlyPdfPageCanvas from './ReadOnlyPdfPageCanvas.svelte';

    let {
        contentUrl,
        downloadUrl,
        showInformation,
        onInformationToggle,
        onReady,
        onError,
    }: {
        contentUrl: string;
        downloadUrl: string | null;
        showInformation: boolean;
        onInformationToggle: () => void;
        onReady: (pageCount: number) => void;
        onError: (message: string) => void;
    } = $props();

    let workspaceElement: HTMLDivElement;
    let pagesElement = $state<HTMLDivElement>();
    let pdfDocument = $state<PDFDocumentProxy | null>(null);
    let pages = $state<PdfPageGeometry[]>([]);
    let loading = $state(true);
    let loadError = $state<string | null>(null);
    let activePage = $state(1);
    let zoom = $state(1);
    let workspaceWidth = $state(0);
    let loadedDocument: LoadedPdfDocument | null = null;
    let loadGeneration = 0;
    let scrollFrame: number | null = null;

    let maximumPageWidth = $derived.by(() => Math.max(1, ...pages.map((page) => (
        page.rotation === 90 || page.rotation === 270 ? page.height : page.width
    ))));
    let fitScale = $derived(Math.min(1.6, Math.max(0.2, (Math.max(280, workspaceWidth) - 48) / maximumPageWidth)));
    let renderScale = $derived(fitScale * zoom);

    onMount(() => {
        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];

            if (entry) {
                workspaceWidth = entry.contentRect.width;
            }
        });

        observer.observe(workspaceElement);

        return () => {
            observer.disconnect();

            if (scrollFrame !== null) {
                window.cancelAnimationFrame(scrollFrame);
            }
        };
    });

    $effect(() => {
        const url = contentUrl;
        const controller = new AbortController();
        const generation = ++loadGeneration;

        loading = true;
        loadError = null;
        pdfDocument = null;
        pages = [];
        activePage = 1;
        zoom = 1;

        void (async (): Promise<void> => {
            let candidate: LoadedPdfDocument | null = null;

            try {
                const binary = await fetchPdfBinary(url, controller.signal);
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
                onReady(geometries.length);
            } catch (error: unknown) {
                if (candidate !== null) {
                    await candidate.destroy();
                }

                if (controller.signal.aborted || generation !== loadGeneration) {
                    return;
                }

                const message = error instanceof Error
                    ? error.message
                    : 'PDF tidak dapat dimuat.';

                loadError = message;
                loading = false;
                onError(message);
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
                    throw new Error('Geometri halaman PDF tidak valid.');
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
            throw new Error('PDF tidak mempunyai halaman.');
        }

        return geometries;
    }

    async function goToPage(page: number): Promise<void> {
        activePage = Math.min(pages.length, Math.max(1, page));
        await tick();

        pagesElement
            ?.querySelector<HTMLElement>(`[data-viewer-page="${activePage}"]`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function trackActivePage(): void {
        if (scrollFrame !== null) {
            return;
        }

        scrollFrame = window.requestAnimationFrame(() => {
            scrollFrame = null;

            if (!pagesElement || pages.length === 0) {
                return;
            }

            const workspaceBounds = workspaceElement.getBoundingClientRect();
            const referenceY = workspaceBounds.top + Math.min(140, workspaceBounds.height * 0.3);
            let closestPage = activePage;
            let closestDistance = Number.POSITIVE_INFINITY;

            pagesElement.querySelectorAll<HTMLElement>('[data-viewer-page]').forEach((element) => {
                const bounds = element.getBoundingClientRect();
                const distance = Math.abs(bounds.top - referenceY);

                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestPage = Number(element.dataset.viewerPage ?? activePage);
                }
            });

            if (Number.isInteger(closestPage) && closestPage !== activePage) {
                activePage = closestPage;
            }
        });
    }

    function pageRenderFailed(page: number): void {
        onError(`Halaman ${page} gagal dirender.`);
    }
</script>

<section class="secure-pdf-viewport" aria-label="Dokumen PDF" aria-busy={loading}>
    <div class="secure-pdf-toolbar" aria-label="Toolbar dokumen">
        <div class="secure-pdf-toolbar__group" role="group" aria-label="Navigasi halaman">
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={activePage <= 1 || pdfDocument === null}
                aria-label="Halaman sebelumnya"
                onclick={() => goToPage(activePage - 1)}
            ><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
            <span class="secure-pdf-toolbar__counter" aria-label="Halaman {activePage} dari {pages.length}">
                {activePage}/{pages.length || '—'}
            </span>
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={activePage >= pages.length || pdfDocument === null}
                aria-label="Halaman berikutnya"
                onclick={() => goToPage(activePage + 1)}
            ><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>

        <div class="secure-pdf-toolbar__group secure-pdf-toolbar__group--zoom" role="group" aria-label="Zoom dokumen">
            <button
                type="button"
                class="btn btn-outline-secondary"
                disabled={pdfDocument === null || zoom <= 0.5}
                aria-label="Perkecil"
                onclick={() => zoom = Math.max(0.5, Math.round((zoom - 0.1) * 10) / 10)}
            ><i class="fas fa-minus" aria-hidden="true"></i></button>
            <button
                type="button"
                class="btn btn-outline-secondary secure-pdf-toolbar__zoom"
                disabled={pdfDocument === null}
                aria-label="Kembalikan zoom ke ukuran pas"
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

        <div class="secure-pdf-toolbar__actions">
            <button
                type="button"
                class:active={showInformation}
                class="btn btn-outline-secondary secure-pdf-toolbar__info"
                aria-label="Tampilkan informasi dokumen"
                aria-pressed={showInformation}
                onclick={onInformationToggle}
            ><i class="fas fa-circle-info" aria-hidden="true"></i><span>Info</span></button>
            {#if downloadUrl}
                <a class="btn btn-primary" href={downloadUrl} aria-label="Unduh dokumen PDF">
                    <i class="fas fa-download" aria-hidden="true"></i><span>Unduh</span>
                </a>
            {/if}
        </div>
    </div>

    <div class="secure-pdf-viewport__body">
        <aside class="secure-pdf-thumbnails" aria-label="Daftar halaman">
            {#if pdfDocument}
                {#each pages as page (page.page)}
                    <button
                        type="button"
                        class:secure-pdf-thumbnail--active={page.page === activePage}
                        class="secure-pdf-thumbnail"
                        aria-label="Buka halaman {page.page}"
                        aria-current={page.page === activePage ? 'page' : undefined}
                        onclick={() => goToPage(page.page)}
                    >
                        <ReadOnlyPdfPageCanvas
                            document={pdfDocument}
                            pageNumber={page.page}
                            expectedGeometry={page}
                            scale={0.16}
                            thumbnail
                            deferUntilVisible
                            onRenderError={pageRenderFailed}
                        />
                        <span>{page.page}</span>
                    </button>
                {/each}
            {/if}
        </aside>

        <div bind:this={workspaceElement} class="secure-pdf-workspace" onscroll={trackActivePage}>
            {#if loading}
                <div class="secure-pdf-state" role="status">
                    <span class="spinner-border text-primary" aria-hidden="true"></span>
                    <div>
                        <strong>Memuat dokumen</strong>
                        <small>PDF dibaca langsung melalui jalur aman server.</small>
                    </div>
                </div>
            {:else if loadError}
                <div class="secure-pdf-state secure-pdf-state--error" role="alert">
                    <i class="fas fa-file-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Dokumen tidak dapat ditampilkan</strong>
                        <small>{loadError}</small>
                    </div>
                </div>
            {:else if pdfDocument}
                <div bind:this={pagesElement} class="secure-pdf-pages">
                    {#each pages as page (page.page)}
                        <article
                            class:secure-pdf-page-wrap--active={page.page === activePage}
                            class="secure-pdf-page-wrap"
                            data-viewer-page={page.page}
                            aria-label="Halaman {page.page}"
                        >
                            <div class="secure-pdf-page-wrap__label">Halaman {page.page}</div>
                            <ReadOnlyPdfPageCanvas
                                document={pdfDocument}
                                pageNumber={page.page}
                                expectedGeometry={page}
                                scale={renderScale}
                                deferUntilVisible
                                onRenderError={pageRenderFailed}
                            />
                        </article>
                    {/each}
                </div>
            {/if}
        </div>
    </div>
</section>
