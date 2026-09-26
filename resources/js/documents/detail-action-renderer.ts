import type {
    DocumentDetailActions,
    DocumentDetailAttachmentContract,
    DocumentDetailContract,
    DocumentDetailStatus,
} from '../esign/types';
import { isSecurePdfViewerOpenDetail } from './pdf-viewer/events';
import type {
    PdfViewerResource,
    SecurePdfViewerOpenDetail,
} from './pdf-viewer/types';
import '../../css/documents/detail-actions.css';

type DetailCellRenderer = (value: unknown, type: string, row: unknown) => string;

declare global {
    interface Window {
        renderDocumentDetailStatus?: DetailCellRenderer;
        renderDocumentDetailActions?: DetailCellRenderer;
    }
}

const STATUS_TONES = new Set(['success', 'warning', 'secondary', 'danger']);
const DELIVERY_MODES = new Set(['original', 'identified_watermarked', 'public_watermarked']);

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function isStatus(value: unknown): value is DocumentDetailStatus {
    return isRecord(value)
        && typeof value.code === 'string'
        && typeof value.label === 'string'
        && typeof value.tone === 'string'
        && STATUS_TONES.has(value.tone);
}

function isActions(value: unknown): value is DocumentDetailActions {
    if (!isRecord(value)
        || !isRecord(value.view)
        || !isRecord(value.download)
        || !isRecord(value.verify)
        || !isRecord(value.sign)) {
        return false;
    }

    return typeof value.view.allowed === 'boolean'
        && typeof value.download.allowed === 'boolean'
        && typeof value.verify.allowed === 'boolean'
        && typeof value.sign.allowed === 'boolean'
        && typeof value.sign.mode === 'string';
}

function isAttachment(value: unknown): value is DocumentDetailAttachmentContract {
    return isRecord(value)
        && (value.key === 'billing' || value.key === 'spj_fungsional')
        && typeof value.label === 'string'
        && typeof value.source_state === 'string'
        && typeof value.delivery_mode === 'string'
        && DELIVERY_MODES.has(value.delivery_mode)
        && isActions(value.actions);
}

function isContract(value: unknown): value is DocumentDetailContract {
    return isRecord(value)
        && value.contract_version === 1
        && typeof value.document_id === 'string'
        && typeof value.payment_type === 'string'
        && typeof value.document_type === 'string'
        && typeof value.source_state === 'string'
        && typeof value.delivery_mode === 'string'
        && DELIVERY_MODES.has(value.delivery_mode)
        && isStatus(value.status)
        && isActions(value.actions)
        && Array.isArray(value.attachments)
        && value.attachments.every(isAttachment);
}

function viewerPayload(
    contract: DocumentDetailContract,
    resource: PdfViewerResource,
    title: string,
    actions: DocumentDetailActions,
    sourceState: DocumentDetailContract['source_state'],
    deliveryMode: DocumentDetailContract['delivery_mode'],
): SecurePdfViewerOpenDetail | null {
    const payload: SecurePdfViewerOpenDetail = {
        document_id: contract.document_id,
        resource,
        title,
        document_type: resource === 'document' ? contract.document_type : title,
        payment_type: contract.payment_type,
        source_state: sourceState,
        delivery_mode: deliveryMode,
        actions: {
            view: actions.view,
            download: actions.download,
            verify: actions.verify,
        },
    };

    return isSecurePdfViewerOpenDetail(payload) ? payload : null;
}

function encodedPayload(payload: SecurePdfViewerOpenDetail): string {
    return encodeURIComponent(JSON.stringify(payload));
}

function viewerButton(payload: SecurePdfViewerOpenDetail, label: string, attachment = false): string {
    const buttonClass = attachment ? 'btn-outline-primary' : 'btn-primary';
    const icon = attachment ? 'fa-paperclip' : 'fa-eye';

    return `<button type="button" class="btn btn-sm ${buttonClass} document-detail-action__button"`
        + ` data-document-pdf-action="view" data-pdf-viewer-payload="${encodedPayload(payload)}"`
        + ` aria-label="Tampilkan ${escapeHtml(label)}">`
        + `<i class="fas ${icon}" aria-hidden="true"></i><span>${escapeHtml(label)}</span></button>`;
}

function canonicalSignButton(actions: DocumentDetailActions): string {
    if (!actions.sign.allowed
        || actions.sign.mode !== 'canonical'
        || typeof actions.sign.step_public_id !== 'string'
        || actions.sign.step_public_id === '') {
        return '';
    }

    return '<button type="button" class="btn btn-sm btn-warning document-detail-action__button"'
        + ' data-esign-action="sign"'
        + ` data-esign-step="${escapeHtml(actions.sign.step_public_id)}"`
        + ' data-esign-can-sign="true"'
        + ` data-esign-can-verify="${actions.verify.allowed ? 'true' : 'false'}"`
        + ' aria-label="Tanda tangani dokumen">'
        + '<i class="fas fa-signature" aria-hidden="true"></i><span>TTE</span></button>';
}

function disabledMessage(contract: DocumentDetailContract): string {
    const reason = contract.actions.view.reason?.message;

    if (typeof reason !== 'string' || reason.trim() === '') {
        return '';
    }

    return `<small class="document-detail-action__reason"><i class="fas fa-lock" aria-hidden="true"></i>${escapeHtml(reason)}</small>`;
}

function contractFromRow(row: unknown): DocumentDetailContract | null {
    const contractValue = isRecord(row) ? row.document_contract : null;

    return isContract(contractValue) ? contractValue : null;
}

function renderStatus(_value: unknown, type: string, row: unknown): string {
    const contract = contractFromRow(row);

    if (contract === null) {
        return type === 'display'
            ? '<span class="badge bg-gradient-danger document-detail-action__status">Kontrak dokumen tidak tersedia</span>'
            : 'Kontrak dokumen tidak tersedia';
    }

    if (type !== 'display') {
        return contract.status.label;
    }

    const badgeTone = STATUS_TONES.has(contract.status.tone)
        ? contract.status.tone
        : 'secondary';

    return `<span class="badge bg-gradient-${badgeTone} document-detail-action__status">${escapeHtml(contract.status.label)}</span>`;
}

function renderActions(_value: unknown, type: string, row: unknown): string {
    const contractValue = contractFromRow(row);

    if (contractValue === null || type !== 'display') {
        return '';
    }

    const buttons: string[] = [];
    const mainPayload = viewerPayload(
        contractValue,
        'document',
        `${contractValue.document_type} ${contractValue.payment_type}`.trim(),
        contractValue.actions,
        contractValue.source_state,
        contractValue.delivery_mode,
    );

    if (mainPayload !== null) {
        buttons.push(viewerButton(mainPayload, 'Tampilkan'));
    }

    for (const attachment of contractValue.attachments) {
        const attachmentPayload = viewerPayload(
            contractValue,
            attachment.key,
            attachment.label,
            attachment.actions,
            attachment.source_state,
            attachment.delivery_mode,
        );

        if (attachmentPayload !== null) {
            buttons.push(viewerButton(attachmentPayload, attachment.label, true));
        }
    }

    const signButton = canonicalSignButton(contractValue.actions);

    if (signButton !== '') {
        buttons.push(signButton);
    }

    const buttonsHtml = buttons.length > 0
        ? `<div class="document-detail-action__buttons">${buttons.join('')}</div>`
        : disabledMessage(contractValue) || '<span class="text-muted">—</span>';

    return `<div class="document-detail-action">${buttonsHtml}</div>`;
}

export function installDocumentDetailActionRenderer(): () => void {
    window.renderDocumentDetailStatus = renderStatus;
    window.renderDocumentDetailActions = renderActions;

    return () => {
        if (window.renderDocumentDetailStatus === renderStatus) {
            delete window.renderDocumentDetailStatus;
        }

        if (window.renderDocumentDetailActions === renderActions) {
            delete window.renderDocumentDetailActions;
        }
    };
}
