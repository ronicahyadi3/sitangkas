import type { CanonicalRectangle, GeometryPage, SafeArea } from './types';

export const CANONICAL_PRECISION = 4;
export const DEFAULT_SNAP_STEP_PT = 1;
export const KEYBOARD_MOVE_STEP_PT = 1;
export const KEYBOARD_MOVE_FAST_STEP_PT = 10;

export function roundCanonical(value: number): number {
    const factor = 10 ** CANONICAL_PRECISION;

    return Math.round((value + Number.EPSILON) * factor) / factor;
}

export function snapCanonical(value: number, step = DEFAULT_SNAP_STEP_PT): number {
    if (!Number.isFinite(step) || step <= 0) {
        throw new RangeError('Snap step must be a finite positive number.');
    }

    return roundCanonical(Math.round(value / step) * step);
}

export function safeAreaForPage(page: GeometryPage, margin: number): SafeArea {
    const safeWidth = page.width - (margin * 2);
    const safeHeight = page.height - (margin * 2);

    if (!Number.isFinite(margin) || margin < 0 || safeWidth <= 0 || safeHeight <= 0) {
        throw new RangeError('Safe margin does not leave a usable page area.');
    }

    return {
        origin_x: margin,
        origin_y: margin,
        width: safeWidth,
        height: safeHeight,
    };
}

export function constrainRectangleToSafeArea(
    rectangle: CanonicalRectangle,
    page: GeometryPage,
    margin: number,
): CanonicalRectangle {
    const area = safeAreaForPage(page, margin);
    const width = Math.min(Math.max(0, rectangle.width), area.width);
    const height = Math.min(Math.max(0, rectangle.height), area.height);
    const maximumX = area.origin_x + area.width - width;
    const maximumY = area.origin_y + area.height - height;

    return {
        origin_x: roundCanonical(Math.min(maximumX, Math.max(area.origin_x, rectangle.origin_x))),
        origin_y: roundCanonical(Math.min(maximumY, Math.max(area.origin_y, rectangle.origin_y))),
        width: roundCanonical(width),
        height: roundCanonical(height),
    };
}

export function moveRectangleWithinSafeArea(
    rectangle: CanonicalRectangle,
    deltaX: number,
    deltaY: number,
    page: GeometryPage,
    margin: number,
    snapStep = DEFAULT_SNAP_STEP_PT,
): CanonicalRectangle {
    return constrainRectangleToSafeArea({
        ...rectangle,
        origin_x: snapCanonical(rectangle.origin_x + deltaX, snapStep),
        origin_y: snapCanonical(rectangle.origin_y + deltaY, snapStep),
    }, page, margin);
}

export function resizeRectangleWithinSafeArea(
    rectangle: CanonicalRectangle,
    width: number,
    height: number,
    page: GeometryPage,
    margin: number,
    snapStep = DEFAULT_SNAP_STEP_PT,
): CanonicalRectangle {
    return constrainRectangleToSafeArea({
        ...rectangle,
        width: snapCanonical(width, snapStep),
        height: snapCanonical(height, snapStep),
    }, page, margin);
}

export function keyboardDelta(
    key: string,
    accelerated = false,
): { x: number; y: number } | null {
    const step = accelerated ? KEYBOARD_MOVE_FAST_STEP_PT : KEYBOARD_MOVE_STEP_PT;

    switch (key) {
        case 'ArrowLeft':
            return { x: -step, y: 0 };
        case 'ArrowRight':
            return { x: step, y: 0 };
        case 'ArrowUp':
            return { x: 0, y: -step };
        case 'ArrowDown':
            return { x: 0, y: step };
        default:
            return null;
    }
}

export function rectanglesOverlapWithGap(
    left: CanonicalRectangle,
    right: CanonicalRectangle,
    minimumGap: number,
): boolean {
    return left.origin_x < right.origin_x + right.width + minimumGap
        && left.origin_x + left.width + minimumGap > right.origin_x
        && left.origin_y < right.origin_y + right.height + minimumGap
        && left.origin_y + left.height + minimumGap > right.origin_y;
}
