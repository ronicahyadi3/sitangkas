import type {
    DocumentDetailActions,
    DocumentDetailSourceState,
    PdfDeliveryMode,
} from '../../esign/types';

export type PdfViewerResource = 'document' | 'billing' | 'spj_fungsional' | 'history';

export interface SecurePdfViewerOpenDetail {
    document_id: string;
    resource: PdfViewerResource;
    title: string;
    document_type: string | null;
    payment_type: string | null;
    source_state: DocumentDetailSourceState;
    delivery_mode: PdfDeliveryMode;
    actions: Pick<DocumentDetailActions, 'view' | 'download' | 'verify'>;
}

export interface SecurePdfViewerClosedDetail {
    document_id: string;
    resource: PdfViewerResource;
}
