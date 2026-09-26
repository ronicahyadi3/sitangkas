<?php

declare(strict_types=1);

namespace App\Data\Document;

use App\Enums\Document\DocumentDetailActionMode;
use App\Enums\Document\DocumentDetailSourceState;

final readonly class DocumentResourceActionData
{
    /**
     * @param  array{code: string, message: string}|null  $viewDisabledReason
     * @param  array{code: string, message: string}|null  $downloadDisabledReason
     * @param  array{code: string, message: string}|null  $verifyDisabledReason
     * @param  array{code: string, message: string}|null  $signDisabledReason
     */
    public function __construct(
        public string $resourceKey,
        public ?ResolvedPdfDeliverySource $source,
        public DocumentDetailSourceState $sourceState,
        public DocumentDetailActionMode $actionMode,
        public bool $sourceResolutionFailed,
        public bool $canView,
        public bool $canDownload,
        public bool $canVerify,
        public bool $canSign,
        public ?string $stepPublicId,
        public ?string $artifactPublicId,
        public ?array $viewDisabledReason,
        public ?array $downloadDisabledReason,
        public ?array $verifyDisabledReason,
        public ?array $signDisabledReason,
    ) {}
}
