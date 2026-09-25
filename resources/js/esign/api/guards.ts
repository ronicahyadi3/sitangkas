import type {
    AcceptedEsignAttempt,
    ApiEnvelope,
    EsignAttemptDetails,
    FooterEditorConfiguration,
    FooterPlan,
    PdfPageGeometry,
    PreparedFooter,
    PreparedSignatureOperation,
    PreparedSigningRendition,
    SignaturePlacement,
    SigningSession,
    VisibleSigningEditorConfiguration,
} from '../types';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const SHA256_PATTERN = /^[0-9a-f]{64}$/i;
const ATTEMPT_STATUSES = new Set([
    'prepared',
    'signing',
    'partially_signed',
    'validating',
    'succeeded',
    'failed',
    'unknown',
]);
const OPERATION_STATUSES = new Set([
    'pending',
    'signing',
    'output_received',
    'completed',
    'failed',
    'unknown',
]);

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isNonEmptyString(value: unknown): value is string {
    return typeof value === 'string' && value.trim() !== '';
}

function isNullableString(value: unknown): value is string | null {
    return value === null || typeof value === 'string';
}

function isFiniteNumber(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value);
}

function isNonNegativeInteger(value: unknown): value is number {
    return Number.isInteger(value) && Number(value) >= 0;
}

function isPositiveInteger(value: unknown): value is number {
    return Number.isInteger(value) && Number(value) > 0;
}

function isUuid(value: unknown): value is string {
    return typeof value === 'string' && UUID_PATTERN.test(value);
}

function isSha256(value: unknown): value is string {
    return typeof value === 'string' && SHA256_PATTERN.test(value);
}

function isIso8601(value: unknown): value is string {
    return typeof value === 'string' && !Number.isNaN(Date.parse(value));
}

function isUrl(value: unknown): value is string {
    if (!isNonEmptyString(value)) {
        return false;
    }

    try {
        new URL(value, window.location.origin);

        return true;
    } catch {
        return false;
    }
}

function isRotation(value: unknown): value is 0 | 90 | 180 | 270 {
    return value === 0 || value === 90 || value === 180 || value === 270;
}

function isPdfPageGeometry(value: unknown): value is PdfPageGeometry {
    if (!isRecord(value)) {
        return false;
    }

    return isPositiveInteger(value.page)
        && isFiniteNumber(value.width)
        && value.width > 0
        && isFiniteNumber(value.height)
        && value.height > 0
        && isRotation(value.rotation);
}

function isPlacement(value: unknown): value is SignaturePlacement {
    if (!isRecord(value)) {
        return false;
    }

    return isUuid(value.client_id)
        && isNonNegativeInteger(value.operation_index)
        && isPositiveInteger(value.page)
        && isFiniteNumber(value.page_width)
        && value.page_width > 0
        && isFiniteNumber(value.page_height)
        && value.page_height > 0
        && isRotation(value.page_rotation)
        && isFiniteNumber(value.origin_x)
        && isFiniteNumber(value.origin_y)
        && isFiniteNumber(value.width)
        && value.width > 0
        && isFiniteNumber(value.height)
        && value.height > 0;
}

function isFooterPlan(value: unknown): value is FooterPlan {
    if (!isRecord(value)) {
        return false;
    }

    return typeof value.text === 'string'
        && isNonEmptyString(value.font_key)
        && isFiniteNumber(value.font_size_pt)
        && typeof value.is_bold === 'boolean'
        && typeof value.is_italic === 'boolean'
        && typeof value.is_underline === 'boolean'
        && Array.isArray(value.placements)
        && value.placements.every((placement) => {
            if (!isRecord(placement)) {
                return false;
            }

            return isPositiveInteger(placement.page)
                && isFiniteNumber(placement.page_width)
                && isFiniteNumber(placement.page_height)
                && isRotation(placement.page_rotation)
                && isFiniteNumber(placement.origin_x)
                && isFiniteNumber(placement.origin_y)
                && isFiniteNumber(placement.width)
                && isFiniteNumber(placement.height);
        });
}

function isPreparedFooter(value: unknown): value is PreparedFooter {
    return isFooterPlan(value)
        && isRecord(value)
        && isSha256(value.configuration_sha256);
}

function isFooterEditorConfiguration(value: unknown): value is FooterEditorConfiguration {
    if (!isRecord(value) || !isRecord(value.allowed_fonts)) {
        return false;
    }

    return typeof value.allowed === 'boolean'
        && typeof value.required === 'boolean'
        && isNullableString(value.text)
        && isNullableString(value.font_key)
        && (value.font_size_pt === null || isFiniteNumber(value.font_size_pt))
        && value.is_bold === false
        && value.is_italic === false
        && value.is_underline === false
        && isFiniteNumber(value.font_size_min_pt)
        && isFiniteNumber(value.font_size_max_pt)
        && Object.values(value.allowed_fonts).every((font) => typeof font === 'string')
        && Array.isArray(value.placements)
        && value.placements.every((placement) => {
            if (!isRecord(placement)) {
                return false;
            }

            return isPositiveInteger(placement.page)
                && isFiniteNumber(placement.page_width)
                && isFiniteNumber(placement.page_height)
                && isRotation(placement.page_rotation)
                && isFiniteNumber(placement.origin_x)
                && isFiniteNumber(placement.origin_y)
                && isFiniteNumber(placement.width)
                && isFiniteNumber(placement.height);
        });
}

function isEditorConfiguration(value: unknown): value is VisibleSigningEditorConfiguration {
    if (!isRecord(value) || !isRecord(value.qr)) {
        return false;
    }

    return value.coordinate_origin === 'top_left'
        && value.measurement_unit === 'pt'
        && isPositiveInteger(value.maximum_signature_count)
        && isFiniteNumber(value.qr.minimum_size_pt)
        && isFiniteNumber(value.qr.maximum_size_pt)
        && isFiniteNumber(value.safe_margin_pt)
        && isFiniteNumber(value.minimum_gap_pt)
        && value.rotated_pages_supported === false
        && isFooterEditorConfiguration(value.footer);
}

export function isSigningSession(value: unknown): value is SigningSession {
    if (!isRecord(value)) {
        return false;
    }

    return isUuid(value.session_id)
        && isUrl(value.session_url)
        && isNonEmptyString(value.signer_name)
        && isNonEmptyString(value.masked_nik)
        && typeof value.placement_required === 'boolean'
        && isPositiveInteger(value.artifact_version)
        && isSha256(value.artifact_sha256)
        && (value.signature_state === 'unsigned' || value.signature_state === 'signed')
        && isNonNegativeInteger(value.verified_signature_count)
        && typeof value.footer_applied === 'boolean'
        && Array.isArray(value.pages)
        && value.pages.length > 0
        && value.pages.every(isPdfPageGeometry)
        && isIso8601(value.expires_at)
        && isEditorConfiguration(value.editor)
        && isUrl(value.preview_url)
        && isUrl(value.prepare_rendition_url)
        && isUrl(value.sign_url);
}

export function isSigningSessionWithPreparedRendition(
    value: unknown,
): value is SigningSession & { prepared_rendition: PreparedSigningRendition | null } {
    return isSigningSession(value)
        && isRecord(value)
        && Object.hasOwn(value, 'prepared_rendition')
        && (value.prepared_rendition === null || isPreparedSigningRendition(value.prepared_rendition));
}

function isPreparedOperation(value: unknown): value is PreparedSignatureOperation {
    return isPlacement(value)
        && isRecord(value)
        && isUuid(value.verification_public_id)
        && isUrl(value.verification_url)
        && isSha256(value.qr_sha256)
        && isNonEmptyString(value.qr_profile_version)
        && isUrl(value.qr_image_url);
}

export function isPreparedSigningRendition(value: unknown): value is PreparedSigningRendition {
    if (!isRecord(value)) {
        return false;
    }

    return isUuid(value.revision)
        && isSha256(value.sha256)
        && isSha256(value.request_fingerprint)
        && isNonEmptyString(value.renderer_version)
        && isPositiveInteger(value.signature_count)
        && Array.isArray(value.signature_operations)
        && value.signature_operations.length === value.signature_count
        && value.signature_operations.every(isPreparedOperation)
        && (value.footer === null || isPreparedFooter(value.footer))
        && isIso8601(value.expires_at)
        && isUrl(value.preview_url);
}

export function isAcceptedEsignAttempt(value: unknown): value is AcceptedEsignAttempt {
    if (!isRecord(value)) {
        return false;
    }

    return isUuid(value.attempt_id)
        && typeof value.status === 'string'
        && ATTEMPT_STATUSES.has(value.status)
        && isUrl(value.status_url);
}

export function isEsignAttemptDetails(value: unknown): value is EsignAttemptDetails {
    if (!isRecord(value) || !isRecord(value.progress)) {
        return false;
    }

    const progress = value.progress;

    return isUuid(value.attempt_id)
        && isPositiveInteger(value.attempt_number)
        && typeof value.status === 'string'
        && ATTEMPT_STATUSES.has(value.status)
        && typeof value.retryable === 'boolean'
        && isNonNegativeInteger(progress.planned)
        && isNonNegativeInteger(progress.completed)
        && isNonNegativeInteger(progress.current_index)
        && Array.isArray(progress.operations)
        && progress.operations.every((operation) => isRecord(operation)
            && isNonNegativeInteger(operation.index)
            && typeof operation.status === 'string'
            && OPERATION_STATUSES.has(operation.status)
            && typeof operation.retryable === 'boolean')
        && typeof value.requires_passphrase === 'boolean'
        && (value.resume_url === null || isUrl(value.resume_url))
        && typeof value.requires_reconciliation === 'boolean'
        && isNullableString(value.error_code)
        && (value.result_artifact_id === null || isUuid(value.result_artifact_id))
        && (value.started_at === null || isIso8601(value.started_at))
        && (value.completed_at === null || isIso8601(value.completed_at))
        && (value.next_poll_after_ms === null || isNonNegativeInteger(value.next_poll_after_ms));
}

export function isApiEnvelope<TData>(
    value: unknown,
    isData: (candidate: unknown) => candidate is TData,
): value is ApiEnvelope<TData> {
    return isRecord(value) && isData(value.data);
}
