import type {
    PDFDocumentLoadingTask,
    PDFDocumentProxy,
} from 'pdfjs-dist';

interface PdfJsModule {
    GlobalWorkerOptions: {
        workerSrc: string;
    };
    getDocument(parameters: {
        data: Uint8Array;
        enableXfa: boolean;
        isEvalSupported: boolean;
    }): PDFDocumentLoadingTask;
}

export interface LoadedPdfDocument {
    document: PDFDocumentProxy;
    destroy(): Promise<void>;
}

let pdfJsPromise: Promise<PdfJsModule> | null = null;

async function pdfJs(): Promise<PdfJsModule> {
    if (pdfJsPromise !== null) {
        return pdfJsPromise;
    }

    pdfJsPromise = Promise.all([
        import('pdfjs-dist'),
        import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]).then(([module, worker]) => {
        module.GlobalWorkerOptions.workerSrc = worker.default;

        return module;
    }).catch((error: unknown) => {
        pdfJsPromise = null;
        throw error;
    });

    return pdfJsPromise;
}

export async function loadPdfDocument(
    binary: ArrayBuffer,
    signal: AbortSignal,
): Promise<LoadedPdfDocument> {
    if (signal.aborted) {
        throw new DOMException('PDF load aborted.', 'AbortError');
    }

    const pdf = await pdfJs();

    if (signal.aborted) {
        throw new DOMException('PDF load aborted.', 'AbortError');
    }

    const loadingTask = pdf.getDocument({
        data: new Uint8Array(binary),
        enableXfa: false,
        isEvalSupported: false,
    });
    const abort = (): void => {
        void loadingTask.destroy();
    };

    signal.addEventListener('abort', abort, { once: true });

    try {
        const document = await loadingTask.promise;

        if (signal.aborted) {
            await loadingTask.destroy();
            throw new DOMException('PDF load aborted.', 'AbortError');
        }

        let destroyed = false;

        return {
            document,
            destroy: async (): Promise<void> => {
                if (destroyed) {
                    return;
                }

                destroyed = true;
                await loadingTask.destroy();
            },
        };
    } finally {
        signal.removeEventListener('abort', abort);
    }
}
