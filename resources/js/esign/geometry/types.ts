import type { PdfPageGeometry, PdfRotation } from '../types';

export interface CanonicalPoint {
    x: number;
    y: number;
}

export interface CanonicalRectangle {
    origin_x: number;
    origin_y: number;
    width: number;
    height: number;
}

/** Bounding box in browser client pixels. */
export interface DomRectangle {
    left: number;
    top: number;
    width: number;
    height: number;
}

export interface RenderedPdfPageMetrics {
    page: number;
    page_width_pt: number;
    page_height_pt: number;
    page_rotation: PdfRotation;
    viewport_width_px: number;
    viewport_height_px: number;
    scale_x: number;
    scale_y: number;
}

export interface SafeArea {
    origin_x: number;
    origin_y: number;
    width: number;
    height: number;
}

export interface GeometrySubject {
    id: string;
    kind: 'signature' | 'footer';
    geometry: CanonicalRectangle & {
        page: number;
        page_width: number;
        page_height: number;
        page_rotation: PdfRotation;
    };
}

export type GeometryPage = Pick<PdfPageGeometry, 'page' | 'width' | 'height' | 'rotation'>;
