import { mount } from 'svelte';
import EsignApp from './components/EsignApp.svelte';
import type { EsignOpenEventDetail } from './types';
import '../../css/esign/esign.css';

export interface EsignAppApi {
    open(action: EsignOpenEventDetail): void;
}

export function mountEsignApp(
    target: HTMLElement,
): EsignAppApi {
    return mount(EsignApp, {
        target,
    }) as EsignAppApi;
}
