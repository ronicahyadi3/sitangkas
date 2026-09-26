<?php

declare(strict_types=1);

namespace App\Data\Document;

use App\Enums\Document\DocumentDetailActionMode;
use App\Enums\Document\DocumentDetailSourceState;

final readonly class DocumentDetailContractData
{
    /**
     * @param  array{code: string, label: string, tone: string}  $status
     * @param  array{view: bool, sign: bool, verify: bool, download_available_in_viewer: bool}  $capabilities
     * @param  array{content_url: string|null, download_url: string|null}  $delivery
     * @param  array{view: array{code: string, message: string}|null, sign: array{code: string, message: string}|null, verify: array{code: string, message: string}|null, download: array{code: string, message: string}|null}  $disabledReasons
     * @param  list<array<string, mixed>>  $attachments
     */
    public function __construct(
        public string $documentId,
        public string $paymentType,
        public string $documentType,
        public array $status,
        public array $capabilities,
        public array $delivery,
        public ?string $stepPublicId,
        public ?string $artifactPublicId,
        public DocumentDetailSourceState $sourceState,
        public DocumentDetailActionMode $actionMode,
        public array $disabledReasons,
        public array $attachments,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract_version' => 1,
            'document_id' => $this->documentId,
            'payment_type' => $this->paymentType,
            'document_type' => $this->documentType,
            'status' => $this->status,
            'capabilities' => $this->capabilities,
            'delivery' => $this->delivery,
            'step_public_id' => $this->stepPublicId,
            'artifact_public_id' => $this->artifactPublicId,
            'source_state' => $this->sourceState->value,
            'action_mode' => $this->actionMode->value,
            'disabled_reasons' => $this->disabledReasons,
            'attachments' => $this->attachments,
        ];
    }
}
