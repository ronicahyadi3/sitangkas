<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Enums\Document\PdfDeliveryMode;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Models\Document;
use App\Models\DocumentHistory;
use App\Models\User;
use App\Support\EncryptedId;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

final class PdfViewerContractBuilder
{
    public function __construct(
        private readonly DocumentActionResolver $documentActions,
        private readonly DocumentHistoryPdfDeliverySource $historySource,
        private readonly PdfDeliveryAuthorizationService $authorization,
        private readonly ConfigRepository $config,
    ) {}

    /** @return array<string, mixed> */
    public function forDocument(
        Document $document,
        User $actor,
        string $resourceKey,
    ): array {
        $action = $this->documentActions->resolve($document, $actor, $resourceKey);
        abort_unless($action->canView && $action->source !== null, 404);

        $encryptedDocumentId = EncryptedId::encode((int) $document->getKey());
        $artifactPublicId = $action->canVerify ? $action->artifactPublicId : null;

        return [
            'document_id' => $encryptedDocumentId,
            'resource' => $resourceKey,
            'title' => $this->title($document, $resourceKey),
            'document_type' => Str::upper(trim((string) $document->src_type)),
            'payment_type' => Str::upper(trim((string) $document->payment_type)),
            'source_state' => $action->sourceState->value,
            'delivery_mode' => PdfDeliveryMode::Original->value,
            'actions' => [
                'view' => [
                    'allowed' => true,
                    'url' => route('document.pdf.content', [
                        'document' => $encryptedDocumentId,
                        'resource' => $resourceKey,
                    ]),
                    'reason' => null,
                ],
                'download' => [
                    'allowed' => $action->canDownload,
                    'url' => $action->canDownload
                        ? route('document.pdf.download', [
                            'document' => $encryptedDocumentId,
                            'resource' => $resourceKey,
                        ])
                        : null,
                    'reason' => $action->downloadDisabledReason,
                ],
                'verify' => $this->verificationAction($artifactPublicId),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function forHistory(DocumentHistory $history, User $actor): array
    {
        $history->loadMissing('document');
        $document = $history->document;
        abort_unless($document instanceof Document, 404);

        $source = $this->historySource->resolve($history);
        abort_unless($source !== null, 404);

        $this->authorization
            ->authorize($actor, $source, PdfDeliveryPurpose::View)
            ->authorize();
        $canDownload = $this->authorization
            ->authorize($actor, $source, PdfDeliveryPurpose::Download)
            ->allowed();
        $encryptedHistoryId = EncryptedId::encode((int) $history->getKey());
        $canVerify = $source->sourceState === DocumentDetailSourceState::Canonical
            && $source->artifactPublicId !== null
            && $this->config->get('esign.frontend.enabled') === true;

        return [
            'document_id' => $encryptedHistoryId,
            'resource' => 'history',
            'title' => trim(sprintf(
                '%s - Riwayat %s',
                Str::upper((string) $document->src_type),
                Str::upper((string) $history->action),
            )),
            'document_type' => Str::upper(trim((string) $document->src_type)),
            'payment_type' => Str::upper(trim((string) $document->payment_type)),
            'source_state' => $source->sourceState->value,
            'delivery_mode' => PdfDeliveryMode::Original->value,
            'actions' => [
                'view' => [
                    'allowed' => true,
                    'url' => route('document.history-pdf.content', [
                        'history' => $encryptedHistoryId,
                    ]),
                    'reason' => null,
                ],
                'download' => [
                    'allowed' => $canDownload,
                    'url' => $canDownload
                        ? route('document.history-pdf.download', [
                            'history' => $encryptedHistoryId,
                        ])
                        : null,
                    'reason' => $canDownload ? null : [
                        'code' => 'document_download_not_authorized',
                        'message' => 'Posisi aktif tidak diizinkan mengunduh dokumen.',
                    ],
                ],
                'verify' => $this->verificationAction(
                    $canVerify ? $source->artifactPublicId : null,
                ),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function verificationAction(?string $artifactPublicId): array
    {
        return [
            'allowed' => $artifactPublicId !== null,
            'artifact_public_id' => $artifactPublicId,
            'verification_url' => $artifactPublicId !== null
                ? route('esign.internal.artifacts.verification.show', [
                    'documentArtifact' => $artifactPublicId,
                ])
                : null,
            'preview_url' => $artifactPublicId !== null
                ? route('esign.internal.artifacts.verification.preview', [
                    'documentArtifact' => $artifactPublicId,
                ])
                : null,
            'reason' => $artifactPublicId === null ? [
                'code' => 'canonical_artifact_required',
                'message' => 'Validasi tersedia untuk artifact canonical.',
            ] : null,
        ];
    }

    private function title(Document $document, string $resourceKey): string
    {
        return match ($resourceKey) {
            PdfDeliverySource::RESOURCE_BILLING => 'Billing',
            PdfDeliverySource::RESOURCE_SPJ_FUNCTIONAL => 'SPJ Fungsional',
            default => trim(sprintf(
                '%s %s',
                Str::upper((string) $document->src_type),
                Str::upper((string) $document->payment_type),
            )),
        };
    }
}
