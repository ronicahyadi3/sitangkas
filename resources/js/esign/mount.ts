import { mount, unmount } from 'svelte';
import EsignApp from './components/EsignApp.svelte';
import type { EsignUiAction } from './types';
import '../../css/esign/esign.css';

export interface EsignAppApi {
    destroy(): Promise<void>;
    open(action: EsignUiAction): void;
}

export function mountEsignApp(
    target: HTMLElement,
): EsignAppApi {
    const component = mount(EsignApp, {
        target,
    });

    return {
        destroy: () => unmount(component),
        open: (action) => component.open(action),
    };
}
