import type {
    FooterEditorConfiguration,
    FooterPlacement,
    FooterPlan,
    PdfPageGeometry,
    VisibleSigningEditorConfiguration,
} from '../types';
import {
    constrainRectangleToSafeArea,
    roundCanonical,
    safeAreaForPage,
} from './constraints';
import { serializeFooterPlacement } from './serialization';

export interface FooterValidationIssue {
    code: 'footer_missing' | 'footer_style_invalid' | 'footer_pages_invalid' | 'footer_box_too_small';
    message: string;
    page?: number;
}

function completeConfiguration(configuration: FooterEditorConfiguration): configuration is FooterEditorConfiguration & {
    text: string;
    font_key: string;
    font_size_pt: number;
} {
    return configuration.allowed
        && configuration.text !== null
        && configuration.font_key !== null
        && configuration.font_size_pt !== null;
}

function clonePlacements(placements: FooterPlacement[]): FooterPlacement[] {
    return placements.map((placement) => ({ ...placement }));
}

export function createDefaultFooterPlan(
    configuration: FooterEditorConfiguration,
): FooterPlan | null {
    if (!completeConfiguration(configuration)) {
        return null;
    }

    return {
        text: configuration.text,
        font_key: configuration.font_key,
        font_size_pt: configuration.font_size_pt,
        is_bold: configuration.is_bold,
        is_italic: configuration.is_italic,
        is_underline: configuration.is_underline,
        placements: clonePlacements(configuration.placements),
    };
}

export function resetFooterStyle(
    footer: FooterPlan,
    configuration: FooterEditorConfiguration,
): FooterPlan {
    const defaults = createDefaultFooterPlan(configuration);

    return defaults === null
        ? footer
        : {
            ...footer,
            text: defaults.text,
            font_key: defaults.font_key,
            font_size_pt: defaults.font_size_pt,
            is_bold: defaults.is_bold,
            is_italic: defaults.is_italic,
            is_underline: defaults.is_underline,
        };
}

export function resetFooterPlacements(
    footer: FooterPlan,
    configuration: FooterEditorConfiguration,
): FooterPlan {
    return {
        ...footer,
        placements: clonePlacements(configuration.placements),
    };
}

export function replaceFooterPlacement(
    footer: FooterPlan,
    updated: FooterPlacement,
): FooterPlan {
    return {
        ...footer,
        placements: footer.placements.map((placement) => (
            placement.page === updated.page ? updated : placement
        )),
    };
}

export function applyFooterPlacementToAllPages(
    footer: FooterPlan,
    sourcePlacement: FooterPlacement,
    pages: PdfPageGeometry[],
    editor: VisibleSigningEditorConfiguration,
): FooterPlan {
    const sourcePage = pages.find((page) => page.page === sourcePlacement.page);

    if (sourcePage === undefined) {
        return footer;
    }

    const margin = editor.safe_margin_pt;
    const sourceArea = safeAreaForPage(sourcePage, margin);
    const sourceTravelX = Math.max(0, sourceArea.width - sourcePlacement.width);
    const sourceTravelY = Math.max(0, sourceArea.height - sourcePlacement.height);
    const ratioX = sourceTravelX === 0
        ? 0
        : (sourcePlacement.origin_x - sourceArea.origin_x) / sourceTravelX;
    const ratioY = sourceTravelY === 0
        ? 0
        : (sourcePlacement.origin_y - sourceArea.origin_y) / sourceTravelY;

    const placements = pages.map((page) => {
        const area = safeAreaForPage(page, margin);
        const width = Math.min(sourcePlacement.width, area.width);
        const height = Math.min(sourcePlacement.height, area.height);
        const rectangle = constrainRectangleToSafeArea({
            origin_x: area.origin_x + (Math.max(0, area.width - width) * ratioX),
            origin_y: area.origin_y + (Math.max(0, area.height - height) * ratioY),
            width,
            height,
        }, page, margin);

        return serializeFooterPlacement(page, rectangle);
    });

    return { ...footer, placements };
}

export function footerCssFontFamily(fontKey: string): string {
    switch (fontKey) {
        case 'times':
            return '"Times New Roman", Times, serif';
        case 'courier':
            return '"Courier New", Courier, monospace';
        default:
            return 'Arial, Helvetica, sans-serif';
    }
}

function footerFontWidthFactor(fontKey: string, isBold: boolean): number {
    switch (fontKey) {
        case 'helvetica':
            return isBold ? 0.49 : 0.45;
        case 'times':
            return isBold ? 0.45 : 0.41;
        case 'courier':
            return 0.6;
        default:
            return 0.55;
    }
}

export function estimatedFooterTextWidth(
    text: string,
    fontSize: number,
    fontKey: string,
    isBold: boolean,
): number {
    return Array.from(text).length * fontSize * footerFontWidthFactor(fontKey, isBold);
}

export function wrapFooterText(
    text: string,
    maximumWidth: number,
    fontSize: number,
    fontKey: string,
    isBold: boolean,
): string[] {
    const lines: string[] = [];
    const paragraphs = text.trim().split(/\r\n|[\n\v\f\r\u0085\u2028\u2029]/u);

    for (const paragraph of paragraphs) {
        const words = paragraph.trim().split(/\s+/u).filter(Boolean);
        let line = '';

        for (const word of words) {
            if (estimatedFooterTextWidth(word, fontSize, fontKey, isBold) > maximumWidth) {
                return [];
            }

            const candidate = line === '' ? word : `${line} ${word}`;

            if (line !== '' && estimatedFooterTextWidth(candidate, fontSize, fontKey, isBold) > maximumWidth) {
                lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        }

        if (line !== '') {
            lines.push(line);
        }
    }

    return lines;
}

export function validateFooterPlan(
    footer: FooterPlan | null,
    pages: PdfPageGeometry[],
    editor: VisibleSigningEditorConfiguration,
): FooterValidationIssue[] {
    if (!editor.footer.allowed) {
        return footer === null
            ? []
            : [{ code: 'footer_style_invalid', message: 'Footer tidak diizinkan untuk dokumen ini.' }];
    }

    if (footer === null) {
        return editor.footer.required
            ? [{ code: 'footer_missing', message: 'Footer wajib tersedia pada seluruh halaman.' }]
            : [];
    }

    const issues: FooterValidationIssue[] = [];
    const text = footer.text.trim();
    const allowedFont = Object.hasOwn(editor.footer.allowed_fonts, footer.font_key);

    if (text === ''
        || text.length > 1000
        || !allowedFont
        || footer.font_size_pt < editor.footer.font_size_min_pt
        || footer.font_size_pt > editor.footer.font_size_max_pt) {
        issues.push({
            code: 'footer_style_invalid',
            message: 'Teks, font, atau ukuran footer belum sesuai konfigurasi backend.',
        });
    }

    const pageNumbers = footer.placements.map((placement) => placement.page);
    const availablePageNumbers = new Set(pages.map((page) => page.page));

    if (new Set(pageNumbers).size !== pageNumbers.length
        || pageNumbers.some((page) => !availablePageNumbers.has(page))) {
        issues.push({
            code: 'footer_pages_invalid',
            message: 'Setiap footer harus terhubung ke halaman PDF yang valid dan tidak boleh duplikat.',
        });
    }

    for (const placement of footer.placements) {
        const lineCount = wrapFooterText(
            text,
            placement.width,
            footer.font_size_pt,
            footer.font_key,
            footer.is_bold,
        ).length;
        const minimumHeight = lineCount * footer.font_size_pt * 1.35;

        if (lineCount < 1
            || placement.width < footer.font_size_pt * 4
            || minimumHeight > placement.height + 0.01) {
            issues.push({
                code: 'footer_box_too_small',
                message: `Area footer halaman ${placement.page} terlalu kecil untuk teks saat ini.`,
                page: placement.page,
            });
        }
    }

    return issues;
}

export function normalizeFooterFontSize(value: number): number {
    return roundCanonical(value);
}
