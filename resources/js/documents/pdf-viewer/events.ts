import type {
    PdfViewerResource,
    SecurePdfViewerClosedDetail,
    SecurePdfViewerOpenDetail,
} from './types';

export const PDF_VIEWER_OPEN_EVENT = 'sitangkas:pdf-viewer:open';
export const PDF_VIEWER_CLOSED_EVENT = 'sitangkas:pdf-viewer:closed';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const RESOURCES = new Set<PdfViewerResource>(['document', 'billing', 'spj_fungsional', 'history']);
const SOURCE_STATES = new Set([
    'canonical',
    'legacy_private_pending',
    'legacy_public_pending',
    'unavailable',
]);
const DELIVERY_MODES = new Set(['original', 'identified_watermarked', 'public_watermarked']);

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function controlledUrl(value: unknown, pathPattern: RegExp): value is string {
    if (typeof value !== 'string' || value.trim() === '') {
        return false;
    }

    try {
        const url = new URL(value, window.location.origin);

        return url.origin === window.location.origin
            && ['http:', 'https:'].includes(url.protocol)
            && pathPattern.test(url.pathname);
    } catch {
        return false;
    }
}

function isReason(value: unknown): boolean {
    return value === null || (isRecord(value)
        && typeof value.code === 'string'
        && typeof value.message === 'string');
}

function deliveryPath(resource: PdfViewerResource, purpose: 'content' | 'download'): RegExp {
    if (resource === 'history') {
        return new RegExp(`/document/history-pdf/[^/]+/${purpose}/?$`);
    }

    return new RegExp(`/document/pdf/[^/]+/${resource}/${purpose}/?$`);
}

function isViewAction(value: unknown, resource: PdfViewerResource): boolean {
    if (!isRecord(value) || typeof value.allowed !== 'boolean' || !isReason(value.reason)) {
        return false;
    }

    return value.allowed === true
        && controlledUrl(value.url, deliveryPath(resource, 'content'));
}

function isDownloadAction(value: unknown, resource: PdfViewerResource): boolean {
    if (!isRecord(value) || typeof value.allowed !== 'boolean' || !isReason(value.reason)) {
        return false;
    }

    return value.allowed === true
        ? controlledUrl(value.url, deliveryPath(resource, 'download'))
        : value.url === null;
}

function isVerifyAction(value: unknown): boolean {
    if (!isRecord(value) || typeof value.allowed !== 'boolean' || !isReason(value.reason)) {
        return false;
    }

    if (value.allowed === false) {
        return value.artifact_public_id === null
            && value.verification_url === null
            && value.preview_url === null;
    }

    if (typeof value.artifact_public_id !== 'string'
        || !UUID_PATTERN.test(value.artifact_public_id)
        || !controlledUrl(
            value.verification_url,
            /\/esign\/internal\/artifacts\/[0-9a-f-]+\/verification\/?$/i,
        )
        || !controlledUrl(
            value.preview_url,
            /\/esign\/internal\/artifacts\/[0-9a-f-]+\/verification\/preview\/?$/i,
        )) {
        return false;
    }

    const verificationUrl = new URL(value.verification_url, window.location.origin);
    const previewUrl = new URL(value.preview_url, window.location.origin);
    const encodedArtifactId = `/${value.artifact_public_id.toLowerCase()}/verification`;

    return verificationUrl.pathname.toLowerCase().endsWith(encodedArtifactId)
        && previewUrl.pathname.toLowerCase().endsWith(`${encodedArtifactId}/preview`);
}

export function isSecurePdfViewerOpenDetail(value: unknown): value is SecurePdfViewerOpenDetail {
    if (!isRecord(value)
        || !isRecord(value.actions)
        || typeof value.document_id !== 'string'
        || value.document_id.trim() === ''
        || typeof value.resource !== 'string'
        || !RESOURCES.has(value.resource as PdfViewerResource)
        || typeof value.title !== 'string'
        || value.title.trim() === ''
        || (value.document_type !== null && typeof value.document_type !== 'string')
        || (value.payment_type !== null && typeof value.payment_type !== 'string')
        || typeof value.source_state !== 'string'
        || !SOURCE_STATES.has(value.source_state)
        || typeof value.delivery_mode !== 'string'
        || !DELIVERY_MODES.has(value.delivery_mode)) {
        return false;
    }

    const resource = value.resource as PdfViewerResource;

    return isViewAction(value.actions.view, resource)
        && isDownloadAction(value.actions.download, resource)
        && isVerifyAction(value.actions.verify);
}

export function dispatchSecurePdfViewerOpen(detail: SecurePdfViewerOpenDetail): boolean {
    if (!isSecurePdfViewerOpenDetail(detail)) {
        return false;
    }

    return window.dispatchEvent(new CustomEvent(PDF_VIEWER_OPEN_EVENT, {
        detail: Object.freeze({ ...detail, actions: Object.freeze({ ...detail.actions }) }),
    }));
}

export function isSecurePdfViewerClosedDetail(value: unknown): value is SecurePdfViewerClosedDetail {
    return isRecord(value)
        && typeof value.document_id === 'string'
        && value.document_id.trim() !== ''
        && typeof value.resource === 'string'
        && RESOURCES.has(value.resource as PdfViewerResource);
}

export function dispatchSecurePdfViewerClosed(detail: SecurePdfViewerClosedDetail): boolean {
    if (!isSecurePdfViewerClosedDetail(detail)) {
        return false;
    }

    return window.dispatchEvent(new CustomEvent(PDF_VIEWER_CLOSED_EVENT, {
        detail: Object.freeze({ ...detail }),
    }));
}
