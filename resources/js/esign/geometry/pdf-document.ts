import type { PDFDocumentProxy } from 'pdfjs-dist';
import type { PdfPageGeometry } from '../types';
import { normalizedRotation } from './transform';
import { PAGE_GEOMETRY_TOLERANCE_PT } from './validation';

export interface PdfDocumentGeometryIssue {
    code: 'pdf_page_contract_invalid' | 'pdf_page_geometry_mismatch';
    message: string;
    page?: number;
}

export async function verifyPdfDocumentGeometry(
    document: PDFDocumentProxy,
    expectedPages: PdfPageGeometry[],
    signal?: AbortSignal,
): Promise<PdfDocumentGeometryIssue | null> {
    if (document.numPages !== expectedPages.length) {
        return {
            code: 'pdf_page_contract_invalid',
            message: 'Jumlah halaman PDF tidak sesuai dengan signing session.',
        };
    }

    for (let index = 0; index < expectedPages.length; index += 1) {
        signal?.throwIfAborted();

        const expected = expectedPages[index];
        const pageNumber = index + 1;

        if (expected === undefined || expected.page !== pageNumber) {
            return {
                code: 'pdf_page_contract_invalid',
                message: 'Urutan metadata halaman signing session tidak valid.',
                page: pageNumber,
            };
        }

        const pdfPage = await document.getPage(pageNumber);

        try {
            const unrotatedViewport = pdfPage.getViewport({ scale: 1, rotation: 0 });
            const rotation = normalizedRotation(pdfPage.rotate);
            const mismatched = rotation === null
                || Math.abs(unrotatedViewport.width - expected.width) > PAGE_GEOMETRY_TOLERANCE_PT
                || Math.abs(unrotatedViewport.height - expected.height) > PAGE_GEOMETRY_TOLERANCE_PT
                || rotation !== expected.rotation;

            if (mismatched) {
                return {
                    code: 'pdf_page_geometry_mismatch',
                    message: `Ukuran atau rotasi halaman ${pageNumber} tidak sesuai dengan signing session.`,
                    page: pageNumber,
                };
            }
        } finally {
            pdfPage.cleanup();
        }
    }

    return null;
}
