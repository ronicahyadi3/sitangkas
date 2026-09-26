<script lang="ts">
    import { onMount } from 'svelte';
    import type {
        PDFDocumentProxy,
        PDFPageProxy,
        RenderTask,
    } from 'pdfjs-dist';
    import type { PdfPageGeometry } from '../../esign/types';

    let {
        document,
        pageNumber,
        expectedGeometry,
        scale,
        thumbnail = false,
        deferUntilVisible = false,
        onRenderError,
    }: {
        document: PDFDocumentProxy;
        pageNumber: number;
        expectedGeometry: PdfPageGeometry;
        scale: number;
        thumbnail?: boolean;
        deferUntilVisible?: boolean;
        onRenderError?: (page: number) => void;
    } = $props();

    let hostElement: HTMLDivElement;
    let canvasElement: HTMLCanvasElement;
    let intersectsViewport = $state(false);
    let renderState = $state<'idle' | 'loading' | 'ready' | 'failed'>('idle');
    let cssWidth = $state(0);
    let cssHeight = $state(0);
    let renderGeneration = 0;
    let renderTask: RenderTask | null = null;
    let renderedPage: PDFPageProxy | null = null;

    let shouldRender = $derived(!deferUntilVisible || intersectsViewport);
    let reservedSize = $derived.by(() => {
        const rotated = expectedGeometry.rotation === 90 || expectedGeometry.rotation === 270;

        return {
            width: Math.max(1, Math.floor((rotated ? expectedGeometry.height : expectedGeometry.width) * scale)),
            height: Math.max(1, Math.floor((rotated ? expectedGeometry.width : expectedGeometry.height) * scale)),
        };
    });

    onMount(() => {
        if (!deferUntilVisible || typeof IntersectionObserver === 'undefined') {
            intersectsViewport = true;
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            intersectsViewport = entries.some((entry) => entry.isIntersecting);
        }, {
            rootMargin: '180px',
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

                cssWidth = width;
                cssHeight = height;
                canvas.width = Math.max(1, Math.floor(viewport.width * outputScale));
                canvas.height = Math.max(1, Math.floor(viewport.height * outputScale));
                canvas.style.width = `${width}px`;
                canvas.style.height = `${height}px`;

                renderTask = pdfPage.render({
                    canvas,
                    viewport,
                    transform: outputScale === 1
                        ? undefined
                        : [outputScale, 0, 0, outputScale, 0, 0],
                    background: '#ffffff',
                });
                await renderTask.promise;

                if (generation === renderGeneration) {
                    renderState = 'ready';
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
    class:secure-pdf-canvas-page--thumbnail={thumbnail}
    class="secure-pdf-canvas-page"
    style:width={`${cssWidth > 0 ? cssWidth : reservedSize.width}px`}
    style:height={`${cssHeight > 0 ? cssHeight : reservedSize.height}px`}
    aria-label="Halaman PDF {pageNumber}"
>
    <canvas bind:this={canvasElement} class="secure-pdf-canvas-page__canvas"></canvas>

    {#if renderState === 'loading' || renderState === 'idle'}
        <div class="secure-pdf-canvas-page__status" role="status">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <span class="visually-hidden">Merender halaman {pageNumber}</span>
        </div>
    {:else if renderState === 'failed'}
        <div class="secure-pdf-canvas-page__status secure-pdf-canvas-page__status--error" role="alert">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span>Halaman gagal dirender</span>
        </div>
    {/if}
</div>
