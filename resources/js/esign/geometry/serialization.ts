import type {
    FooterPlan,
    FooterPlacement,
    PdfPageGeometry,
    SignaturePlacement,
    Uuid,
} from '../types';
import { roundCanonical } from './constraints';
import type { CanonicalRectangle } from './types';

function serializeGeometry(
    page: PdfPageGeometry,
    rectangle: CanonicalRectangle,
): FooterPlacement {
    return {
        page: page.page,
        page_width: roundCanonical(page.width),
        page_height: roundCanonical(page.height),
        page_rotation: page.rotation,
        origin_x: roundCanonical(rectangle.origin_x),
        origin_y: roundCanonical(rectangle.origin_y),
        width: roundCanonical(rectangle.width),
        height: roundCanonical(rectangle.height),
    };
}

function serializeExistingGeometry<TPlacement extends SignaturePlacement | FooterPlacement>(
    placement: TPlacement,
): TPlacement {
    return {
        ...placement,
        page_width: roundCanonical(placement.page_width),
        page_height: roundCanonical(placement.page_height),
        origin_x: roundCanonical(placement.origin_x),
        origin_y: roundCanonical(placement.origin_y),
        width: roundCanonical(placement.width),
        height: roundCanonical(placement.height),
    };
}

export function serializeFooterPlacement(
    page: PdfPageGeometry,
    rectangle: CanonicalRectangle,
): FooterPlacement {
    return serializeGeometry(page, rectangle);
}

export function serializeSignaturePlacement(
    page: PdfPageGeometry,
    rectangle: CanonicalRectangle,
    clientId: Uuid,
    operationIndex: number,
): SignaturePlacement {
    return {
        client_id: clientId,
        operation_index: operationIndex,
        ...serializeGeometry(page, rectangle),
    };
}

export function serializeSignaturePlan(placements: SignaturePlacement[]): SignaturePlacement[] {
    return placements
        .map((placement) => serializeExistingGeometry(placement))
        .sort((left, right) => left.operation_index - right.operation_index);
}

export function serializeFooterPlan(footer: FooterPlan | null): FooterPlan | null {
    if (footer === null) {
        return null;
    }

    return {
        ...footer,
        text: footer.text.trim(),
        font_size_pt: roundCanonical(footer.font_size_pt),
        placements: footer.placements
            .map((placement) => serializeExistingGeometry(placement))
            .sort((left, right) => left.page - right.page),
    };
}

export function rectangleFromPlacement(
    placement: SignaturePlacement | FooterPlacement,
): CanonicalRectangle {
    return {
        origin_x: placement.origin_x,
        origin_y: placement.origin_y,
        width: placement.width,
        height: placement.height,
    };
}
