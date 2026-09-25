import { mount, unmount } from 'svelte';
import EsignApp from './components/EsignApp.svelte';
import type { EsignFrontendConfiguration, EsignUiAction } from './types';
import '../../css/esign/esign.css';

export interface EsignAppApi {
    destroy(): Promise<void>;
    open(action: EsignUiAction): Promise<void>;
}

export function mountEsignApp(
    target: HTMLElement,
): EsignAppApi {
    const createSessionUrl = target.dataset.esignSessionUrl?.trim();

    if (createSessionUrl === undefined || createSessionUrl === '') {
        throw new Error('Esign session endpoint is not available.');
    }

    const configuration: EsignFrontendConfiguration = {
        create_session_url: createSessionUrl,
    };
    const component = mount(EsignApp, {
        target,
        props: { configuration },
    });

    return {
        destroy: () => unmount(component),
        open: (action) => component.open(action),
    };
}
