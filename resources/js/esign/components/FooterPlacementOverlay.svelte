<script lang="ts">
    import {
        keyboardDelta,
        moveRectangleWithinSafeArea,
    } from '../geometry/constraints';
    import { footerCssFontFamily } from '../geometry/footer';
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
        pointerId: number;
        startPoint: { x: number; y: number };
        startPlacement: FooterPlacement;
        pageBounds: DomRectangle;
    }

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
        onDeselect: () => void;
    } = $props();

    let overlayElement: HTMLDivElement;
    let interaction: PointerInteraction | null = null;
    let wasSelected = false;

    let left = $derived((placement.origin_x / page.width) * pageWidthPx);
    let top = $derived((placement.origin_y / page.height) * pageHeightPx);
    let width = $derived((placement.width / page.width) * pageWidthPx);
    let height = $derived((placement.height / page.height) * pageHeightPx);
    let fontSize = $derived((footer.font_size_pt / page.height) * pageHeightPx);
    let textDecoration = $derived(footer.is_underline ? 'underline' : 'none');

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

    function startMove(event: PointerEvent): void {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        onSelect(placement.page);

        const target = event.currentTarget as HTMLElement;
        target.setPointerCapture(event.pointerId);
        const pageBounds = currentPageBounds();

        interaction = {
            pointerId: event.pointerId,
            startPoint: domPointToCanonicalPoint({ x: event.clientX, y: event.clientY }, page, pageBounds),
            startPlacement: { ...placement },
            pageBounds,
        };
    }

    function handlePointerMove(event: PointerEvent): void {
        const current = interaction;

        if (current === null || current.pointerId !== event.pointerId) {
            return;
        }

        event.preventDefault();
        const point = domPointToCanonicalPoint({ x: event.clientX, y: event.clientY }, page, current.pageBounds);
        const rectangle = moveRectangleWithinSafeArea(
            current.startPlacement,
            point.x - current.startPoint.x,
            point.y - current.startPoint.y,
            page,
            editor.safe_margin_pt,
        );

        onChange(serializeFooterPlacement(page, rectangle));
    }

    function finishMove(event: PointerEvent): void {
        if (interaction?.pointerId !== event.pointerId) {
            return;
        }

        const target = event.currentTarget as HTMLElement;

        if (target.hasPointerCapture(event.pointerId)) {
            target.releasePointerCapture(event.pointerId);
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
    style:font-family={footerCssFontFamily(footer.font_key)}
    style:font-size={`${fontSize}px`}
    style:font-style={footer.is_italic ? 'italic' : 'normal'}
    style:font-weight={footer.is_bold ? '700' : '400'}
    style:text-decoration={textDecoration}
    role="button"
    tabindex="0"
    aria-label="Footer halaman {placement.page}"
    aria-pressed={selected}
    data-invalid={invalid ? 'true' : undefined}
    onpointerdown={startMove}
    onpointermove={handlePointerMove}
    onpointerup={finishMove}
    onpointercancel={finishMove}
    onkeydown={handleKeydown}
>
    <span>{footer.text}</span>
</div>
