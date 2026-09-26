//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import './realtime-presence';
import { installEsignActionBridge } from './esign/action-bridge';
import { installEsignIslandLoader } from './esign/island-loader';
import { installEsignPageAdapter } from './esign/page-adapter';
import { installPdfViewerIslandLoader } from './documents/pdf-viewer/island-loader';
import { installPdfViewerActionBridge } from './documents/pdf-viewer/action-bridge';
import { installDocumentDetailActionRenderer } from './documents/detail-action-renderer';

installEsignIslandLoader();
installEsignActionBridge();
installEsignPageAdapter();
installPdfViewerIslandLoader();
installPdfViewerActionBridge();
installDocumentDetailActionRenderer();
