import { mount, unmount } from 'svelte';
import SecurePdfViewerModal from './SecurePdfViewerModal.svelte';
import type { SecurePdfViewerOpenDetail } from './types';
import '../../../css/documents/pdf-viewer.css';

export interface SecurePdfViewerApi {
    destroy(): Promise<void>;
    open(action: SecurePdfViewerOpenDetail): Promise<void>;
}

export function mountSecurePdfViewer(target: HTMLElement): SecurePdfViewerApi {
    const component = mount(SecurePdfViewerModal, { target });

    return {
        destroy: () => unmount(component),
        open: (action) => component.open(action),
    };
}
