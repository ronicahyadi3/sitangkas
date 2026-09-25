/**
 * Canonical frontend contract for the internal eSign API.
 *
 * Keep these property names aligned with the JSON emitted by Laravel. The
 * frontend must not reconstruct endpoint URLs or accept storage paths from a
 * payment page.
 */

export type Uuid = string;
export type Sha256 = string;
export type Iso8601DateTime = string;

export type PdfRotation = 0 | 90 | 180 | 270;
export type SignatureState = 'unsigned' | 'signed';

export interface ApiEnvelope<TData> {
    data: TData;
}

export interface EsignFrontendConfiguration {
    create_session_url: string;
}

export interface EsignActionCapabilities {
    step_public_id: Uuid;
    can_sign: boolean;
    can_verify: boolean;
}

export type EsignOpenEventDetail = EsignActionCapabilities;

export interface EsignValidationOpenEventDetail {
    artifact_public_id: Uuid;
    can_verify: true;
}

export interface EsignCompletedEventDetail {
    step_public_id: Uuid;
    attempt_id: Uuid;
    result_artifact_id: Uuid;
    result: 'succeeded';
}

export type EsignClosedEventDetail =
    | {
        action: 'sign';
        step_public_id: Uuid;
    }
    | {
        action: 'verify';
        artifact_public_id: Uuid;
    };

export type EsignUiAction =
    | {
        kind: 'signing';
        detail: EsignOpenEventDetail;
    }
    | {
        kind: 'validation';
        detail: EsignValidationOpenEventDetail;
    };

export interface PdfPageGeometry {
    page: number;
    width: number;
    height: number;
    rotation: PdfRotation;
}

export interface PlacementGeometry {
    page: number;
    page_width: number;
    page_height: number;
    page_rotation: PdfRotation;
    origin_x: number;
    origin_y: number;
    width: number;
    height: number;
}

export interface SignaturePlacement extends PlacementGeometry {
    client_id: Uuid;
    operation_index: number;
}

export type FooterPlacement = PlacementGeometry;

export interface FooterPlan {
    text: string;
    font_key: string;
    font_size_pt: number;
    is_bold: boolean;
    is_italic: boolean;
    is_underline: boolean;
    placements: FooterPlacement[];
}

export interface PreparedFooter extends FooterPlan {
    configuration_sha256: Sha256;
}

export interface FooterEditorConfiguration {
    allowed: boolean;
    required: boolean;
    text: string | null;
    font_key: string | null;
    font_size_pt: number | null;
    is_bold: false;
    is_italic: false;
    is_underline: false;
    font_size_min_pt: number;
    font_size_max_pt: number;
    allowed_fonts: Record<string, string>;
    placements: FooterPlacement[];
}

export interface VisibleSigningEditorConfiguration {
    coordinate_origin: 'top_left';
    measurement_unit: 'pt';
    maximum_signature_count: number;
    qr: {
        minimum_size_pt: number;
        maximum_size_pt: number;
    };
    safe_margin_pt: number;
    minimum_gap_pt: number;
    rotated_pages_supported: false;
    footer: FooterEditorConfiguration;
}

export interface PreparedSignatureOperation extends SignaturePlacement {
    verification_public_id: Uuid;
    verification_url: string;
    qr_sha256: Sha256;
    qr_profile_version: string;
    qr_image_url: string;
}

export interface PreparedSigningRendition {
    revision: Uuid;
    sha256: Sha256;
    request_fingerprint: Sha256;
    renderer_version: string;
    signature_count: number;
    signature_operations: PreparedSignatureOperation[];
    footer: PreparedFooter | null;
    expires_at: Iso8601DateTime;
    preview_url: string;
}

export interface SigningSession {
    session_id: Uuid;
    session_url: string;
    signer_name: string;
    masked_nik: string;
    placement_required: boolean;
    artifact_version: number;
    artifact_sha256: Sha256;
    signature_state: SignatureState;
    verified_signature_count: number;
    footer_applied: boolean;
    pages: PdfPageGeometry[];
    expires_at: Iso8601DateTime;
    editor: VisibleSigningEditorConfiguration;
    preview_url: string;
    prepare_rendition_url: string;
    sign_url: string;
}

/** POST /esign/internal/signing-sessions does not include prepared_rendition. */
export type CreateSigningSessionResponse = ApiEnvelope<SigningSession>;

/** GET session always includes prepared_rendition, with null when absent. */
export type ShowSigningSessionResponse = ApiEnvelope<
    SigningSession & {
        prepared_rendition: PreparedSigningRendition | null;
    }
>;

export interface CreateSigningSessionRequest {
    step_public_id: Uuid;
}

export interface PrepareSigningRenditionRequest {
    placements: SignaturePlacement[];
    footer: FooterPlan | null;
}

export type PrepareSigningRenditionResponse = ApiEnvelope<PreparedSigningRendition>;

export interface SignDocumentRequest {
    affirmed: true;
    idempotency_key: Uuid;
    passphrase: string;
    prepared_revision: Uuid;
    preview_sha256: Sha256;
}

export interface ResumeEsignAttemptRequest {
    affirmed: true;
    passphrase: string;
}

export type EsignAttemptStatus =
    | 'prepared'
    | 'signing'
    | 'partially_signed'
    | 'validating'
    | 'succeeded'
    | 'failed'
    | 'unknown';

export type EsignSignatureOperationStatus =
    | 'pending'
    | 'signing'
    | 'output_received'
    | 'completed'
    | 'failed'
    | 'unknown';

export interface AcceptedEsignAttempt {
    attempt_id: Uuid;
    status: EsignAttemptStatus;
    status_url: string;
}

export type SignDocumentResponse = ApiEnvelope<AcceptedEsignAttempt>;
export type ResumeEsignAttemptResponse = ApiEnvelope<AcceptedEsignAttempt>;

export interface EsignSignatureOperationProgress {
    index: number;
    status: EsignSignatureOperationStatus;
    retryable: boolean;
}

export interface EsignAttemptProgress {
    planned: number;
    completed: number;
    current_index: number;
    operations: EsignSignatureOperationProgress[];
}

export interface EsignAttemptDetails {
    attempt_id: Uuid;
    attempt_number: number;
    status: EsignAttemptStatus;
    retryable: boolean;
    progress: EsignAttemptProgress;
    requires_passphrase: boolean;
    resume_url: string | null;
    requires_reconciliation: boolean;
    error_code: string | null;
    result_artifact_id: Uuid | null;
    started_at: Iso8601DateTime | null;
    completed_at: Iso8601DateTime | null;
    next_poll_after_ms: number | null;
}

export type ShowEsignAttemptResponse = ApiEnvelope<EsignAttemptDetails>;

export interface ValidationErrorResponse {
    message: string;
    errors: Record<string, string[]>;
}

export interface SigningConflictErrorResponse {
    message: string;
    error: {
        code: string;
    };
}

export interface GenericErrorResponse {
    message: string;
}

export type EsignErrorCategory =
    | 'authentication'
    | 'authorization'
    | 'not_found'
    | 'conflict'
    | 'validation'
    | 'rate_limited'
    | 'server'
    | 'network'
    | 'invalid_response';

export interface NormalizedEsignError {
    category: EsignErrorCategory;
    status: number | null;
    message: string;
    code: string | null;
    field_errors: Record<string, string[]>;
    retry_after_seconds: number | null;
}

export type KnownSigningConflictCode =
    | 'esign.idempotency_payload_mismatch'
    | 'esign.prepared_rendition_busy'
    | 'esign.prepared_rendition_changed'
    | 'esign.prepared_rendition_context_mismatch'
    | 'esign.prepared_rendition_not_found'
    | 'esign.prepared_revision_not_allowed'
    | 'esign.prepared_revision_required'
    | 'esign.preview_hash_mismatch'
    | 'esign.signing_session_context_changed'
    | 'esign.signing_session_expired'
    | 'esign.signing_session_invalid'
    | 'esign.signing_session_role_changed'
    | 'esign.source_artifact_changed'
    | 'esign.visible_placement_not_required'
    | 'esign.visible_worker_not_ready';

export type KnownSigningPlanValidationCode =
    | 'esign.footer_all_pages_required'
    | 'esign.footer_already_applied'
    | 'esign.footer_box_too_small'
    | 'esign.footer_for_signed_pdf_forbidden'
    | 'esign.footer_font_size_invalid'
    | 'esign.footer_page_invalid'
    | 'esign.footer_placement_invalid'
    | 'esign.footer_required_for_unsigned_pdf'
    | 'esign.footer_style_invalid'
    | 'esign.page_geometry_mismatch'
    | 'esign.placement_collision'
    | 'esign.placement_number_invalid'
    | 'esign.placement_outside_safe_area'
    | 'esign.rotated_pdf_not_supported'
    | 'esign.signature_count_invalid'
    | 'esign.signature_operation_order_invalid'
    | 'esign.signature_page_invalid'
    | 'esign.signature_placement_invalid'
    | 'esign.signature_size_invalid';

export const ESIGN_BINARY_MEDIA_TYPES = {
    pdf: 'application/pdf',
    qr: 'image/png',
} as const;
