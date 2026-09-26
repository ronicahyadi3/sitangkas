<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\DocumentDetailContractData;
use App\Data\Document\DocumentResourceActionData;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailActionMode;
use App\Enums\Document\DocumentDetailSourceState;
use App\Enums\Document\PdfDeliveryMode;
use App\Enums\Esign\DocumentArtifactType;
use App\Models\Document;
use App\Models\User;
use App\Support\EncryptedId;
use Illuminate\Support\Str;

final class DocumentDetailContractBuilder
{
    public function __construct(
        private readonly DocumentActionResolver $actionResolver,
    ) {}

    public function build(
        Document $document,
        User $actor,
        ?string $stepPublicId = null,
    ): DocumentDetailContractData {
        $action = $this->actionResolver->resolve(
            $document,
            $actor,
            PdfDeliverySource::RESOURCE_DOCUMENT,
            $stepPublicId,
        );
        $encryptedDocumentId = EncryptedId::encode((int) $document->getKey());

        return new DocumentDetailContractData(
            documentId: $encryptedDocumentId,
            paymentType: Str::upper(trim((string) ($document->payment_type ?? ''))),
            documentType: Str::upper(trim((string) ($document->src_type ?? ''))),
            status: $this->status(
                $document,
                $action->source,
                $action->sourceState,
                $action->canSign,
            ),
            capabilities: [
                'view' => $action->canView,
                'sign' => $action->canSign,
                'verify' => $action->canVerify,
                'download_available_in_viewer' => $action->canDownload,
            ],
            delivery: $this->deliveryUrls(
                $encryptedDocumentId,
                PdfDeliverySource::RESOURCE_DOCUMENT,
                $action->canView,
                $action->canDownload,
            ),
            actions: $this->actions($action, $encryptedDocumentId),
            stepPublicId: $action->stepPublicId,
            artifactPublicId: $action->artifactPublicId,
            sourceState: $action->sourceState,
            deliveryMode: PdfDeliveryMode::Original,
            actionMode: $action->actionMode,
            disabledReasons: [
                'view' => $action->viewDisabledReason,
                'sign' => $action->signDisabledReason,
                'verify' => $action->verifyDisabledReason,
                'download' => $action->downloadDisabledReason,
            ],
            attachments: $this->attachments($document, $actor),
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
    private function attachments(Document $document, User $actor): array
    {
        $attachments = [];

        if (filled($document->billing ?? null)) {
            $action = $this->actionResolver->resolve(
                $document,
                $actor,
                PdfDeliverySource::RESOURCE_BILLING,
            );
            $attachments[] = $this->attachment(
                document: $document,
                key: 'billing',
                label: 'Billing',
                action: $action,
            );
        }

        if (filled($document->spj_fungsional ?? null)) {
            $action = $this->actionResolver->resolve(
                $document,
                $actor,
                PdfDeliverySource::RESOURCE_SPJ_FUNCTIONAL,
            );
            $attachments[] = $this->attachment(
                document: $document,
                key: 'spj_fungsional',
                label: 'SPJ Fungsional',
                action: $action,
            );
        }

        return $attachments;
    }

    /** @return array<string, mixed> */
    private function attachment(
        Document $document,
        string $key,
        string $label,
        DocumentResourceActionData $action,
    ): array {
        $encryptedDocumentId = EncryptedId::encode((int) $document->getKey());

        return [
            'key' => $key,
            'label' => $label,
            'artifact_public_id' => $action->artifactPublicId,
            'source_state' => $action->sourceState->value,
            'delivery_mode' => PdfDeliveryMode::Original->value,
            'action_mode' => $action->actionMode->value,
            'capabilities' => [
                'view' => $action->canView,
                'sign' => $action->canSign,
                'verify' => $action->canVerify,
                'download_available_in_viewer' => $action->canDownload,
            ],
            'delivery' => $this->deliveryUrls(
                $encryptedDocumentId,
                $key,
                $action->canView,
                $action->canDownload,
            ),
            'actions' => $this->actions($action, $encryptedDocumentId),
            'disabled_reasons' => [
                'view' => $action->viewDisabledReason,
                'sign' => $action->signDisabledReason,
                'verify' => $action->verifyDisabledReason,
                'download' => $action->downloadDisabledReason,
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

    /** @return array<string, array<string, mixed>> */
    private function actions(
        DocumentResourceActionData $action,
        string $encryptedDocumentId,
    ): array {
        $delivery = $this->deliveryUrls(
            $encryptedDocumentId,
            $action->resourceKey,
            $action->canView,
            $action->canDownload,
        );
        $artifactPublicId = $action->canVerify ? $action->artifactPublicId : null;

        return [
            'view' => [
                'allowed' => $action->canView,
                'url' => $delivery['content_url'],
                'reason' => $action->viewDisabledReason,
            ],
            'download' => [
                'allowed' => $action->canDownload,
                'url' => $delivery['download_url'],
                'reason' => $action->downloadDisabledReason,
            ],
            'verify' => [
                'allowed' => $action->canVerify,
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
                'reason' => $action->verifyDisabledReason,
            ],
            'sign' => [
                'allowed' => $action->canSign,
                'mode' => $action->canSign
                    ? DocumentDetailActionMode::Canonical->value
                    : DocumentDetailActionMode::None->value,
                'step_public_id' => $action->stepPublicId,
                'reason' => $action->signDisabledReason,
            ],
        ];
    }
}
