import type {
    AcceptedEsignAttempt,
    ArtifactVerification,
    EsignAttemptDetails,
    EsignFrontendConfiguration,
    PrepareSigningRenditionRequest,
    PreparedSigningRendition,
    ResumeEsignAttemptRequest,
    ShowSigningSessionResponse,
    SignDocumentRequest,
    SigningSession,
    Uuid,
} from '../types';
import { EsignApiError, invalidResponseError, normalizeHttpError } from './errors';
import {
    isAcceptedEsignAttempt,
    isApiEnvelope,
    isArtifactVerification,
    isEsignAttemptDetails,
    isPreparedSigningRendition,
    isSigningSession,
    isSigningSessionWithPreparedRendition,
} from './guards';

type JsonValidator<TData> = (value: unknown) => value is TData;

interface JsonRequestOptions {
    body?: unknown;
    method: 'GET' | 'POST';
    signal?: AbortSignal;
}

export class EsignApiClient {
    private readonly createSessionUrl: string;

    public constructor(configuration: EsignFrontendConfiguration) {
        this.createSessionUrl = this.sameOriginUrl(configuration.create_session_url);
    }

    public createSigningSession(stepPublicId: Uuid, signal?: AbortSignal): Promise<SigningSession> {
        return this.requestEnvelope(
            this.createSessionUrl,
            {
                method: 'POST',
                body: { step_public_id: stepPublicId },
                signal,
            },
            isSigningSession,
            [201],
        );
    }

    public showSigningSession(
        sessionUrl: string,
        signal?: AbortSignal,
    ): Promise<ShowSigningSessionResponse['data']> {
        return this.requestEnvelope(
            sessionUrl,
            { method: 'GET', signal },
            isSigningSessionWithPreparedRendition,
            [200],
        );
    }

    public prepareSigningRendition(
        prepareUrl: string,
        request: PrepareSigningRenditionRequest,
        signal?: AbortSignal,
    ): Promise<PreparedSigningRendition> {
        return this.requestEnvelope(
            prepareUrl,
            { method: 'POST', body: request, signal },
            isPreparedSigningRendition,
            [201],
        );
    }

    public signDocument(
        signUrl: string,
        request: SignDocumentRequest,
        signal?: AbortSignal,
    ): Promise<AcceptedEsignAttempt> {
        return this.requestEnvelope(
            signUrl,
            { method: 'POST', body: request, signal },
            isAcceptedEsignAttempt,
            [202],
        );
    }

    public showAttempt(statusUrl: string, signal?: AbortSignal): Promise<EsignAttemptDetails> {
        return this.requestEnvelope(
            statusUrl,
            { method: 'GET', signal },
            isEsignAttemptDetails,
            [200],
        );
    }

    public resumeAttempt(
        resumeUrl: string,
        request: ResumeEsignAttemptRequest,
        signal?: AbortSignal,
    ): Promise<AcceptedEsignAttempt> {
        return this.requestEnvelope(
            resumeUrl,
            { method: 'POST', body: request, signal },
            isAcceptedEsignAttempt,
            [202],
        );
    }

    public showArtifactVerification(
        verificationUrl: string,
        signal?: AbortSignal,
    ): Promise<ArtifactVerification> {
        return this.requestEnvelope(
            verificationUrl,
            { method: 'GET', signal },
            isArtifactVerification,
            [200],
        );
    }

    public async fetchPdf(url: string, signal?: AbortSignal): Promise<ArrayBuffer> {
        const response = await this.fetchBinaryResponse(url, 'application/pdf', signal);

        return response.arrayBuffer();
    }

    public async fetchPng(url: string, signal?: AbortSignal): Promise<Blob> {
        const response = await this.fetchBinaryResponse(url, 'image/png', signal);

        return response.blob();
    }

    public async closeSigningSession(sessionUrl: string, signal?: AbortSignal): Promise<void> {
        const response = await this.fetch(sessionUrl, {
            method: 'DELETE',
            headers: this.headers(false, true),
            signal,
        });

        if (!response.ok) {
            throw new EsignApiError(normalizeHttpError(response, await this.readErrorPayload(response)));
        }

        if (response.status !== 204) {
            throw new EsignApiError(invalidResponseError());
        }
    }

    private async requestEnvelope<TData>(
        url: string,
        options: JsonRequestOptions,
        isData: JsonValidator<TData>,
        expectedStatuses: number[],
    ): Promise<TData> {
        const hasBody = options.body !== undefined;
        const response = await this.fetch(url, {
            method: options.method,
            headers: this.headers(hasBody, options.method !== 'GET'),
            body: hasBody ? JSON.stringify(options.body) : undefined,
            signal: options.signal,
        });
        const contentType = response.headers.get('Content-Type')?.toLowerCase() ?? '';

        if (!response.ok) {
            throw new EsignApiError(normalizeHttpError(response, await this.readErrorPayload(response)));
        }

        if (!expectedStatuses.includes(response.status) || !contentType.includes('application/json')) {
            throw new EsignApiError(invalidResponseError());
        }

        let payload: unknown;

        try {
            payload = await response.json();
        } catch {
            throw new EsignApiError(invalidResponseError());
        }

        if (!isApiEnvelope(payload, isData)) {
            throw new EsignApiError(invalidResponseError());
        }

        return payload.data;
    }

    private async fetchBinaryResponse(
        url: string,
        mediaType: string,
        signal?: AbortSignal,
    ): Promise<Response> {
        const response = await this.fetch(url, {
            method: 'GET',
            headers: new Headers({ Accept: mediaType }),
            signal,
        });

        if (!response.ok) {
            throw new EsignApiError(normalizeHttpError(response, await this.readErrorPayload(response)));
        }

        const contentType = response.headers.get('Content-Type')?.toLowerCase() ?? '';

        if (response.status !== 200 || !contentType.startsWith(mediaType)) {
            throw new EsignApiError(invalidResponseError());
        }

        return response;
    }

    private fetch(url: string, init: RequestInit): Promise<Response> {
        return window.fetch(this.sameOriginUrl(url), {
            ...init,
            cache: 'no-store',
            credentials: 'same-origin',
            redirect: 'follow',
        });
    }

    private headers(hasJsonBody: boolean, includeCsrf: boolean): Headers {
        const headers = new Headers({ Accept: 'application/json' });

        if (hasJsonBody) {
            headers.set('Content-Type', 'application/json');
        }

        if (includeCsrf) {
            headers.set('X-CSRF-TOKEN', this.csrfToken());
        }

        return headers;
    }

    private csrfToken(): string {
        const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content.trim();

        if (token === undefined || token === '') {
            throw new EsignApiError({
                ...invalidResponseError(),
                message: 'Token keamanan halaman tidak tersedia. Muat ulang halaman sebelum melanjutkan.',
                code: 'csrf_token_missing',
            });
        }

        return token;
    }

    private sameOriginUrl(value: string): string {
        let url: URL;

        try {
            url = new URL(value, window.location.origin);
        } catch {
            throw new EsignApiError(invalidResponseError());
        }

        if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) {
            throw new EsignApiError({
                ...invalidResponseError(),
                message: 'URL layanan TTE tidak berada pada origin aplikasi.',
                code: 'cross_origin_url_rejected',
            });
        }

        return url.toString();
    }

    private async readErrorPayload(response: Response): Promise<unknown> {
        const contentType = response.headers.get('Content-Type')?.toLowerCase() ?? '';

        if (!contentType.includes('application/json')) {
            return null;
        }

        try {
            return await response.json();
        } catch {
            return null;
        }
    }
}
