<script lang="ts">
    import {
        constrainRectangleToSafeArea,
        keyboardDelta,
        moveRectangleWithinSafeArea,
        safeAreaForPage,
    } from '../geometry/constraints';
    import {
        estimatedFooterTextWidth,
        footerCssFontFamily,
        wrapFooterText,
    } from '../geometry/footer';
    import { serializeFooterPlacement } from '../geometry/serialization';
    import { domPointToCanonicalPoint } from '../geometry/transform';
    import type { DomRectangle } from '../geometry/types';
    import type {
        FooterPlacement,
        FooterPlan,
        PdfPageGeometry,
        VisibleSigningEditorConfiguration,
    } from '../types';

    interface PointerInteraction {
        kind: 'move' | 'resize';
        pointerId: number;
        startPoint: { x: number; y: number };
        startPlacement: FooterPlacement;
        pageBounds: DomRectangle;
        resizeCorner?: ResizeCorner;
    }

    type ResizeCorner = 'nw' | 'ne' | 'sw' | 'se';

    let {
        placement,
        footer,
        page,
        editor,
        pageWidthPx,
        pageHeightPx,
        selected,
        invalid,
        onSelect,
        onChange,
        onDelete,
        onDeselect,
    }: {
        placement: FooterPlacement;
        footer: FooterPlan;
        page: PdfPageGeometry;
        editor: VisibleSigningEditorConfiguration;
        pageWidthPx: number;
        pageHeightPx: number;
        selected: boolean;
        invalid: boolean;
        onSelect: (page: number) => void;
        onChange: (placement: FooterPlacement) => void;
        onDelete: (page: number) => void;
        onDeselect: () => void;
    } = $props();

    let overlayElement: HTMLDivElement;
    let interaction: PointerInteraction | null = null;
    let wasSelected = false;

    let left = $derived((placement.origin_x / page.width) * pageWidthPx);
    let top = $derived((placement.origin_y / page.height) * pageHeightPx);
    let width = $derived((placement.width / page.width) * pageWidthPx);
    let height = $derived((placement.height / page.height) * pageHeightPx);
    let textLines = $derived(wrapFooterText(
        footer.text,
        placement.width,
        footer.font_size_pt,
        footer.font_key,
        footer.is_bold,
    ));

    function lineCenterX(): number {
        return placement.width / 2;
    }

    function lineBaselineY(index: number): number {
        const lineHeight = footer.font_size_pt * 1.35;
        const requiredHeight = textLines.length * lineHeight;
        const verticalPadding = Math.max(0, (placement.height - requiredHeight) / 2);

        return placement.height
            - verticalPadding
            - ((textLines.length - 1 - index) * lineHeight)
            - (footer.font_size_pt * 0.2);
    }

    function underlineY(index: number): number {
        return lineBaselineY(index) + 1.2;
    }

    $effect(() => {
        if (selected && !wasSelected) {
            window.requestAnimationFrame(() => overlayElement?.focus({ preventScroll: true }));
        }

        wasSelected = selected;
    });

    function currentPageBounds(): DomRectangle {
        const pageElement = overlayElement.parentElement;
        const bounds = pageElement?.getBoundingClientRect();

        return {
            left: bounds?.left ?? 0,
            top: bounds?.top ?? 0,
            width: bounds?.width ?? pageWidthPx,
            height: bounds?.height ?? pageHeightPx,
        };
    }

    function startInteraction(event: PointerEvent, kind: 'move' | 'resize', resizeCorner?: ResizeCorner): void {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        onSelect(placement.page);

        overlayElement.setPointerCapture(event.pointerId);
        const pageBounds = currentPageBounds();

        interaction = {
            kind,
            pointerId: event.pointerId,
            startPoint: domPointToCanonicalPoint({ x: event.clientX, y: event.clientY }, page, pageBounds),
            startPlacement: { ...placement },
            pageBounds,
            resizeCorner,
        };
    }

    function handlePointerMove(event: PointerEvent): void {
        const current = interaction;

        if (current === null || current.pointerId !== event.pointerId) {
            return;
        }

        event.preventDefault();
        const point = domPointToCanonicalPoint({ x: event.clientX, y: event.clientY }, page, current.pageBounds);
        const rectangle = current.kind === 'move'
            ? moveRectangleWithinSafeArea(
                current.startPlacement,
                point.x - current.startPoint.x,
                point.y - current.startPoint.y,
                page,
                editor.safe_margin_pt,
            )
            : resizeRectangleFromCorner(
                current.startPlacement,
                current.resizeCorner ?? 'se',
                point,
            );

        onChange(serializeFooterPlacement(page, rectangle));
    }

    function resizeRectangleFromCorner(
        startPlacement: FooterPlacement,
        corner: ResizeCorner,
        point: { x: number; y: number },
    ): FooterPlacement {
        const area = safeAreaForPage(page, editor.safe_margin_pt);
        const minimumWidth = Math.max(24, footer.font_size_pt * 4);
        const minimumHeight = Math.max(12, footer.font_size_pt * 1.35);
        const movingLeft = corner.endsWith('w');
        const movingTop = corner.startsWith('n');
        const fixedX = movingLeft ? startPlacement.origin_x + startPlacement.width : startPlacement.origin_x;
        const fixedY = movingTop ? startPlacement.origin_y + startPlacement.height : startPlacement.origin_y;
        const minimumX = movingLeft
            ? Math.max(area.origin_x, fixedX - Math.min(minimumWidth, fixedX - area.origin_x))
            : fixedX + Math.min(minimumWidth, area.origin_x + area.width - fixedX);
        const maximumX = movingLeft
            ? fixedX
            : area.origin_x + area.width;
        const minimumY = movingTop
            ? Math.max(area.origin_y, fixedY - Math.min(minimumHeight, fixedY - area.origin_y))
            : fixedY + Math.min(minimumHeight, area.origin_y + area.height - fixedY);
        const maximumY = movingTop
            ? fixedY
            : area.origin_y + area.height;
        const movingX = Math.min(maximumX, Math.max(minimumX, point.x));
        const movingY = Math.min(maximumY, Math.max(minimumY, point.y));
        const left = movingLeft ? movingX : fixedX;
        const top = movingTop ? movingY : fixedY;
        const right = movingLeft ? fixedX : movingX;
        const bottom = movingTop ? fixedY : movingY;

        return constrainRectangleToSafeArea({
            origin_x: left,
            origin_y: top,
            width: right - left,
            height: bottom - top,
        }, page, editor.safe_margin_pt);
    }

    function finishMove(event: PointerEvent): void {
        if (interaction?.pointerId !== event.pointerId) {
            return;
        }

        if (overlayElement.hasPointerCapture(event.pointerId)) {
            overlayElement.releasePointerCapture(event.pointerId);
        }

        interaction = null;
    }

    function handleKeydown(event: KeyboardEvent): void {
        const delta = keyboardDelta(event.key, event.shiftKey);

        if (delta !== null) {
            event.preventDefault();
            const rectangle = moveRectangleWithinSafeArea(
                placement,
                delta.x,
                delta.y,
                page,
                editor.safe_margin_pt,
            );

            onChange(serializeFooterPlacement(page, rectangle));

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            onDeselect();
        }
    }

    function deletePlacement(event: MouseEvent): void {
        event.preventDefault();
        event.stopPropagation();
        onDelete(placement.page);
    }
</script>

<div
    bind:this={overlayElement}
    class:esign-footer-placement--selected={selected}
    class:esign-footer-placement--invalid={invalid}
    class="esign-footer-placement"
    style:left={`${left}px`}
    style:top={`${top}px`}
    style:width={`${width}px`}
    style:height={`${height}px`}
    role="button"
    tabindex="0"
    aria-label="Footer halaman {placement.page}"
    aria-pressed={selected}
    data-invalid={invalid ? 'true' : undefined}
    onpointerdown={(event) => startInteraction(event, 'move')}
    onpointermove={handlePointerMove}
    onpointerup={finishMove}
    onpointercancel={finishMove}
    onkeydown={handleKeydown}
>
    <svg
        class="esign-footer-placement__text"
        viewBox="0 0 {placement.width} {placement.height}"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        {#each textLines as line, index (`${index}-${line}`)}
            {@const centerX = lineCenterX()}
            {@const baselineY = lineBaselineY(index)}
            {@const lineWidth = Math.min(
                placement.width,
                estimatedFooterTextWidth(line, footer.font_size_pt, footer.font_key, footer.is_bold),
            )}
            <text
                x={centerX}
                y={baselineY}
                fill="currentColor"
                font-family={footerCssFontFamily(footer.font_key)}
                font-size={footer.font_size_pt}
                font-style={footer.is_italic ? 'italic' : 'normal'}
                font-weight={footer.is_bold ? '700' : '400'}
                text-anchor="middle"
            >{line}</text>
            {#if footer.is_underline}
                <line
                    x1={centerX - (lineWidth / 2)}
                    y1={underlineY(index)}
                    x2={centerX + (lineWidth / 2)}
                    y2={underlineY(index)}
                    stroke="currentColor"
                    stroke-width={Math.max(0.4, footer.font_size_pt / 16)}
                ></line>
            {/if}
        {/each}
    </svg>
    {#if selected}
        <button
            type="button"
            class="esign-footer-placement__delete"
            aria-label="Hapus footer halaman {placement.page}"
            title="Hapus footer halaman ini"
            onpointerdown={(event) => {
                event.preventDefault();
                event.stopPropagation();
            }}
            onclick={deletePlacement}
        >
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
        {#each ['nw', 'ne', 'sw', 'se'] as corner (corner)}
            <span
                class="esign-footer-placement__resize esign-footer-placement__resize--{corner}"
                role="presentation"
                aria-hidden="true"
                onpointerdown={(event) => startInteraction(event, 'resize', corner as ResizeCorner)}
            ></span>
        {/each}
    {/if}
</div>
