import type { PdfRotation } from '../types';
import type {
    CanonicalPoint,
    CanonicalRectangle,
    DomRectangle,
    GeometryPage,
} from './types';

interface DisplayPoint {
    x: number;
    y: number;
}

function assertFinitePositive(value: number, name: string): void {
    if (!Number.isFinite(value) || value <= 0) {
        throw new RangeError(`${name} must be a finite positive number.`);
    }
}

function assertFiniteRectangle(rectangle: CanonicalRectangle | DomRectangle): void {
    for (const [name, value] of Object.entries(rectangle)) {
        if (!Number.isFinite(value)) {
            throw new RangeError(`${name} must be finite.`);
        }
    }

    assertFinitePositive(rectangle.width, 'width');
    assertFinitePositive(rectangle.height, 'height');
}

export function displayedPageSize(page: GeometryPage): { width: number; height: number } {
    assertFinitePositive(page.width, 'page.width');
    assertFinitePositive(page.height, 'page.height');

    return page.rotation === 90 || page.rotation === 270
        ? { width: page.height, height: page.width }
        : { width: page.width, height: page.height };
}

function canonicalPointToDisplayPoint(
    point: CanonicalPoint,
    page: GeometryPage,
): DisplayPoint {
    switch (page.rotation) {
        case 0:
            return { x: point.x, y: point.y };
        case 90:
            return { x: page.height - point.y, y: point.x };
        case 180:
            return { x: page.width - point.x, y: page.height - point.y };
        case 270:
            return { x: point.y, y: page.width - point.x };
    }
}

function displayPointToCanonicalPoint(
    point: DisplayPoint,
    page: GeometryPage,
): CanonicalPoint {
    switch (page.rotation) {
        case 0:
            return { x: point.x, y: point.y };
        case 90:
            return { x: point.y, y: page.height - point.x };
        case 180:
            return { x: page.width - point.x, y: page.height - point.y };
        case 270:
            return { x: page.width - point.y, y: point.x };
    }
}

function canonicalRectangleToDisplayRectangle(
    rectangle: CanonicalRectangle,
    page: GeometryPage,
): CanonicalRectangle {
    switch (page.rotation) {
        case 0:
            return { ...rectangle };
        case 90:
            return {
                origin_x: page.height - rectangle.origin_y - rectangle.height,
                origin_y: rectangle.origin_x,
                width: rectangle.height,
                height: rectangle.width,
            };
        case 180:
            return {
                origin_x: page.width - rectangle.origin_x - rectangle.width,
                origin_y: page.height - rectangle.origin_y - rectangle.height,
                width: rectangle.width,
                height: rectangle.height,
            };
        case 270:
            return {
                origin_x: rectangle.origin_y,
                origin_y: page.width - rectangle.origin_x - rectangle.width,
                width: rectangle.height,
                height: rectangle.width,
            };
    }
}

function displayRectangleToCanonicalRectangle(
    rectangle: CanonicalRectangle,
    page: GeometryPage,
): CanonicalRectangle {
    switch (page.rotation) {
        case 0:
            return { ...rectangle };
        case 90:
            return {
                origin_x: rectangle.origin_y,
                origin_y: page.height - rectangle.origin_x - rectangle.width,
                width: rectangle.height,
                height: rectangle.width,
            };
        case 180:
            return {
                origin_x: page.width - rectangle.origin_x - rectangle.width,
                origin_y: page.height - rectangle.origin_y - rectangle.height,
                width: rectangle.width,
                height: rectangle.height,
            };
        case 270:
            return {
                origin_x: page.width - rectangle.origin_y - rectangle.height,
                origin_y: rectangle.origin_x,
                width: rectangle.height,
                height: rectangle.width,
            };
    }
}

function scales(page: GeometryPage, pageBounds: DomRectangle): { x: number; y: number } {
    assertFiniteRectangle(pageBounds);
    const displaySize = displayedPageSize(page);

    return {
        x: pageBounds.width / displaySize.width,
        y: pageBounds.height / displaySize.height,
    };
}

export function canonicalPointToDomPoint(
    point: CanonicalPoint,
    page: GeometryPage,
    pageBounds: DomRectangle,
): CanonicalPoint {
    const scale = scales(page, pageBounds);
    const displayPoint = canonicalPointToDisplayPoint(point, page);

    return {
        x: pageBounds.left + (displayPoint.x * scale.x),
        y: pageBounds.top + (displayPoint.y * scale.y),
    };
}

export function domPointToCanonicalPoint(
    point: CanonicalPoint,
    page: GeometryPage,
    pageBounds: DomRectangle,
): CanonicalPoint {
    const scale = scales(page, pageBounds);

    return displayPointToCanonicalPoint({
        x: (point.x - pageBounds.left) / scale.x,
        y: (point.y - pageBounds.top) / scale.y,
    }, page);
}

export function canonicalRectangleToDomRectangle(
    rectangle: CanonicalRectangle,
    page: GeometryPage,
    pageBounds: DomRectangle,
): DomRectangle {
    assertFiniteRectangle(rectangle);
    const scale = scales(page, pageBounds);
    const displayRectangle = canonicalRectangleToDisplayRectangle(rectangle, page);

    return {
        left: pageBounds.left + (displayRectangle.origin_x * scale.x),
        top: pageBounds.top + (displayRectangle.origin_y * scale.y),
        width: displayRectangle.width * scale.x,
        height: displayRectangle.height * scale.y,
    };
}

export function domRectangleToCanonicalRectangle(
    rectangle: DomRectangle,
    page: GeometryPage,
    pageBounds: DomRectangle,
): CanonicalRectangle {
    assertFiniteRectangle(rectangle);
    const scale = scales(page, pageBounds);

    return displayRectangleToCanonicalRectangle({
        origin_x: (rectangle.left - pageBounds.left) / scale.x,
        origin_y: (rectangle.top - pageBounds.top) / scale.y,
        width: rectangle.width / scale.x,
        height: rectangle.height / scale.y,
    }, page);
}

export function normalizedRotation(value: number): PdfRotation | null {
    const rotation = ((value % 360) + 360) % 360;

    return rotation === 0 || rotation === 90 || rotation === 180 || rotation === 270
        ? rotation
        : null;
}
