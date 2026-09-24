export const ESIGN_OPEN_EVENT = 'sitangkas:esign:open';

const ACTION_SELECTOR = '[data-esign-action="sign"][data-esign-step]';
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

let removeListener = null;

function handleEsignAction(event) {
    const target = event.target instanceof Element
        ? event.target.closest(ACTION_SELECTOR)
        : null;

    if (!(target instanceof HTMLButtonElement) || target.disabled) {
        return;
    }

    event.preventDefault();

    const stepPublicId = target.dataset.esignStep ?? '';
    const canSign = target.dataset.esignCanSign === 'true';

    if (!canSign || !UUID_PATTERN.test(stepPublicId)) {
        return;
    }

    window.dispatchEvent(new CustomEvent(ESIGN_OPEN_EVENT, {
        detail: Object.freeze({
            step_public_id: stepPublicId,
            can_sign: true,
            can_verify: target.dataset.esignCanVerify === 'true',
        }),
    }));
}

export function installEsignActionBridge() {
    if (removeListener !== null) {
        return removeListener;
    }

    document.addEventListener('click', handleEsignAction);
    removeListener = () => {
        document.removeEventListener('click', handleEsignAction);
        removeListener = null;
    };

    return removeListener;
}

installEsignActionBridge();

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}

