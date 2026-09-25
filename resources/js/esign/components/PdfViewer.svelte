<script lang="ts">
    import { onMount, tick } from 'svelte';
    import type { PDFDocumentProxy } from 'pdfjs-dist';
    import { EsignApiError, invalidResponseError } from '../api/errors';
    import {
        applyFooterPlacementToAllPages,
        normalizeFooterFontSize,
        replaceFooterPlacement,
        resetFooterStyle,
        validateFooterPlan,
    } from '../geometry/footer';
    import {
        findAvailableSignatureRectangle,
        normalizeSignatureOperationIndexes,
        resizeSquareSignaturePlacement,
    } from '../geometry/placement';
    import { serializeSignaturePlacement } from '../geometry/serialization';
    import { domPointToCanonicalPoint } from '../geometry/transform';
    import { validateGeometryPlan } from '../geometry/validation';
    import { verifyPdfDocumentGeometry } from '../geometry/pdf-document';
    import type { RenderedPdfPageMetrics } from '../geometry/types';
    import { loadPdfDocument, type LoadedPdfDocument } from '../pdf/load-document';
    import type {
        NormalizedEsignError,
        FooterPlacement,
        FooterPlan,
        SignaturePlacement,
        SignaturePlacementTarget,
        SigningSession,
    } from '../types';
    import PdfPageCanvas from './PdfPageCanvas.svelte';

    const FOOTER_FONT_SIZE_STEP_PT = 0.1;

    let {
        session,
        fetchPdf,
        onAddSignature,
        onResetPlacements,
        placements = $bindable(),
        footerPlan = $bindable(null),
        activePage = $bindable(1),
        selectedPlacementId = $bindable(null),
        selectedFooterPage = $bindable(null),
        editorReady = $bindable(false),
    }: {
        session: SigningSession;
        fetchPdf: (url: string, signal?: AbortSignal) => Promise<ArrayBuffer>;
        onAddSignature: (target?: SignaturePlacementTarget) => void;
        onResetPlacements: () => void;
        placements: SignaturePlacement[];
        footerPlan: FooterPlan | null;
        activePage: number;
        selectedPlacementId: string | null;
        selectedFooterPage: number | null;
        editorReady: boolean;
    } = $props();

    let workspaceElement: HTMLDivElement;
    let pagesElement: HTMLDivElement;
    let thumbnailListElement: HTMLDivElement;
    let document = $state<PDFDocumentProxy | null>(null);
    let viewerError = $state<NormalizedEsignError | null>(null);
    let loading = $state(true);
    let zoom = $state(1);
    let workspaceWidth = $state(0);
    let thumbnailCount = $state(0);
    let renderedPageMetrics = $state<Record<number, RenderedPdfPageMetrics>>({});
    let loadedDocument: LoadedPdfDocument | null = null;
    let loadGeneration = 0;
    let placementFeedback = $state('');
    let inspectorPanel = $state<'signature' | 'footer' | 'document'>('signature');

    let activeGeometry = $derived(session.pages[activePage - 1] ?? session.pages[0]);
    let fitScale = $derived.by(() => {
        const availableWidth = Math.max(240, workspaceWidth - 48);
        const pageWidth = activeGeometry?.rotation === 90 || activeGeometry?.rotation === 270
            ? activeGeometry.height
            : activeGeometry.width;

        return Math.min(1.6, Math.max(0.25, availableWidth / Math.max(1, pageWidth)));
    });
    let renderScale = $derived(fitScale * zoom);
    let geometryValidation = $derived(validateGeometryPlan(
        placements,
        footerPlan?.placements ?? [],
        session.pages,
        session.editor,
    ));
    let footerValidationIssues = $derived(validateFooterPlan(
        footerPlan,
        session.pages,
        session.editor,
    ));
    let invalidPlacementIds = $derived([...new Set(geometryValidation.issues.flatMap((issue) => {
        const ids = [issue.subject_id, issue.conflicting_subject_id];

        return ids.filter((id): id is string => id !== undefined && !id.startsWith('footer:'));
    }))]);
    let selectedPlacement = $derived(
        placements.find((placement) => placement.client_id === selectedPlacementId) ?? null,
    );
    let selectedPlacementIssues = $derived(geometryValidation.issues.filter((issue) => (
        issue.subject_id === selectedPlacementId
        || issue.conflicting_subject_id === selectedPlacementId
    )));
    let invalidFooterPages = $derived([...new Set([
        ...geometryValidation.issues.flatMap((issue) => (
            [issue.subject_id, issue.conflicting_subject_id]
                .filter((id): id is string => id?.startsWith('footer:') ?? false)
                .map((id) => Number(id.slice('footer:'.length)))
        )),
        ...footerValidationIssues.flatMap((issue) => issue.page === undefined ? [] : [issue.page]),
    ])]);
    let selectedFooterPlacement = $derived(
        footerPlan?.placements.find((placement) => placement.page === selectedFooterPage) ?? null,
    );
    let selectedFooterIssues = $derived([
        ...geometryValidation.issues.filter((issue) => (
            issue.subject_id === `footer:${selectedFooterPage}`
            || issue.conflicting_subject_id === `footer:${selectedFooterPage}`
        )).map((issue) => issue.message),
        ...footerValidationIssues.filter((issue) => (
            issue.page === undefined || issue.page === selectedFooterPage
        )).map((issue) => issue.message),
    ]);
    let selectedMaximumSize = $derived.by(() => {
        if (selectedPlacement === null) {
            return session.editor.qr.maximum_size_pt;
        }

        const page = session.pages.find((item) => item.page === selectedPlacement.page);

        if (page === undefined) {
            return session.editor.qr.maximum_size_pt;
        }

        return Math.min(
            session.editor.qr.maximum_size_pt,
            page.width - session.editor.safe_margin_pt - selectedPlacement.origin_x,
            page.height - session.editor.safe_margin_pt - selectedPlacement.origin_y,
        );
    });
    let viewerPages = $derived(session.pages.map((page) => page.page));
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
        const previewUrl = session.preview_url;
        const controller = new AbortController();
        const generation = ++loadGeneration;

        loading = true;
        viewerError = null;
        document = null;
        activePage = 1;
        zoom = 1;
        thumbnailCount = 0;
        renderedPageMetrics = {};
        placementFeedback = '';
        inspectorPanel = 'signature';
        editorReady = false;

        void (async (): Promise<void> => {
            let candidate: LoadedPdfDocument | null = null;

            try {
                const binary = await fetchPdf(previewUrl, controller.signal);
                candidate = await loadPdfDocument(binary, controller.signal);

                if (controller.signal.aborted || generation !== loadGeneration) {
                    await candidate.destroy();
                    candidate = null;
                    return;
                }

                const unsupportedRotatedPage = session.pages.find((page) => (
                    page.rotation !== 0 && !session.editor.rotated_pages_supported
                ));

                if (unsupportedRotatedPage !== undefined) {
                    throw new EsignApiError({
                        ...invalidResponseError(),
                        message: `Halaman ${unsupportedRotatedPage.page} memiliki rotasi ${unsupportedRotatedPage.rotation}° dan belum didukung editor.`,
                        code: 'esign.rotated_pdf_not_supported',
                    });
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

                loadedDocument = candidate;
                document = candidate.document;
                thumbnailCount = Math.min(6, candidate.document.numPages);
                candidate = null;
                loading = false;
                editorReady = true;
            } catch (error: unknown) {
                if (candidate !== null) {
                    await candidate.destroy();
                    candidate = null;
                }

                if (controller.signal.aborted || generation !== loadGeneration) {
                    return;
                }

                viewerError = error instanceof EsignApiError
                    ? error.details
                    : {
                        ...invalidResponseError(),
                        message: 'PDF tidak dapat dibuka oleh viewer. Dokumen tidak diteruskan ke editor.',
                        code: 'pdf_render_initialization_failed',
                    };
                loading = false;
            }
        })();

        return () => {
            loadGeneration += 1;
            controller.abort();

            const current = loadedDocument;
            loadedDocument = null;
            document = null;
            editorReady = false;

            if (current !== null) {
                void current.destroy();
            }
        };
    });

    $effect(() => {
        const pdf = document;
        const rendered = thumbnailCount;

        if (pdf === null || rendered >= pdf.numPages) {
            return;
        }

        const grow = (): void => {
            thumbnailCount = Math.min(pdf.numPages, rendered + 6);
        };

        if ('requestIdleCallback' in window) {
            const handle = window.requestIdleCallback(grow, { timeout: 600 });

            return () => window.cancelIdleCallback(handle);
        }

        const handle = window.setTimeout(grow, 120);

        return () => window.clearTimeout(handle);
    });

    async function scrollThumbnailToPage(
        page: number,
        behavior: ScrollBehavior = 'smooth',
    ): Promise<void> {
        await tick();

        const targetThumbnail = thumbnailListElement
            ?.querySelector<HTMLElement>(`[data-thumbnail-page="${page}"]`);

        if (targetThumbnail === null || targetThumbnail === undefined) {
            return;
        }

        const thumbnailTop = targetThumbnail.offsetTop
            - Math.max(0, (thumbnailListElement.clientHeight - targetThumbnail.offsetHeight) / 2);

        thumbnailListElement.scrollTo({
            behavior,
            top: Math.max(0, thumbnailTop),
        });
    }

    async function activateWorkspacePage(page: number): Promise<void> {
        const normalizedPage = Math.min(session.pages.length, Math.max(1, page));

        activePage = normalizedPage;
        thumbnailCount = Math.max(thumbnailCount, normalizedPage);
        await scrollThumbnailToPage(normalizedPage);
    }

    function visiblePlacementTarget(): SignaturePlacementTarget | undefined {
        if (pagesElement === undefined) {
            return undefined;
        }

        const workspaceBounds = pagesElement.getBoundingClientRect();
        const pageElements = pagesElement.querySelectorAll<HTMLElement>('[data-page-number]');
        let bestMatch: {
            element: HTMLElement;
            page: number;
            visibleArea: number;
            visibleBottom: number;
            visibleLeft: number;
            visibleRight: number;
            visibleTop: number;
        } | null = null;

        for (const element of pageElements) {
            const page = Number(element.dataset.pageNumber);

            if (!Number.isInteger(page)) {
                continue;
            }

            const bounds = element.getBoundingClientRect();
            const visibleLeft = Math.max(bounds.left, workspaceBounds.left);
            const visibleRight = Math.min(bounds.right, workspaceBounds.right);
            const visibleTop = Math.max(bounds.top, workspaceBounds.top);
            const visibleBottom = Math.min(bounds.bottom, workspaceBounds.bottom);
            const visibleArea = Math.max(0, visibleRight - visibleLeft)
                * Math.max(0, visibleBottom - visibleTop);

            if (visibleArea > (bestMatch?.visibleArea ?? 0)) {
                bestMatch = {
                    element,
                    page,
                    visibleArea,
                    visibleBottom,
                    visibleLeft,
                    visibleRight,
                    visibleTop,
                };
            }
        }

        if (bestMatch === null || bestMatch.visibleArea <= 0) {
            return undefined;
        }

        const page = session.pages.find((item) => item.page === bestMatch.page);

        if (page === undefined) {
            return undefined;
        }

        const bounds = bestMatch.element.getBoundingClientRect();
        const center = domPointToCanonicalPoint({
            x: (bestMatch.visibleLeft + bestMatch.visibleRight) / 2,
            y: (bestMatch.visibleTop + bestMatch.visibleBottom) / 2,
        }, page, {
            left: bounds.left,
            top: bounds.top,
            width: bounds.width,
            height: bounds.height,
        });

        return {
            page: page.page,
            center_x: center.x,
            center_y: center.y,
        };
    }

    function addSignatureAtVisibleCenter(): void {
        const target = visiblePlacementTarget();

        if (target !== undefined) {
            activePage = target.page;
            thumbnailCount = Math.max(thumbnailCount, target.page);
            void scrollThumbnailToPage(target.page);
        }

        onAddSignature(target);
    }

    async function goToPage(page: number): Promise<void> {
        const normalizedPage = Math.min(session.pages.length, Math.max(1, page));

        activePage = normalizedPage;
        thumbnailCount = Math.max(thumbnailCount, normalizedPage);
        await tick();

        const targetPage = pagesElement
            ?.querySelector<HTMLElement>(`[data-page-number="${normalizedPage}"]`);

        if (pagesElement !== undefined && targetPage !== null && targetPage !== undefined) {
            const viewport = pagesElement.getBoundingClientRect();
            const pageBounds = targetPage.getBoundingClientRect();
            const viewportTop = viewport.top + pagesElement.clientTop;
            const viewportLeft = viewport.left + pagesElement.clientLeft;
            const viewportBottom = viewportTop + pagesElement.clientHeight;
            const viewportRight = viewportLeft + pagesElement.clientWidth;
            const pageIsAlreadyVisible = pageBounds.bottom > viewportTop
                && pageBounds.top < viewportBottom
                && pageBounds.right > viewportLeft
                && pageBounds.left < viewportRight;

            if (!pageIsAlreadyVisible) {
                const verticalDelta = pageBounds.top >= viewportBottom
                    ? pageBounds.top - viewportTop
                    : pageBounds.bottom <= viewportTop
                        ? pageBounds.bottom - viewportBottom
                        : 0;
                const horizontalDelta = pageBounds.left >= viewportRight
                    ? pageBounds.left - viewportLeft
                    : pageBounds.right <= viewportLeft
                        ? pageBounds.right - viewportRight
                        : 0;

                pagesElement.scrollBy({
                    behavior: 'smooth',
                    left: horizontalDelta,
                    top: verticalDelta,
                });
            }
        }

        await scrollThumbnailToPage(normalizedPage);
    }

    function zoomIn(): void {
        zoom = Math.min(2.5, Math.round((zoom + 0.1) * 10) / 10);
    }

    function zoomOut(): void {
        zoom = Math.max(0.5, Math.round((zoom - 0.1) * 10) / 10);
    }

    function thumbnailScale(page: number): number {
        const geometry = session.pages[page - 1];
        const width = geometry?.rotation === 90 || geometry?.rotation === 270
            ? geometry.height
            : geometry?.width;

        return Math.min(0.26, 104 / Math.max(1, width ?? 1));
    }

    function registerViewport(metrics: RenderedPdfPageMetrics): void {
        renderedPageMetrics = {
            ...renderedPageMetrics,
            [metrics.page]: metrics,
        };
    }

    function placementsForPage(page: number): SignaturePlacement[] {
        return placements.filter((placement) => placement.page === page);
    }

    function updatePlacement(updated: SignaturePlacement): void {
        placements = placements.map((placement) => (
            placement.client_id === updated.client_id ? updated : placement
        ));
        placementFeedback = '';
    }

    function deletePlacement(clientId: string): void {
        placements = normalizeSignatureOperationIndexes(
            placements.filter((placement) => placement.client_id !== clientId),
        );

        if (selectedPlacementId === clientId) {
            selectedPlacementId = null;
        }

        placementFeedback = '';
    }

    function selectPlacement(clientId: string): void {
        const placement = placements.find((item) => item.client_id === clientId);

        if (placement === undefined) {
            return;
        }

        selectedPlacementId = clientId;
        selectedFooterPage = null;
        inspectorPanel = 'signature';
        placementFeedback = '';

        if (placement.page !== activePage) {
            void goToPage(placement.page);
        }
    }

    function centerSelectedPlacement(): void {
        const selected = selectedPlacement;

        if (selected === null) {
            return;
        }

        const page = session.pages.find((item) => item.page === selected.page);

        if (page === undefined) {
            return;
        }

        const rectangle = findAvailableSignatureRectangle(
            page,
            placements.filter((placement) => placement.client_id !== selected.client_id),
            session.editor,
            selected.width,
            footerPlan?.placements ?? [],
        );

        if (rectangle === null) {
            placementFeedback = 'Tidak tersedia ruang aman untuk memusatkan QR pada halaman ini.';
            return;
        }

        updatePlacement(serializeSignaturePlacement(
            page,
            rectangle,
            selected.client_id,
            selected.operation_index,
        ));
    }

    function changeSelectedSize(delta: number): void {
        const selected = selectedPlacement;

        if (selected === null) {
            return;
        }

        const page = session.pages.find((item) => item.page === selected.page);

        if (page === undefined) {
            return;
        }

        updatePlacement(resizeSquareSignaturePlacement(
            selected,
            page,
            session.editor,
            selected.width + delta,
        ));
    }

    function selectFooter(page: number): void {
        if (footerPlan === null) {
            return;
        }

        selectedFooterPage = page;
        selectedPlacementId = null;
        inspectorPanel = 'footer';
        placementFeedback = '';

        if (page !== activePage) {
            void goToPage(page);
        }
    }

    function updateFooterPlacement(updated: FooterPlacement): void {
        if (footerPlan === null) {
            return;
        }

        footerPlan = replaceFooterPlacement(footerPlan, updated);
        placementFeedback = '';
    }

    function deleteFooterPlacement(page: number): void {
        if (footerPlan === null) {
            return;
        }

        footerPlan = {
            ...footerPlan,
            placements: footerPlan.placements.filter((placement) => placement.page !== page),
        };

        if (selectedFooterPage === page) {
            selectedFooterPage = null;
        }

        placementFeedback = '';
    }

    function updateFooterStyle(update: Partial<Omit<FooterPlan, 'placements'>>): void {
        if (footerPlan === null) {
            return;
        }

        footerPlan = { ...footerPlan, ...update };
    }

    function changeFooterFontSize(delta: number): void {
        if (footerPlan === null) {
            return;
        }

        const size = Math.min(
            session.editor.footer.font_size_max_pt,
            Math.max(
                session.editor.footer.font_size_min_pt,
                footerPlan.font_size_pt + delta,
            ),
        );

        updateFooterStyle({ font_size_pt: normalizeFooterFontSize(size) });
    }

    function resetCurrentFooterPlacement(): void {
        if (footerPlan === null || selectedFooterPage === null) {
            return;
        }

        const defaultPlacement = session.editor.footer.placements.find((placement) => (
            placement.page === selectedFooterPage
        ));

        if (defaultPlacement !== undefined) {
            updateFooterPlacement({ ...defaultPlacement });
        }
    }

    function applyCurrentFooterPlacementToAllPages(): void {
        if (footerPlan === null || selectedFooterPlacement === null) {
            return;
        }

        footerPlan = applyFooterPlacementToAllPages(
            footerPlan,
            selectedFooterPlacement,
            session.pages,
            session.editor,
        );
        placementFeedback = 'Posisi footer halaman aktif diterapkan secara proporsional ke seluruh halaman.';
    }

    function resetFooterToDefaultStyle(): void {
        if (footerPlan === null) {
            return;
        }

        footerPlan = resetFooterStyle(footerPlan, session.editor.footer);
    }
</script>

<section class="esign-editor-shell" aria-label="Area pengaturan posisi tanda tangan" aria-busy={loading}>
    <aside class="esign-editor-shell__thumbnails" aria-label="Daftar halaman">
        <div class="esign-panel-heading">
            <span>Halaman</span>
            <label class="esign-thumbnail-page-select">
                <span class="visually-hidden">Pilih halaman</span>
                <select
                    class="form-select form-select-sm"
                    value={activePage}
                    disabled={document === null}
                    aria-label="Pilih halaman PDF"
                    onchange={(event) => goToPage(Number(event.currentTarget.value))}
                >
                    {#each session.pages as page}
                        <option value={page.page}>{page.page} / {session.pages.length}</option>
                    {/each}
                </select>
            </label>
        </div>

        <div bind:this={thumbnailListElement} class="esign-thumbnail-list">
            {#if document}
                {#each Array.from({ length: thumbnailCount }, (_, index) => index + 1) as page}
                    <button
                        type="button"
                        data-thumbnail-page={page}
                        class:esign-thumbnail-button--active={page === activePage}
                        class="esign-thumbnail-button"
                        aria-label="Buka halaman {page}"
                        aria-current={page === activePage ? 'page' : undefined}
                        onclick={() => goToPage(page)}
                    >
                        <PdfPageCanvas
                            {document}
                            pageNumber={page}
                            expectedGeometry={session.pages[page - 1]}
                            scale={thumbnailScale(page)}
                            thumbnail
                            deferUntilVisible
                        />
                        <span class="esign-thumbnail-button__label">
                            <span>{page}</span>
                            {#if placementsForPage(page).length > 0}
                                <span class="badge bg-gradient-primary">
                                    {placementsForPage(page).length} QR
                                </span>
                            {/if}
                            {#if footerPlan?.placements.some((placement) => placement.page === page)}
                                <span class="badge bg-gradient-info">Footer</span>
                            {/if}
                        </span>
                    </button>
                {/each}
            {:else}
                <div class="esign-empty-panel">Thumbnail tersedia setelah PDF selesai dimuat.</div>
            {/if}
        </div>
    </aside>

    <div bind:this={workspaceElement} class="esign-editor-shell__workspace">
        <div class="esign-workspace-toolbar" aria-label="Toolbar PDF">
            <div class="esign-workspace-toolbar__navigation">
                <div class="btn-group btn-group-sm" role="group" aria-label="Navigasi halaman">
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        disabled={activePage <= 1 || document === null}
                        aria-label="Halaman sebelumnya"
                        onclick={() => goToPage(activePage - 1)}
                    >
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        disabled={activePage >= session.pages.length || document === null}
                        aria-label="Halaman berikutnya"
                        onclick={() => goToPage(activePage + 1)}
                    >
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>

                <span class="esign-workspace-toolbar__page" aria-label={`Halaman ${activePage} dari ${session.pages.length}`}>
                    {activePage}/{session.pages.length}
                </span>
            </div>

            <div class="esign-workspace-toolbar__placement-actions" aria-label="Aksi penempatan QR">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary mb-0"
                    disabled={placements.length === 0}
                    title="Kembalikan seluruh QR ke posisi aman"
                    onclick={onResetPlacements}
                >
                    <i class="fas fa-rotate-left me-1" aria-hidden="true"></i>
                    Reset Posisi
                </button>
                <button
                    type="button"
                    class="btn btn-sm bg-gradient-primary mb-0"
                    disabled={!editorReady || placements.length >= session.editor.maximum_signature_count}
                    title={placements.length >= session.editor.maximum_signature_count
                        ? `Maksimal ${session.editor.maximum_signature_count} QR`
                        : `Tambahkan QR pada halaman ${activePage}`}
                    onclick={addSignatureAtVisibleCenter}
                >
                    <i class="fas fa-qrcode me-1" aria-hidden="true"></i>
                    Tambah QR{placements.length > 0 ? ` (${placements.length})` : ''}
                </button>
            </div>

            <div class="btn-group btn-group-sm esign-workspace-toolbar__zoom" role="group" aria-label="Pengaturan zoom">
                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    disabled={document === null || zoom <= 0.5}
                    aria-label="Perkecil"
                    onclick={zoomOut}
                >
                    <i class="fas fa-minus" aria-hidden="true"></i>
                </button>
                <button
                    class="btn btn-outline-secondary esign-zoom-value"
                    type="button"
                    disabled={document === null}
                    title="Kembalikan ke lebar area"
                    onclick={() => zoom = 1}
                >
                    {Math.round(zoom * 100)}%
                </button>
                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    disabled={document === null || zoom >= 2.5}
                    aria-label="Perbesar"
                    onclick={zoomIn}
                >
                    <i class="fas fa-plus" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div
            bind:this={pagesElement}
            class="esign-pdf-workspace"
            role="region"
            aria-label="Preview halaman PDF"
        >
            {#if loading}
                <div class="esign-pdf-viewer-state" role="status">
                    <span class="spinner-border text-primary" aria-hidden="true"></span>
                    <div>
                        <p class="fw-semibold mb-1">Memuat PDF dari server</p>
                        <p class="text-sm text-secondary mb-0">Dokumen dimuat sebagai binary, bukan Base64.</p>
                    </div>
                </div>
            {:else if viewerError}
                <div class="esign-pdf-viewer-state esign-pdf-viewer-state--error" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <div>
                        <p class="fw-semibold mb-1">PDF tidak dapat ditampilkan</p>
                        <p class="text-sm text-secondary mb-1">{viewerError.message}</p>
                        {#if viewerError.code}
                            <p class="text-xs text-secondary mb-0">Kode: {viewerError.code}</p>
                        {/if}
                    </div>
                </div>
            {:else if document}
                <div class="esign-pdf-pages">
                    {#each viewerPages as page (page)}
                        <div
                            class:esign-pdf-pages__item--active={page === activePage}
                            class="esign-pdf-pages__item"
                            aria-current={page === activePage ? 'page' : undefined}
                        >
                            <div class="esign-pdf-pages__label">
                                <span>Halaman {page}</span>
                                {#if page === activePage}
                                    <span class="esign-pdf-pages__active-badge">
                                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                                        Aktif
                                    </span>
                                {/if}
                                {#if session.pages[page - 1]?.rotation !== 0}
                                    <span class="badge bg-gradient-warning">
                                        Rotasi {session.pages[page - 1]?.rotation}°
                                    </span>
                                {/if}
                            </div>
                            <PdfPageCanvas
                                {document}
                                pageNumber={page}
                                expectedGeometry={session.pages[page - 1]}
                                scale={renderScale}
                                deferUntilVisible
                                placements={placementsForPage(page)}
                                {selectedPlacementId}
                                {invalidPlacementIds}
                                {footerPlan}
                                {selectedFooterPage}
                                {invalidFooterPages}
                                editor={session.editor}
                                onViewportReady={registerViewport}
                                onPageSelect={activateWorkspacePage}
                                onPlacementSelect={selectPlacement}
                                onPlacementChange={updatePlacement}
                                onPlacementDelete={deletePlacement}
                                onPlacementDeselect={() => selectedPlacementId = null}
                                onFooterSelect={selectFooter}
                                onFooterChange={updateFooterPlacement}
                                onFooterDelete={deleteFooterPlacement}
                                onFooterDeselect={() => selectedFooterPage = null}
                            />
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </div>

    <aside class="esign-editor-shell__inspector" aria-label="Informasi PDF">
        <nav class="esign-inspector-tabs" aria-label="Panel pengaturan editor">
            <button
                type="button"
                class:esign-inspector-tabs__button--active={inspectorPanel === 'signature'}
                class="esign-inspector-tabs__button"
                aria-pressed={inspectorPanel === 'signature'}
                onclick={() => inspectorPanel = 'signature'}
            >
                <i class="fas fa-qrcode" aria-hidden="true"></i>
                QR
                <span class="badge bg-gradient-primary">{placements.length}</span>
            </button>
            {#if session.editor.footer.allowed && footerPlan}
                <button
                    type="button"
                    class:esign-inspector-tabs__button--active={inspectorPanel === 'footer'}
                    class="esign-inspector-tabs__button"
                    aria-pressed={inspectorPanel === 'footer'}
                    onclick={() => inspectorPanel = 'footer'}
                >
                    <i class="fas fa-align-left" aria-hidden="true"></i>
                    Footer
                </button>
            {/if}
            <button
                type="button"
                class:esign-inspector-tabs__button--active={inspectorPanel === 'document'}
                class="esign-inspector-tabs__button"
                aria-pressed={inspectorPanel === 'document'}
                onclick={() => inspectorPanel = 'document'}
            >
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Info
            </button>
        </nav>

        <div class="esign-inspector-content">
        {#if inspectorPanel === 'document'}
        <div class="esign-panel-heading">Ringkasan dokumen</div>
        <dl class="esign-summary-list esign-summary-list--compact esign-summary-list--document mb-0">
            <div
                class="esign-summary-list__status"
                class:esign-summary-list__status--signed={session.signature_state === 'signed'}
            >
                <dt>Status tanda tangan</dt>
                <dd>
                    <i
                        class="fas {session.signature_state === 'signed' ? 'fa-circle-check' : 'fa-clock'}"
                        aria-hidden="true"
                    ></i>
                    <span>{session.signature_state === 'signed' ? 'Sudah memiliki TTE' : 'Belum memiliki TTE'}</span>
                </dd>
                {#if session.signature_state === 'signed'}
                    <small>{session.verified_signature_count} tanda tangan terverifikasi</small>
                {:else}
                    <small>Dokumen siap diproses sesuai alur penandatangan.</small>
                {/if}
            </div>
            <div class="esign-summary-list__identity">
                <dt>Penandatangan saat ini</dt>
                <dd>
                    <strong>{session.signer_name}</strong>
                    <small>NIK {session.masked_nik}</small>
                </dd>
            </div>
            <div class="esign-summary-list__metric">
                <dt>Halaman</dt>
                <dd>{session.pages.length}</dd>
            </div>
            <div class="esign-summary-list__metric">
                <dt>Versi dokumen</dt>
                <dd>{session.artifact_version}</dd>
            </div>
        </dl>
        {/if}
        {#if inspectorPanel === 'signature'}
        <div class="esign-panel-heading esign-panel-heading--section">
            <span>QR tanda tangan</span>
            <span class="badge bg-gradient-primary">
                {placements.length}/{session.editor.maximum_signature_count}
            </span>
        </div>
        <div class="esign-inspector-hint">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <span>Pilih QR untuk mengatur ukuran; geser QR pada halaman untuk mengubah posisi.</span>
        </div>
        {#if placements.length === 0}
            <div class="esign-empty-panel esign-empty-panel--compact">
                Tekan “Tambah QR” untuk menempatkan tanda tangan pada halaman aktif.
            </div>
        {:else}
            <div class="esign-signature-list" aria-label="Daftar QR tanda tangan">
                {#each placements as placement (placement.client_id)}
                    <button
                        type="button"
                        class:esign-signature-list__item--active={placement.client_id === selectedPlacementId}
                        class:esign-signature-list__item--invalid={invalidPlacementIds.includes(placement.client_id)}
                        class="esign-signature-list__item"
                        onclick={() => selectPlacement(placement.client_id)}
                    >
                        <span class="esign-signature-list__number">{placement.operation_index + 1}</span>
                        <span>
                            <strong>QR {placement.operation_index + 1}</strong>
                            <small>Halaman {placement.page} · {Math.round(placement.width)} pt</small>
                        </span>
                        {#if invalidPlacementIds.includes(placement.client_id)}
                            <i class="fas fa-triangle-exclamation" aria-label="Posisi perlu diperbaiki"></i>
                        {/if}
                    </button>
                {/each}
            </div>
        {/if}

        {#if selectedPlacement}
            <div class="esign-signature-inspector">
                <div class="esign-signature-inspector__heading">
                    <strong>QR {selectedPlacement.operation_index + 1}</strong>
                    <span>Halaman {selectedPlacement.page}</span>
                </div>
                <div class="esign-signature-size-control">
                    <span class="esign-signature-size-control__label">
                        <i class="fas fa-expand" aria-hidden="true"></i>
                        <span>Ukuran QR</span>
                    </span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ubah ukuran QR">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            disabled={selectedPlacement.width <= session.editor.qr.minimum_size_pt}
                            aria-label="Perkecil ukuran QR"
                            onclick={() => changeSelectedSize(-6)}
                        >
                            <i class="fas fa-minus" aria-hidden="true"></i>
                        </button>
                        <span class="btn btn-outline-secondary disabled">
                            {Math.round(selectedPlacement.width)} pt
                        </span>
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            disabled={selectedPlacement.width >= selectedMaximumSize}
                            aria-label="Perbesar ukuran QR"
                            onclick={() => changeSelectedSize(6)}
                        >
                            <i class="fas fa-plus" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="esign-signature-inspector__actions">
                    <button type="button" class="btn btn-sm btn-outline-primary mb-0" onclick={centerSelectedPlacement}>
                        <i class="fas fa-crosshairs me-1" aria-hidden="true"></i>
                        Pusatkan
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger mb-0"
                        onclick={() => deletePlacement(selectedPlacement.client_id)}
                    >
                        <i class="fas fa-trash me-1" aria-hidden="true"></i>
                        Hapus
                    </button>
                </div>
                {#if selectedPlacementIssues.length > 0}
                    <div class="esign-placement-feedback esign-placement-feedback--danger" role="alert">
                        {selectedPlacementIssues[0]?.message}
                    </div>
                {/if}
            </div>
        {/if}
        {/if}

        {#if inspectorPanel === 'footer' && session.editor.footer.allowed && footerPlan}
            <div class="esign-panel-heading esign-panel-heading--section">
                <span>Footer dokumen</span>
                <span class="badge bg-gradient-info">
                    {footerPlan.placements.length} / {session.pages.length} halaman
                </span>
            </div>
            <div class="esign-footer-inspector">
                <div class="esign-inspector-hint esign-inspector-hint--footer">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    <span>{footerPlan.placements.length}/{session.pages.length} halaman memakai footer. Hapus dari halaman melalui tombol × pada footer.</span>
                </div>
                <label class="form-label" for="esignFooterText">Teks footer <span>· rata tengah</span></label>
                <textarea
                    id="esignFooterText"
                    class="form-control form-control-sm"
                    rows="4"
                    maxlength="1000"
                    value={footerPlan.text}
                    oninput={(event) => updateFooterStyle({ text: event.currentTarget.value })}
                ></textarea>
                <div class="esign-footer-inspector__row">
                    <div class="esign-footer-inspector__field">
                        <label class="form-label" for="esignFooterFont">Jenis font</label>
                        <select
                            id="esignFooterFont"
                            class="form-select form-select-sm"
                            value={footerPlan.font_key}
                            onchange={(event) => updateFooterStyle({ font_key: event.currentTarget.value })}
                        >
                            {#each Object.entries(session.editor.footer.allowed_fonts) as [fontKey, fontLabel]}
                                <option value={fontKey}>{fontLabel}</option>
                            {/each}
                        </select>
                    </div>
                    <div class="esign-footer-inspector__field">
                        <span class="form-label">Ukuran</span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Ukuran font footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                disabled={footerPlan.font_size_pt <= session.editor.footer.font_size_min_pt}
                                aria-label="Perkecil font footer"
                                onclick={() => changeFooterFontSize(-FOOTER_FONT_SIZE_STEP_PT)}
                            >
                                <i class="fas fa-minus" aria-hidden="true"></i>
                            </button>
                            <span class="btn btn-outline-secondary disabled">{footerPlan.font_size_pt.toFixed(1)} pt</span>
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                disabled={footerPlan.font_size_pt >= session.editor.footer.font_size_max_pt}
                                aria-label="Perbesar font footer"
                                onclick={() => changeFooterFontSize(FOOTER_FONT_SIZE_STEP_PT)}
                            >
                                <i class="fas fa-plus" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="esign-footer-style-controls" role="group" aria-label="Style footer">
                    <button
                        type="button"
                        class:active={footerPlan.is_bold}
                        class="btn btn-sm btn-outline-secondary"
                        aria-label="Tebal"
                        aria-pressed={footerPlan.is_bold}
                        onclick={() => updateFooterStyle({ is_bold: !footerPlan.is_bold })}
                    ><strong>B</strong></button>
                    <button
                        type="button"
                        class:active={footerPlan.is_italic}
                        class="btn btn-sm btn-outline-secondary"
                        aria-label="Miring"
                        aria-pressed={footerPlan.is_italic}
                        onclick={() => updateFooterStyle({ is_italic: !footerPlan.is_italic })}
                    ><em>I</em></button>
                    <button
                        type="button"
                        class:active={footerPlan.is_underline}
                        class="btn btn-sm btn-outline-secondary"
                        aria-label="Garis bawah"
                        aria-pressed={footerPlan.is_underline}
                        onclick={() => updateFooterStyle({ is_underline: !footerPlan.is_underline })}
                    ><span class="text-decoration-underline">U</span></button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary ms-auto"
                        onclick={resetFooterToDefaultStyle}
                    >Reset style</button>
                </div>
                <div class="esign-footer-position-controls">
                    <div>
                        <strong><i class="fas fa-arrows-up-down-left-right" aria-hidden="true"></i> Posisi per halaman</strong>
                        <small>
                            {selectedFooterPage === null
                                ? 'Pilih footer pada preview.'
                                : `Footer halaman ${selectedFooterPage} dipilih.`}
                        </small>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary mb-0"
                        onclick={() => selectFooter(activePage)}
                    >Pilih halaman {activePage}</button>
                    <div class="esign-footer-position-controls__actions">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary mb-0"
                            disabled={selectedFooterPage === null}
                            onclick={resetCurrentFooterPlacement}
                        >Reset halaman</button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary mb-0"
                            disabled={selectedFooterPlacement === null}
                            onclick={applyCurrentFooterPlacementToAllPages}
                        >Terapkan ke semua</button>
                    </div>
                </div>
                {#if selectedFooterIssues.length > 0}
                    <div class="esign-placement-feedback esign-placement-feedback--danger" role="alert">
                        {selectedFooterIssues[0]}
                    </div>
                {/if}
            </div>
        {/if}

        {#if placementFeedback !== ''}
            <div class="esign-placement-feedback esign-placement-feedback--warning" role="status">
                {placementFeedback}
            </div>
        {/if}
        </div>
    </aside>
</section>
