<script lang="ts">
    import {
        keyboardDelta,
        moveRectangleWithinSafeArea,
    } from '../geometry/constraints';
    import {
        resizeSquareSignaturePlacement,
    } from '../geometry/placement';
    import { serializeSignaturePlacement } from '../geometry/serialization';
    import { domPointToCanonicalPoint } from '../geometry/transform';
    import type { DomRectangle } from '../geometry/types';
    import type {
        PdfPageGeometry,
        SignaturePlacement,
        VisibleSigningEditorConfiguration,
    } from '../types';

    type InteractionKind = 'move' | 'resize';

    interface PointerInteraction {
        kind: InteractionKind;
        pointerId: number;
        startPoint: { x: number; y: number };
        startPlacement: SignaturePlacement;
        pageBounds: DomRectangle;
    }

    let {
        placement,
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
        placement: SignaturePlacement;
        page: PdfPageGeometry;
        editor: VisibleSigningEditorConfiguration;
        pageWidthPx: number;
        pageHeightPx: number;
        selected: boolean;
        invalid: boolean;
        onSelect: (clientId: string) => void;
        onChange: (placement: SignaturePlacement) => void;
        onDelete: (clientId: string) => void;
        onDeselect: () => void;
    } = $props();

    let overlayElement: HTMLDivElement;
    let interaction: PointerInteraction | null = null;
    let wasSelected = false;

    let left = $derived((placement.origin_x / page.width) * pageWidthPx);
    let top = $derived((placement.origin_y / page.height) * pageHeightPx);
    let width = $derived((placement.width / page.width) * pageWidthPx);
    let height = $derived((placement.height / page.height) * pageHeightPx);

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

    function startInteraction(event: PointerEvent, kind: InteractionKind): void {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        onSelect(placement.client_id);

        const target = event.currentTarget as HTMLElement;
        target.setPointerCapture(event.pointerId);
        const pageBounds = currentPageBounds();

        interaction = {
            kind,
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

        if (current.kind === 'move') {
            const rectangle = moveRectangleWithinSafeArea(
                current.startPlacement,
                point.x - current.startPoint.x,
                point.y - current.startPoint.y,
                page,
                editor.safe_margin_pt,
            );

            onChange(serializeSignaturePlacement(
                page,
                rectangle,
                placement.client_id,
                placement.operation_index,
            ));

            return;
        }

        const deltaX = point.x - current.startPoint.x;
        const deltaY = point.y - current.startPoint.y;
        const requestedSize = current.startPlacement.width + Math.max(deltaX, deltaY);

        onChange(resizeSquareSignaturePlacement(
            current.startPlacement,
            page,
            editor,
            requestedSize,
        ));
    }

    function finishInteraction(event: PointerEvent): void {
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

            onChange(serializeSignaturePlacement(
                page,
                rectangle,
                placement.client_id,
                placement.operation_index,
            ));

            return;
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            onDelete(placement.client_id);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            onDeselect();
        }
    }

    function deletePlacement(event: MouseEvent): void {
        event.preventDefault();
        event.stopPropagation();
        onDelete(placement.client_id);
    }
</script>

<div
    bind:this={overlayElement}
    class:esign-signature-placement--selected={selected}
    class:esign-signature-placement--invalid={invalid}
    class="esign-signature-placement"
    style:left={`${left}px`}
    style:top={`${top}px`}
    style:width={`${width}px`}
    style:height={`${height}px`}
    style:font-size={`${Math.min(width, height)}px`}
    role="button"
    tabindex="0"
    aria-label="QR {placement.operation_index + 1}, halaman {placement.page}"
    aria-pressed={selected}
    data-invalid={invalid ? 'true' : undefined}
    onpointerdown={(event) => startInteraction(event, 'move')}
    onpointermove={handlePointerMove}
    onpointerup={finishInteraction}
    onpointercancel={finishInteraction}
    onkeydown={handleKeydown}
>
    <span class="esign-signature-placement__index" aria-hidden="true">
        {placement.operation_index + 1}
    </span>
    <i class="fas fa-qrcode esign-signature-placement__icon" aria-hidden="true"></i>
    <span class="esign-signature-placement__label">QR {placement.operation_index + 1}</span>
    {#if selected}
        <button
            type="button"
            class="esign-signature-placement__delete"
            aria-label="Hapus QR {placement.operation_index + 1}"
            title="Hapus QR"
            onpointerdown={(event) => {
                event.preventDefault();
                event.stopPropagation();
            }}
            onclick={deletePlacement}
        >
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
        <span
            class="esign-signature-placement__resize"
            role="presentation"
            onpointerdown={(event) => startInteraction(event, 'resize')}
            onpointermove={handlePointerMove}
            onpointerup={finishInteraction}
            onpointercancel={finishInteraction}
        ></span>
    {/if}
</div>
