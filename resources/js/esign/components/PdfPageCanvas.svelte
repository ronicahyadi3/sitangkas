<script lang="ts">
    import { onMount } from 'svelte';
    import type {
        PDFDocumentProxy,
        PDFPageProxy,
        RenderTask,
    } from 'pdfjs-dist';
    import { displayedPageSize } from '../geometry/transform';
    import type { RenderedPdfPageMetrics } from '../geometry/types';
    import type {
        FooterPlacement,
        FooterPlan,
        PdfPageGeometry,
        PreparedSignatureOperation,
        SignaturePlacement,
        VisibleSigningEditorConfiguration,
    } from '../types';
    import FooterPlacementOverlay from './FooterPlacementOverlay.svelte';
    import PreparedSignatureOverlay from './PreparedSignatureOverlay.svelte';
    import SignaturePlacementOverlay from './SignaturePlacementOverlay.svelte';

    let {
        document,
        pageNumber,
        expectedGeometry,
        scale,
        thumbnail = false,
        deferUntilVisible = false,
        placements = [],
        selectedPlacementId = null,
        invalidPlacementIds = [],
        footerPlan = null,
        selectedFooterPage = null,
        invalidFooterPages = [],
        preparedOperations = [],
        preparedQrImages = {},
        editor,
        onViewportReady,
        onRenderReady,
        onRenderError,
        onPlacementSelect,
        onPlacementChange,
        onPlacementDelete,
        onPlacementDeselect,
        onFooterSelect,
        onFooterChange,
        onFooterDeselect,
    }: {
        document: PDFDocumentProxy;
        pageNumber: number;
        expectedGeometry: PdfPageGeometry;
        scale: number;
        thumbnail?: boolean;
        deferUntilVisible?: boolean;
        placements?: SignaturePlacement[];
        selectedPlacementId?: string | null;
        invalidPlacementIds?: string[];
        footerPlan?: FooterPlan | null;
        selectedFooterPage?: number | null;
        invalidFooterPages?: number[];
        preparedOperations?: PreparedSignatureOperation[];
        preparedQrImages?: Record<number, string>;
        editor?: VisibleSigningEditorConfiguration;
        onViewportReady?: (metrics: RenderedPdfPageMetrics) => void;
        onRenderReady?: (page: number) => void;
        onRenderError?: (page: number) => void;
        onPlacementSelect?: (clientId: string) => void;
        onPlacementChange?: (placement: SignaturePlacement) => void;
        onPlacementDelete?: (clientId: string) => void;
        onPlacementDeselect?: () => void;
        onFooterSelect?: (page: number) => void;
        onFooterChange?: (placement: FooterPlacement) => void;
        onFooterDeselect?: () => void;
    } = $props();

    let hostElement: HTMLDivElement;
    let canvasElement: HTMLCanvasElement;
    let intersectsViewport = $state(false);
    let isVisible = $derived(!deferUntilVisible || intersectsViewport);
    let renderState = $state<'idle' | 'loading' | 'ready' | 'failed'>('idle');
    let cssWidth = $state(0);
    let cssHeight = $state(0);
    let renderGeneration = 0;
    let renderTask: RenderTask | null = null;
    let renderedPage: PDFPageProxy | null = null;

    onMount(() => {
        if (!deferUntilVisible || typeof IntersectionObserver === 'undefined') {
            intersectsViewport = true;

            return;
        }

        const observer = new IntersectionObserver((entries) => {
            intersectsViewport = entries.some((entry) => entry.isIntersecting);
        }, {
            rootMargin: '160px',
            threshold: 0.01,
        });

        observer.observe(hostElement);

        return () => observer.disconnect();
    });

    $effect(() => {
        const pdf = document;
        const page = pageNumber;
        const renderScale = scale;
        const canvas = canvasElement;
        const shouldRender = isVisible;

        if (!canvas || !shouldRender || renderScale <= 0) {
            return;
        }

        const generation = ++renderGeneration;
        renderState = 'loading';

        void (async (): Promise<void> => {
            try {
                const pdfPage = await pdf.getPage(page);

                if (generation !== renderGeneration) {
                    pdfPage.cleanup();
                    return;
                }

                renderedPage = pdfPage;

                const viewport = pdfPage.getViewport({ scale: renderScale });
                const outputScale = Math.min(window.devicePixelRatio || 1, thumbnail ? 1.25 : 2);
                const width = Math.max(1, Math.floor(viewport.width));
                const height = Math.max(1, Math.floor(viewport.height));
                const displayedSize = displayedPageSize(expectedGeometry);

                cssWidth = width;
                cssHeight = height;
                canvas.width = Math.max(1, Math.floor(viewport.width * outputScale));
                canvas.height = Math.max(1, Math.floor(viewport.height * outputScale));
                canvas.style.width = `${width}px`;
                canvas.style.height = `${height}px`;

                if (!thumbnail) {
                    onViewportReady?.({
                        page,
                        page_width_pt: expectedGeometry.width,
                        page_height_pt: expectedGeometry.height,
                        page_rotation: expectedGeometry.rotation,
                        viewport_width_px: width,
                        viewport_height_px: height,
                        scale_x: width / displayedSize.width,
                        scale_y: height / displayedSize.height,
                    });
                }

                const transform = outputScale === 1
                    ? undefined
                    : [outputScale, 0, 0, outputScale, 0, 0];

                renderTask = pdfPage.render({
                    canvas,
                    viewport,
                    transform,
                    background: '#ffffff',
                });
                await renderTask.promise;

                if (generation === renderGeneration) {
                    renderState = 'ready';
                    onRenderReady?.(page);
                }
            } catch (error: unknown) {
                const cancelled = error instanceof Error
                    && (error.name === 'RenderingCancelledException' || error.name === 'AbortException');

                if (!cancelled && generation === renderGeneration) {
                    renderState = 'failed';
                    onRenderError?.(page);
                }
            } finally {
                if (generation === renderGeneration) {
                    renderTask = null;
                }
            }
        })();

        return () => {
            renderGeneration += 1;
            renderTask?.cancel();
            renderTask = null;
            renderedPage?.cleanup();
            renderedPage = null;

            if (deferUntilVisible) {
                canvas.width = 1;
                canvas.height = 1;
                renderState = 'idle';
            }
        };
    });
</script>

<div
    bind:this={hostElement}
    class:esign-pdf-page--thumbnail={thumbnail}
    class="esign-pdf-page"
    data-page-number={pageNumber}
    data-page-width-pt={expectedGeometry.width}
    data-page-height-pt={expectedGeometry.height}
    data-page-rotation={expectedGeometry.rotation}
    style:width={cssWidth > 0 ? `${cssWidth}px` : undefined}
    style:height={cssHeight > 0 ? `${cssHeight}px` : undefined}
    aria-label="Halaman PDF {pageNumber}"
>
    <canvas
        bind:this={canvasElement}
        class="esign-pdf-page__canvas"
        aria-label="Isi halaman {pageNumber}"
    ></canvas>

    {#if !thumbnail}
        <div
            class="esign-pdf-page__overlay"
            data-esign-placement-layer
            data-coordinate-origin="top_left"
            data-measurement-unit="pt"
            aria-label="Layer penempatan halaman {pageNumber}"
        >
            {#if editor}
                {#if (selectedPlacementId !== null && placements.some((placement) => placement.client_id === selectedPlacementId)) || selectedFooterPage === pageNumber}
                    <div
                        class="esign-placement-safe-area"
                        style:left={`${(editor.safe_margin_pt / expectedGeometry.width) * cssWidth}px`}
                        style:top={`${(editor.safe_margin_pt / expectedGeometry.height) * cssHeight}px`}
                        style:width={`${((expectedGeometry.width - (editor.safe_margin_pt * 2)) / expectedGeometry.width) * cssWidth}px`}
                        style:height={`${((expectedGeometry.height - (editor.safe_margin_pt * 2)) / expectedGeometry.height) * cssHeight}px`}
                        aria-hidden="true"
                    ></div>
                {/if}
                {#if footerPlan}
                    {@const footerPlacement = footerPlan.placements.find((placement) => placement.page === pageNumber)}
                    {#if footerPlacement}
                        <FooterPlacementOverlay
                            placement={footerPlacement}
                            footer={footerPlan}
                            page={expectedGeometry}
                            {editor}
                            pageWidthPx={cssWidth}
                            pageHeightPx={cssHeight}
                            selected={selectedFooterPage === pageNumber}
                            invalid={invalidFooterPages.includes(pageNumber)}
                            onSelect={onFooterSelect ?? (() => undefined)}
                            onChange={onFooterChange ?? (() => undefined)}
                            onDeselect={onFooterDeselect ?? (() => undefined)}
                        />
                    {/if}
                {/if}
                {#each placements as placement (placement.client_id)}
                    <SignaturePlacementOverlay
                        {placement}
                        page={expectedGeometry}
                        {editor}
                        pageWidthPx={cssWidth}
                        pageHeightPx={cssHeight}
                        selected={placement.client_id === selectedPlacementId}
                        invalid={invalidPlacementIds.includes(placement.client_id)}
                        onSelect={onPlacementSelect ?? (() => undefined)}
                        onChange={onPlacementChange ?? (() => undefined)}
                        onDelete={onPlacementDelete ?? (() => undefined)}
                        onDeselect={onPlacementDeselect ?? (() => undefined)}
                    />
                {/each}
            {/if}
            {#each preparedOperations as operation (operation.operation_index)}
                {@const imageUrl = preparedQrImages[operation.operation_index]}
                {#if imageUrl}
                    <PreparedSignatureOverlay
                        {operation}
                        page={expectedGeometry}
                        {imageUrl}
                        pageWidthPx={cssWidth}
                        pageHeightPx={cssHeight}
                    />
                {/if}
            {/each}
        </div>
    {/if}

    {#if renderState === 'loading' || renderState === 'idle'}
        <div class="esign-pdf-page__status" role="status">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <span class="visually-hidden">Merender halaman {pageNumber}</span>
        </div>
    {:else if renderState === 'failed'}
        <div class="esign-pdf-page__status esign-pdf-page__status--error" role="alert">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span>Halaman gagal dirender</span>
        </div>
    {/if}
</div>
