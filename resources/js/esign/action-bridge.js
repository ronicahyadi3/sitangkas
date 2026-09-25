import {
    ESIGN_OPEN_EVENT,
    dispatchEsignOpen,
    dispatchEsignValidationOpen,
} from './events';

export { ESIGN_OPEN_EVENT };

const ACTION_SELECTOR = '[data-esign-action="sign"], [data-esign-action="verify"]';

let removeListener = null;

function handleEsignAction(event) {
    const target = event.target instanceof Element
        ? event.target.closest(ACTION_SELECTOR)
        : null;

    if (!(target instanceof HTMLButtonElement) || target.disabled) {
        return;
    }

    event.preventDefault();

    const action = target.dataset.esignAction;

    if (action === 'sign') {
        dispatchEsignOpen({
            step_public_id: target.dataset.esignStep ?? '',
            can_sign: target.dataset.esignCanSign === 'true',
            can_verify: target.dataset.esignCanVerify === 'true',
        });
    }

    if (action === 'verify') {
        dispatchEsignValidationOpen({
            artifact_public_id: target.dataset.esignArtifact ?? '',
            can_verify: target.dataset.esignCanVerify === 'true',
            verification_url: target.dataset.esignVerificationUrl ?? '',
            preview_url: target.dataset.esignPreviewUrl ?? '',
        });
    }
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

if (import.meta.hot) {
    import.meta.hot.dispose(() => removeListener?.());
}
