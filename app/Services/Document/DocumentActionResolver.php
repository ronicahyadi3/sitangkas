<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\DocumentResourceActionData;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailActionMode;
use App\Enums\Document\DocumentDetailSourceState;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\User\ActivePositionService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DocumentActionResolver
{
    public function __construct(
        private readonly PdfDeliverySource $pdfDeliverySource,
        private readonly PdfDeliveryAuthorizationService $deliveryAuthorization,
        private readonly LegacyDocumentSigningRuleRegistry $legacySigningRules,
        private readonly ActivePositionService $activePosition,
        private readonly CurrentUserContext $currentUserContext,
        private readonly ConfigRepository $config,
        private readonly Request $request,
    ) {}

    public function resolve(
        Document $document,
        User $actor,
        string $resourceKey = PdfDeliverySource::RESOURCE_DOCUMENT,
        ?string $stepPublicId = null,
    ): DocumentResourceActionData {
        [$source, $sourceResolutionFailed] = $this->resolveSource($document, $resourceKey);
        $sourceState = $source?->sourceState ?? DocumentDetailSourceState::Unavailable;
        $actionMode = $this->actionMode($sourceState);
        $resolvedStepPublicId = $this->uuidOrNull($stepPublicId);

        if (! $source instanceof ResolvedPdfDeliverySource) {
            return $this->unavailable(
                $resourceKey,
                $sourceState,
                $actionMode,
                $sourceResolutionFailed,
            );
        }

        $viewAuthorization = $this->deliveryAuthorization->authorize(
            $actor,
            $source,
            PdfDeliveryPurpose::View,
        );
        $canView = $viewAuthorization->allowed();
        $downloadAuthorization = $canView
            ? $this->deliveryAuthorization->authorize(
                $actor,
                $source,
                PdfDeliveryPurpose::Download,
            )
            : null;
        $canDownload = $downloadAuthorization?->allowed() === true;
        $canVerify = $canView
            && $sourceState === DocumentDetailSourceState::Canonical
            && $source->artifactPublicId !== null
            && $this->config->get('esign.frontend.enabled') === true;
        $canSign = $this->canSign(
            $document,
            $actor,
            $resourceKey,
            $sourceState,
            $canView,
            $resolvedStepPublicId,
        );

        return new DocumentResourceActionData(
            resourceKey: $resourceKey,
            source: $source,
            sourceState: $sourceState,
            actionMode: $actionMode,
            sourceResolutionFailed: $sourceResolutionFailed,
            canView: $canView,
            canDownload: $canDownload,
            canVerify: $canVerify,
            canSign: $canSign,
            stepPublicId: $canSign && $actionMode === DocumentDetailActionMode::Canonical
                ? $resolvedStepPublicId
                : null,
            artifactPublicId: $source->artifactPublicId,
            viewDisabledReason: $canView ? null : $this->reason(
                'document_not_accessible',
                'Dokumen tidak tersedia untuk posisi aktif.',
            ),
            downloadDisabledReason: $canDownload ? null : $this->downloadDisabledReason($canView),
            verifyDisabledReason: $canVerify ? null : $this->verificationDisabledReason(
                $sourceState,
                $canView,
            ),
            signDisabledReason: $canSign ? null : $this->signDisabledReason(
                $resourceKey,
                $sourceState,
                $canView,
                $resolvedStepPublicId,
            ),
        );
    }

    private function canSign(
        Document $document,
        User $actor,
        string $resourceKey,
        DocumentDetailSourceState $sourceState,
        bool $canView,
        ?string $stepPublicId,
    ): bool {
        if (! $canView
            || $resourceKey !== PdfDeliverySource::RESOURCE_DOCUMENT
            || $this->currentUserContext->effectiveContextIsActing($this->request)
            || ! $actor->isActive()
            || $actor->isLocked()) {
            return false;
        }

        if ($sourceState === DocumentDetailSourceState::Canonical) {
            return $stepPublicId !== null
                && $this->config->get('esign.frontend.enabled') === true
                && $this->config->get('esign.processing.multi_operation_enabled') === true;
        }

        if (! in_array($sourceState, [
            DocumentDetailSourceState::LegacyPrivatePending,
            DocumentDetailSourceState::LegacyPublicPending,
        ], true)) {
            return false;
        }

        $effectivePosition = $this->activePosition->get();
        if (! $effectivePosition instanceof UserPosition
            || ! in_array(
                (int) $effectivePosition->jabatan_id,
                $this->legacySigningRules->signerJabatanIds($document),
                true,
            )) {
            return false;
        }

        $positionId = (string) $effectivePosition->jabatan_id;
        $assignedTo = $this->legacyIdList($document->assigned_to);
        $submittedBy = $this->legacyIdList($document->submit);
        $signedBy = $this->legacyIdList($document->status);
        $hasLegacyStatus = $document->status !== null;

        return (! in_array($positionId, $submittedBy, true)
                && ! in_array($positionId, $signedBy, true)
                && in_array($positionId, $assignedTo, true))
            || ! $hasLegacyStatus;
    }

    private function unavailable(
        string $resourceKey,
        DocumentDetailSourceState $sourceState,
        DocumentDetailActionMode $actionMode,
        bool $sourceResolutionFailed,
    ): DocumentResourceActionData {
        $sourceReason = $this->reason(
            $sourceResolutionFailed
                ? 'document_source_invalid'
                : 'document_source_unavailable',
            $sourceResolutionFailed
                ? 'Sumber dokumen memerlukan pemeriksaan administrator.'
                : 'Sumber dokumen belum tersedia.',
        );

        return new DocumentResourceActionData(
            resourceKey: $resourceKey,
            source: null,
            sourceState: $sourceState,
            actionMode: $actionMode,
            sourceResolutionFailed: $sourceResolutionFailed,
            canView: false,
            canDownload: false,
            canVerify: false,
            canSign: false,
            stepPublicId: null,
            artifactPublicId: null,
            viewDisabledReason: $sourceReason,
            downloadDisabledReason: $sourceReason,
            verifyDisabledReason: $sourceReason,
            signDisabledReason: $sourceReason,
        );
    }

    private function signDisabledReason(
        string $resourceKey,
        DocumentDetailSourceState $sourceState,
        bool $canView,
        ?string $stepPublicId,
    ): array {
        if (! $canView) {
            return $this->reason(
                'document_not_accessible',
                'Dokumen tidak tersedia untuk posisi aktif.',
            );
        }

        if ($resourceKey !== PdfDeliverySource::RESOURCE_DOCUMENT) {
            return $this->reason(
                'document_attachment_not_signable',
                'Lampiran ini tidak tersedia untuk proses TTE.',
            );
        }

        if ($this->currentUserContext->effectiveContextIsActing($this->request)) {
            return $this->reason(
                'acting_context_cannot_sign',
                'TTE tidak dapat dilakukan dari konteks acting Admin Super.',
            );
        }

        if ($sourceState === DocumentDetailSourceState::Canonical) {
            return $this->reason(
                $stepPublicId === null
                    ? 'document_step_not_signable'
                    : 'esign_frontend_disabled',
                $stepPublicId === null
                    ? 'Dokumen belum berada pada langkah TTE aktif pengguna ini.'
                    : 'Fitur TTE belum diaktifkan.',
            );
        }

        return $this->reason(
            'legacy_sign_not_available',
            'TTE legacy tidak tersedia untuk posisi aktif atau keadaan dokumen ini.',
        );
    }

    private function verificationDisabledReason(
        DocumentDetailSourceState $sourceState,
        bool $canView,
    ): array {
        if (! $canView) {
            return $this->reason(
                'document_not_accessible',
                'Dokumen tidak tersedia untuk posisi aktif.',
            );
        }

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

    private function downloadDisabledReason(bool $canView): array
    {
        return $canView
            ? $this->reason(
                'document_download_not_authorized',
                'Posisi aktif tidak diizinkan mengunduh dokumen.',
            )
            : $this->reason(
                'document_not_accessible',
                'Dokumen tidak tersedia untuk posisi aktif.',
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

    /** @return list<string> */
    private function legacyIdList(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $id): bool => ctype_digit($id),
        ));
    }

    private function actionMode(DocumentDetailSourceState $sourceState): DocumentDetailActionMode
    {
        return match ($sourceState) {
            DocumentDetailSourceState::Canonical => DocumentDetailActionMode::Canonical,
            DocumentDetailSourceState::LegacyPrivatePending,
            DocumentDetailSourceState::LegacyPublicPending => DocumentDetailActionMode::LegacyTransition,
            DocumentDetailSourceState::Unavailable => DocumentDetailActionMode::None,
        };
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    /** @return array{0: ResolvedPdfDeliverySource|null, 1: bool} */
    private function resolveSource(Document $document, string $resourceKey): array
    {
        try {
            return [$this->pdfDeliverySource->resolve($document, $resourceKey), false];
        } catch (EsignInvariantViolationException|InvalidArgumentException $exception) {
            Log::channel('module_document_data')->warning('Document action source invariant failed', [
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
