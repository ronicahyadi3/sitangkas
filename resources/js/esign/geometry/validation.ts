import type {
    FooterPlacement,
    KnownSigningPlanValidationCode,
    PdfPageGeometry,
    SignaturePlacement,
    VisibleSigningEditorConfiguration,
} from '../types';
import { rectanglesOverlapWithGap } from './constraints';
import type { GeometrySubject } from './types';

export const PAGE_GEOMETRY_TOLERANCE_PT = 0.05;

export interface GeometryValidationIssue {
    code: KnownSigningPlanValidationCode;
    message: string;
    field: 'placements' | 'footer.placements';
    page?: number;
    subject_id?: string;
    conflicting_subject_id?: string;
}

export interface GeometryValidationResult {
    valid: boolean;
    issues: GeometryValidationIssue[];
}

function geometryMatchesPage(
    geometry: GeometrySubject['geometry'],
    page: PdfPageGeometry,
): boolean {
    return Math.abs(geometry.page_width - page.width) <= PAGE_GEOMETRY_TOLERANCE_PT
        && Math.abs(geometry.page_height - page.height) <= PAGE_GEOMETRY_TOLERANCE_PT
        && geometry.page_rotation === page.rotation;
}

function finiteGeometry(geometry: GeometrySubject['geometry']): boolean {
    return [
        geometry.page_width,
        geometry.page_height,
        geometry.origin_x,
        geometry.origin_y,
        geometry.width,
        geometry.height,
    ].every(Number.isFinite);
}

function validateSubject(
    subject: GeometrySubject,
    pages: Map<number, PdfPageGeometry>,
    editor: VisibleSigningEditorConfiguration,
): GeometryValidationIssue[] {
    const field = subject.kind === 'signature' ? 'placements' : 'footer.placements';
    const page = pages.get(subject.geometry.page);

    if (page === undefined) {
        return [{
            code: subject.kind === 'signature'
                ? 'esign.signature_page_invalid'
                : 'esign.footer_page_invalid',
            message: 'Halaman penempatan tidak tersedia pada dokumen.',
            field,
            page: subject.geometry.page,
            subject_id: subject.id,
        }];
    }

    if (!finiteGeometry(subject.geometry)) {
        return [{
            code: 'esign.placement_number_invalid',
            message: 'Koordinat penempatan harus berupa angka finite.',
            field,
            page: page.page,
            subject_id: subject.id,
        }];
    }

    if (!geometryMatchesPage(subject.geometry, page)) {
        return [{
            code: 'esign.page_geometry_mismatch',
            message: 'Ukuran atau rotasi halaman tidak lagi sesuai dengan signing session.',
            field,
            page: page.page,
            subject_id: subject.id,
        }];
    }

    const geometry = subject.geometry;
    const margin = editor.safe_margin_pt;
    const outsideSafeArea = geometry.origin_x < margin
        || geometry.origin_y < margin
        || geometry.width <= 0
        || geometry.height <= 0
        || geometry.origin_x + geometry.width > page.width - margin
        || geometry.origin_y + geometry.height > page.height - margin;

    if (outsideSafeArea) {
        return [{
            code: 'esign.placement_outside_safe_area',
            message: 'Penempatan melewati batas aman halaman.',
            field,
            page: page.page,
            subject_id: subject.id,
        }];
    }

    if (subject.kind === 'signature') {
        const minimum = editor.qr.minimum_size_pt;
        const maximum = editor.qr.maximum_size_pt;

        if (geometry.width < minimum
            || geometry.height < minimum
            || geometry.width > maximum
            || geometry.height > maximum) {
            return [{
                code: 'esign.signature_size_invalid',
                message: `Ukuran QR harus berada antara ${minimum} dan ${maximum} pt.`,
                field,
                page: page.page,
                subject_id: subject.id,
            }];
        }
    }

    return [];
}

function signatureSubjects(placements: SignaturePlacement[]): GeometrySubject[] {
    return placements.map((placement) => ({
        id: placement.client_id,
        kind: 'signature',
        geometry: placement,
    }));
}

function footerSubjects(placements: FooterPlacement[]): GeometrySubject[] {
    return placements.map((placement) => ({
        id: `footer:${placement.page}`,
        kind: 'footer',
        geometry: placement,
    }));
}

export function validateGeometryPlan(
    placements: SignaturePlacement[],
    footerPlacements: FooterPlacement[],
    pages: PdfPageGeometry[],
    editor: VisibleSigningEditorConfiguration,
): GeometryValidationResult {
    const issues: GeometryValidationIssue[] = [];
    const pageMap = new Map(pages.map((page) => [page.page, page]));

    if (!editor.rotated_pages_supported) {
        for (const page of pages) {
            if (page.rotation !== 0) {
                issues.push({
                    code: 'esign.rotated_pdf_not_supported',
                    message: `Halaman ${page.page} memiliki rotasi ${page.rotation}° dan belum didukung editor.`,
                    field: 'placements',
                    page: page.page,
                });
            }
        }
    }

    if (placements.length < 1 || placements.length > editor.maximum_signature_count) {
        issues.push({
            code: 'esign.signature_count_invalid',
            message: `Jumlah QR harus 1 sampai ${editor.maximum_signature_count}.`,
            field: 'placements',
        });
    }

    const orderedIndexes = placements
        .map((placement) => placement.operation_index)
        .sort((left, right) => left - right);

    if (orderedIndexes.some((operationIndex, index) => operationIndex !== index)) {
        issues.push({
            code: 'esign.signature_operation_order_invalid',
            message: 'Urutan operasi QR harus dimulai dari 0 dan berurutan tanpa celah.',
            field: 'placements',
        });
    }

    const subjects = [
        ...signatureSubjects(placements),
        ...footerSubjects(footerPlacements),
    ];

    for (const subject of subjects) {
        issues.push(...validateSubject(subject, pageMap, editor));
    }

    for (let leftIndex = 0; leftIndex < subjects.length; leftIndex += 1) {
        for (let rightIndex = leftIndex + 1; rightIndex < subjects.length; rightIndex += 1) {
            const left = subjects[leftIndex];
            const right = subjects[rightIndex];

            if (left === undefined || right === undefined || left.geometry.page !== right.geometry.page) {
                continue;
            }

            if (rectanglesOverlapWithGap(left.geometry, right.geometry, editor.minimum_gap_pt)) {
                issues.push({
                    code: 'esign.placement_collision',
                    message: `Penempatan pada halaman ${left.geometry.page} berjarak kurang dari ${editor.minimum_gap_pt} pt.`,
                    field: 'placements',
                    page: left.geometry.page,
                    subject_id: left.id,
                    conflicting_subject_id: right.id,
                });
            }
        }
    }

    return {
        valid: issues.length === 0,
        issues,
    };
}
