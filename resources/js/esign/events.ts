import type {
    EsignClosedEventDetail,
    EsignCompletedEventDetail,
    EsignOpenEventDetail,
    EsignValidationOpenEventDetail,
} from './types';

export const ESIGN_OPEN_EVENT = 'sitangkas:esign:open';
export const ESIGN_VALIDATION_OPEN_EVENT = 'sitangkas:esign:validation-open';
export const ESIGN_COMPLETED_EVENT = 'sitangkas:esign:completed';
export const ESIGN_CLOSED_EVENT = 'sitangkas:esign:closed';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function isEsignOpenEventDetail(value: unknown): value is EsignOpenEventDetail {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const detail = value as Partial<EsignOpenEventDetail>;

    return typeof detail.step_public_id === 'string'
        && UUID_PATTERN.test(detail.step_public_id)
        && detail.can_sign === true
        && typeof detail.can_verify === 'boolean';
}

export function isEsignValidationOpenEventDetail(value: unknown): value is EsignValidationOpenEventDetail {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const detail = value as Partial<EsignValidationOpenEventDetail>;

    return typeof detail.artifact_public_id === 'string'
        && UUID_PATTERN.test(detail.artifact_public_id)
        && detail.can_verify === true;
}

export function isEsignCompletedEventDetail(value: unknown): value is EsignCompletedEventDetail {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const detail = value as Partial<EsignCompletedEventDetail>;

    return typeof detail.step_public_id === 'string'
        && UUID_PATTERN.test(detail.step_public_id)
        && typeof detail.attempt_id === 'string'
        && UUID_PATTERN.test(detail.attempt_id)
        && typeof detail.result_artifact_id === 'string'
        && UUID_PATTERN.test(detail.result_artifact_id)
        && detail.result === 'succeeded';
}

export function isEsignClosedEventDetail(value: unknown): value is EsignClosedEventDetail {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const detail = value as Partial<EsignClosedEventDetail>;

    if (detail.action === 'sign') {
        return typeof detail.step_public_id === 'string'
            && UUID_PATTERN.test(detail.step_public_id);
    }

    return detail.action === 'verify'
        && typeof detail.artifact_public_id === 'string'
        && UUID_PATTERN.test(detail.artifact_public_id);
}

export function dispatchEsignOpen(detail: EsignOpenEventDetail): boolean {
    if (!isEsignOpenEventDetail(detail)) {
        return false;
    }

    return window.dispatchEvent(new CustomEvent(ESIGN_OPEN_EVENT, {
        detail: Object.freeze({ ...detail }),
    }));
}

export function dispatchEsignValidationOpen(detail: EsignValidationOpenEventDetail): boolean {
    if (!isEsignValidationOpenEventDetail(detail)) {
        return false;
    }

    return window.dispatchEvent(new CustomEvent(ESIGN_VALIDATION_OPEN_EVENT, {
        detail: Object.freeze({ ...detail }),
    }));
}

export function dispatchEsignCompleted(detail: EsignCompletedEventDetail): boolean {
    if (!isEsignCompletedEventDetail(detail)) {
        return false;
    }

    return window.dispatchEvent(new CustomEvent(ESIGN_COMPLETED_EVENT, {
        detail: Object.freeze({ ...detail }),
    }));
}

export function dispatchEsignClosed(detail: EsignClosedEventDetail): boolean {
    if (!isEsignClosedEventDetail(detail)) {
        return false;
    }

    return window.dispatchEvent(new CustomEvent(ESIGN_CLOSED_EVENT, {
        detail: Object.freeze({ ...detail }),
    }));
}
