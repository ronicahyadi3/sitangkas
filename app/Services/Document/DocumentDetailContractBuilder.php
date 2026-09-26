<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\DocumentDetailContractData;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailActionMode;
use App\Enums\Document\DocumentDetailSourceState;
use App\Enums\Esign\DocumentArtifactType;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Support\EncryptedId;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DocumentDetailContractBuilder
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly PdfDeliverySource $pdfDeliverySource,
    ) {}

    public function build(
        Document $document,
        bool $legacyCanSign,
        bool $canDownload,
        ?string $stepPublicId = null,
    ): DocumentDetailContractData {
        [$resolvedSource, $sourceResolutionFailed] = $this->resolveSource(
            $document,
            PdfDeliverySource::RESOURCE_DOCUMENT,
        );
        $artifactPublicId = $resolvedSource?->artifactPublicId;
        $sourceState = $resolvedSource?->sourceState ?? DocumentDetailSourceState::Unavailable;
        $actionMode = match ($sourceState) {
            DocumentDetailSourceState::Canonical => DocumentDetailActionMode::Canonical,
            DocumentDetailSourceState::LegacyPrivatePending,
            DocumentDetailSourceState::LegacyPublicPending => DocumentDetailActionMode::LegacyTransition,
            DocumentDetailSourceState::Unavailable => DocumentDetailActionMode::None,
        };
        $resolvedStepPublicId = $this->uuidOrNull($stepPublicId);
        $canView = $sourceState !== DocumentDetailSourceState::Unavailable;
        $canSign = match ($actionMode) {
            DocumentDetailActionMode::Canonical => $resolvedStepPublicId !== null,
            DocumentDetailActionMode::LegacyTransition => $legacyCanSign,
            DocumentDetailActionMode::None => false,
        };
        $canVerify = $sourceState === DocumentDetailSourceState::Canonical
            && $artifactPublicId !== null
            && $this->config->get('esign.frontend.enabled') === true;
        $downloadAvailableInViewer = $canView && $canDownload;
        $encryptedDocumentId = EncryptedId::encode((int) $document->getKey());

        return new DocumentDetailContractData(
            documentId: $encryptedDocumentId,
            paymentType: Str::upper(trim((string) ($document->payment_type ?? ''))),
            documentType: Str::upper(trim((string) ($document->src_type ?? ''))),
            status: $this->status($document, $resolvedSource, $sourceState, $canSign),
            capabilities: [
                'view' => $canView,
                'sign' => $canSign,
                'verify' => $canVerify,
                'download_available_in_viewer' => $downloadAvailableInViewer,
            ],
            delivery: $this->deliveryUrls(
                $encryptedDocumentId,
                PdfDeliverySource::RESOURCE_DOCUMENT,
                $canView,
                $downloadAvailableInViewer,
            ),
            stepPublicId: $canSign && $actionMode === DocumentDetailActionMode::Canonical
                ? $resolvedStepPublicId
                : null,
            artifactPublicId: $artifactPublicId,
            sourceState: $sourceState,
            actionMode: $actionMode,
            disabledReasons: [
                'view' => $canView ? null : $this->reason(
                    $sourceResolutionFailed
                        ? 'document_source_invalid'
                        : 'document_source_unavailable',
                    $sourceResolutionFailed
                        ? 'Sumber dokumen memerlukan pemeriksaan administrator.'
                        : 'Sumber dokumen belum tersedia untuk ditampilkan.',
                ),
                'sign' => $canSign ? null : $this->signDisabledReason(
                    $sourceState,
                    $sourceResolutionFailed,
                ),
                'verify' => $canVerify ? null : $this->verificationDisabledReason($sourceState),
                'download' => $downloadAvailableInViewer ? null : $this->downloadDisabledReason(
                    $canView,
                    $canDownload,
                ),
            ],
            attachments: $this->attachments($document, $canDownload),
        );
    }

    /**
     * @return array{code: string, label: string, tone: string}
     */
    private function status(
        Document $document,
        ?ResolvedPdfDeliverySource $resolvedSource,
        DocumentDetailSourceState $sourceState,
        bool $canSign,
    ): array {
        if ($sourceState === DocumentDetailSourceState::Unavailable) {
            return [
                'code' => 'unavailable',
                'label' => 'Dokumen tidak tersedia',
                'tone' => 'danger',
            ];
        }

        $artifactType = (string) ($resolvedSource?->metadata['artifact_type'] ?? '');
        $isSigned = $sourceState === DocumentDetailSourceState::Canonical
            ? $artifactType === DocumentArtifactType::AfterSign->value
            : ($document->status ?? null) !== null;

        if ($isSigned) {
            return [
                'code' => 'signed',
                'label' => 'Sudah TTE',
                'tone' => 'success',
            ];
        }

        if ($canSign) {
            return [
                'code' => 'ready_to_sign',
                'label' => 'Siap TTE',
                'tone' => 'warning',
            ];
        }

        return [
            'code' => 'waiting_for_signature',
            'label' => 'Menunggu TTE',
            'tone' => 'secondary',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function attachments(Document $document, bool $canDownload): array
    {
        $attachments = [];

        if (filled($document->billing ?? null)) {
            [$source, $resolutionFailed] = $this->resolveSource(
                $document,
                PdfDeliverySource::RESOURCE_BILLING,
            );
            $attachments[] = $this->attachment(
                document: $document,
                key: 'billing',
                label: 'Billing',
                source: $source,
                resolutionFailed: $resolutionFailed,
                canDownload: $canDownload,
            );
        }

        if (filled($document->spj_fungsional ?? null)) {
            [$source, $resolutionFailed] = $this->resolveSource(
                $document,
                PdfDeliverySource::RESOURCE_SPJ_FUNCTIONAL,
            );
            $attachments[] = $this->attachment(
                document: $document,
                key: 'spj_fungsional',
                label: 'SPJ Fungsional',
                source: $source,
                resolutionFailed: $resolutionFailed,
                canDownload: $canDownload,
            );
        }

        return $attachments;
    }

    /** @return array<string, mixed> */
    private function attachment(
        Document $document,
        string $key,
        string $label,
        ?ResolvedPdfDeliverySource $source,
        bool $resolutionFailed,
        bool $canDownload,
    ): array {
        $artifactPublicId = $source?->artifactPublicId;
        $sourceState = $source?->sourceState ?? DocumentDetailSourceState::Unavailable;
        $canView = $sourceState !== DocumentDetailSourceState::Unavailable;
        $canVerify = $sourceState === DocumentDetailSourceState::Canonical
            && $this->config->get('esign.frontend.enabled') === true;
        $downloadAvailableInViewer = $canView && $canDownload;

        return [
            'key' => $key,
            'label' => $label,
            'artifact_public_id' => $artifactPublicId,
            'source_state' => $sourceState->value,
            'action_mode' => $sourceState === DocumentDetailSourceState::Canonical
                ? DocumentDetailActionMode::Canonical->value
                : ($sourceState === DocumentDetailSourceState::Unavailable
                    ? DocumentDetailActionMode::None->value
                    : DocumentDetailActionMode::LegacyTransition->value),
            'capabilities' => [
                'view' => $canView,
                'sign' => false,
                'verify' => $canVerify,
                'download_available_in_viewer' => $downloadAvailableInViewer,
            ],
            'delivery' => $this->deliveryUrls(
                EncryptedId::encode((int) $document->getKey()),
                $key,
                $canView,
                $downloadAvailableInViewer,
            ),
            'disabled_reasons' => [
                'view' => $canView ? null : $this->reason(
                    $resolutionFailed
                        ? 'document_attachment_invalid'
                        : 'document_attachment_unavailable',
                    $resolutionFailed
                        ? 'Sumber lampiran memerlukan pemeriksaan administrator.'
                        : 'Sumber lampiran belum tersedia untuk ditampilkan.',
                ),
                'sign' => $this->reason(
                    'document_attachment_not_signable',
                    'Lampiran ini tidak tersedia untuk proses TTE.',
                ),
                'verify' => $canVerify ? null : $this->verificationDisabledReason($sourceState),
                'download' => $downloadAvailableInViewer ? null : $this->downloadDisabledReason(
                    $canView,
                    $canDownload,
                ),
            ],
        ];
    }

    /** @return array{content_url: string|null, download_url: string|null} */
    private function deliveryUrls(
        string $encryptedDocumentId,
        string $resourceKey,
        bool $canView,
        bool $canDownload,
    ): array {
        $parameters = [
            'document' => $encryptedDocumentId,
            'resource' => $resourceKey,
        ];

        return [
            'content_url' => $canView
                ? route('document.pdf.content', $parameters)
                : null,
            'download_url' => $canDownload
                ? route('document.pdf.download', $parameters)
                : null,
        ];
    }

    /** @return array{code: string, message: string} */
    private function signDisabledReason(
        DocumentDetailSourceState $sourceState,
        bool $sourceResolutionFailed,
    ): array {
        if ($sourceResolutionFailed) {
            return $this->reason(
                'document_source_invalid',
                'Sumber dokumen memerlukan pemeriksaan administrator.',
            );
        }

        return match ($sourceState) {
            DocumentDetailSourceState::Canonical => $this->reason(
                'document_step_not_signable',
                'Dokumen belum berada pada langkah TTE aktif pengguna ini.',
            ),
            DocumentDetailSourceState::LegacyPrivatePending,
            DocumentDetailSourceState::LegacyPublicPending => $this->reason(
                'legacy_sign_not_available',
                'TTE legacy tidak tersedia untuk posisi aktif atau keadaan dokumen ini.',
            ),
            DocumentDetailSourceState::Unavailable => $this->reason(
                'document_source_unavailable',
                'Sumber dokumen belum tersedia untuk proses TTE.',
            ),
        };
    }

    /** @return array{code: string, message: string} */
    private function verificationDisabledReason(DocumentDetailSourceState $sourceState): array
    {
        if ($sourceState !== DocumentDetailSourceState::Canonical) {
            return $this->reason(
                'canonical_artifact_required',
                'Validasi tersedia setelah dokumen mempunyai artifact canonical.',
            );
        }

        return $this->reason(
            'esign_frontend_disabled',
            'Fitur validasi dokumen belum diaktifkan.',
        );
    }

    /** @return array{code: string, message: string} */
    private function downloadDisabledReason(bool $canView, bool $canDownload): array
    {
        if (! $canView) {
            return $this->reason(
                'document_source_unavailable',
                'Sumber dokumen belum tersedia untuk diunduh.',
            );
        }

        if (! $canDownload) {
            return $this->reason(
                'document_download_not_authorized',
                'Posisi aktif tidak diizinkan mengunduh dokumen.',
            );
        }

        return $this->reason(
            'document_download_unavailable',
            'Dokumen belum tersedia untuk diunduh dari viewer.',
        );
    }

    /** @return array{code: string, message: string} */
    private function reason(string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
        ];
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    /**
     * @return array{0: ResolvedPdfDeliverySource|null, 1: bool}
     */
    private function resolveSource(Document $document, string $resourceKey): array
    {
        try {
            return [$this->pdfDeliverySource->resolve($document, $resourceKey), false];
        } catch (EsignInvariantViolationException|InvalidArgumentException $exception) {
            Log::channel('module_document_data')->warning('Document PDF source invariant failed', [
                'document_id' => (int) $document->getKey(),
                'resource_key' => $resourceKey,
                'error_code' => $exception instanceof EsignInvariantViolationException
                    ? $exception->invariantCode
                    : $exception->getMessage(),
            ]);

            return [null, true];
        }
    }
}
