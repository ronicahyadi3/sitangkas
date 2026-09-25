import type {
    FooterPlacement,
    PdfPageGeometry,
    SignaturePlacement,
    Uuid,
    VisibleSigningEditorConfiguration,
} from '../types';
import {
    constrainRectangleToSafeArea,
    rectanglesOverlapWithGap,
    roundCanonical,
    safeAreaForPage,
} from './constraints';
import { serializeSignaturePlacement } from './serialization';
import type { CanonicalPoint, CanonicalRectangle } from './types';

const PREFERRED_QR_SIZE_PT = 96;

function uniqueValues(values: number[]): number[] {
    return [...new Set(values.map(roundCanonical))];
}

function axisCandidates(minimum: number, maximum: number, center: number, step: number): number[] {
    const values = [center, minimum, maximum];

    for (let value = minimum; value <= maximum; value += step) {
        values.push(value);
    }

    values.push(maximum);

    return uniqueValues(values.map((value) => Math.min(maximum, Math.max(minimum, value))));
}

export function preferredQrSize(
    page: PdfPageGeometry,
    editor: VisibleSigningEditorConfiguration,
): number | null {
    const area = safeAreaForPage(page, editor.safe_margin_pt);
    const maximum = Math.min(editor.qr.maximum_size_pt, area.width, area.height);

    if (maximum < editor.qr.minimum_size_pt) {
        return null;
    }

    return roundCanonical(Math.min(maximum, Math.max(editor.qr.minimum_size_pt, PREFERRED_QR_SIZE_PT)));
}

export function findAvailableSignatureRectangle(
    page: PdfPageGeometry,
    placements: SignaturePlacement[],
    editor: VisibleSigningEditorConfiguration,
    requestedSize?: number,
    footerPlacements: FooterPlacement[] = [],
    preferredCenter?: CanonicalPoint,
): CanonicalRectangle | null {
    const area = safeAreaForPage(page, editor.safe_margin_pt);
    const preferredSize = preferredQrSize(page, editor);

    if (preferredSize === null) {
        return null;
    }

    const maximumSize = Math.min(editor.qr.maximum_size_pt, area.width, area.height);
    const size = roundCanonical(Math.min(
        maximumSize,
        Math.max(editor.qr.minimum_size_pt, requestedSize ?? preferredSize),
    ));
    const maximumX = area.origin_x + area.width - size;
    const maximumY = area.origin_y + area.height - size;
    const centerX = Math.min(maximumX, Math.max(
        area.origin_x,
        (preferredCenter?.x ?? (area.origin_x + (area.width / 2))) - (size / 2),
    ));
    const centerY = Math.min(maximumY, Math.max(
        area.origin_y,
        (preferredCenter?.y ?? (area.origin_y + (area.height / 2))) - (size / 2),
    ));
    const step = size + editor.minimum_gap_pt;
    const xCandidates = axisCandidates(area.origin_x, maximumX, centerX, step);
    const yCandidates = axisCandidates(area.origin_y, maximumY, centerY, step);
    const candidates = xCandidates.flatMap((originX) => (
        yCandidates.map((originY) => ({
            origin_x: originX,
            origin_y: originY,
            width: size,
            height: size,
        }))
    ));

    candidates.sort((left, right) => {
        const leftDistance = ((left.origin_x - centerX) ** 2) + ((left.origin_y - centerY) ** 2);
        const rightDistance = ((right.origin_x - centerX) ** 2) + ((right.origin_y - centerY) ** 2);

        return leftDistance - rightDistance;
    });

    const obstacles = [
        ...placements.filter((placement) => placement.page === page.page),
        ...footerPlacements.filter((placement) => placement.page === page.page),
    ];

    return candidates.find((candidate) => obstacles.every((placement) => (
        !rectanglesOverlapWithGap(candidate, placement, editor.minimum_gap_pt)
    ))) ?? null;
}

export function normalizeSignatureOperationIndexes(
    placements: SignaturePlacement[],
): SignaturePlacement[] {
    return placements.map((placement, operationIndex) => ({
        ...placement,
        operation_index: operationIndex,
    }));
}

export function createDefaultSignaturePlacement(
    page: PdfPageGeometry,
    placements: SignaturePlacement[],
    editor: VisibleSigningEditorConfiguration,
    clientId: Uuid,
    footerPlacements: FooterPlacement[] = [],
    preferredCenter?: CanonicalPoint,
): SignaturePlacement | null {
    const rectangle = findAvailableSignatureRectangle(
        page,
        placements,
        editor,
        undefined,
        footerPlacements,
        preferredCenter,
    );

    return rectangle === null
        ? null
        : serializeSignaturePlacement(page, rectangle, clientId, placements.length);
}

export function resizeSquareSignaturePlacement(
    placement: SignaturePlacement,
    page: PdfPageGeometry,
    editor: VisibleSigningEditorConfiguration,
    requestedSize: number,
): SignaturePlacement {
    const area = safeAreaForPage(page, editor.safe_margin_pt);
    const maximumAtOrigin = Math.min(
        editor.qr.maximum_size_pt,
        area.origin_x + area.width - placement.origin_x,
        area.origin_y + area.height - placement.origin_y,
    );
    const size = Math.min(maximumAtOrigin, Math.max(editor.qr.minimum_size_pt, requestedSize));
    const constrained = constrainRectangleToSafeArea({
        origin_x: placement.origin_x,
        origin_y: placement.origin_y,
        width: size,
        height: size,
    }, page, editor.safe_margin_pt);

    return serializeSignaturePlacement(page, constrained, placement.client_id, placement.operation_index);
}

export function resetSignaturePlacements(
    placements: SignaturePlacement[],
    pages: PdfPageGeometry[],
    editor: VisibleSigningEditorConfiguration,
    footerPlacements: FooterPlacement[] = [],
): SignaturePlacement[] {
    const reset: SignaturePlacement[] = [];
    const pageMap = new Map(pages.map((page) => [page.page, page]));

    for (const placement of placements) {
        const page = pageMap.get(placement.page);

        if (page === undefined) {
            continue;
        }

        const rectangle = findAvailableSignatureRectangle(
            page,
            reset,
            editor,
            placement.width,
            footerPlacements,
        );

        if (rectangle === null) {
            reset.push(placement);
            continue;
        }

        reset.push(serializeSignaturePlacement(
            page,
            rectangle,
            placement.client_id,
            reset.length,
        ));
    }

    return normalizeSignatureOperationIndexes(reset);
}
