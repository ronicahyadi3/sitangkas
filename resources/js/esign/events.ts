import type { EsignOpenEventDetail } from './types';

export const ESIGN_OPEN_EVENT = 'sitangkas:esign:open';
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
