<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

final class PdfDeliveryAuthorizationService
{
    public function __construct(
        private readonly ActivePositionService $activePosition,
        private readonly PositionIdentityResolver $positionIdentityResolver,
        private readonly DocumentOrganizationScope $organizationScope,
        private readonly EsignAuthorizationService $esignAuthorization,
    ) {}

    public function authorize(
        User $user,
        ResolvedPdfDeliverySource $source,
        PdfDeliveryPurpose $purpose,
    ): Response {
        if (! $user->isActive() || $user->isLocked()) {
            return $this->notFound();
        }

        $realPosition = $this->activePosition->real();
        $effectivePosition = $this->activePosition->get();
        if (! $realPosition instanceof UserPosition
            || ! $effectivePosition instanceof UserPosition
            || (int) $realPosition->user_id !== (int) $user->getKey()
            || ! $realPosition->isAvailableForSelection()
            || $source->documentYear !== $this->activePosition->selectedYear()) {
            return $this->notFound();
        }

        $isActingContext = $effectivePosition->getAttribute('is_acting_context') === true;
        if (! $isActingContext
            && $effectivePosition->getAttribute('pdf_watermark_required') === true) {
            return $this->notFound();
        }

        if ($source->authorizationSubject instanceof DocumentArtifact) {
            return $purpose === PdfDeliveryPurpose::Download
                ? $this->esignAuthorization->downloadArtifact(
                    $user,
                    $source->authorizationSubject,
                )
                : $this->esignAuthorization->viewArtifact(
                    $user,
                    $source->authorizationSubject,
                );
        }

        if (! $source->authorizationSubject instanceof Document
            || (int) $source->authorizationSubject->getKey() !== $source->documentId) {
            return $this->notFound();
        }

        return $this->authorizeLegacyDocument(
            $source->authorizationSubject,
            $realPosition,
            $effectivePosition,
            $purpose,
        );
    }

    private function authorizeLegacyDocument(
        Document $document,
        UserPosition $realPosition,
        UserPosition $effectivePosition,
        PdfDeliveryPurpose $purpose,
    ): Response {
        $roleCode = $this->roleCode($effectivePosition);
        if ($purpose === PdfDeliveryPurpose::Download && $roleCode === 'AUDITOR') {
            return $this->notFound();
        }

        if ($document->trashed() && ! in_array($roleCode, ['ADMIN_SUPER', 'AUDITOR'], true)) {
            return $this->notFound();
        }

        if ($roleCode === 'ADMIN_SUPER'
            || $this->matchesUploader($document, $realPosition)
            || $this->organizationScope->containsUnit(
                $effectivePosition,
                $document->id_unit_kerja,
            )
            || $this->matchesLegacyAssignment($document, $effectivePosition)) {
            return Response::allow();
        }

        return $this->notFound();
    }

    private function matchesUploader(
        Document $document,
        UserPosition $realPosition,
    ): bool {
        $uploadedByPositionId = (int) ($document->uploaded_by ?? 0);
        if ($uploadedByPositionId < 1) {
            return false;
        }

        try {
            return $this->positionIdentityResolver->contains(
                $realPosition,
                $uploadedByPositionId,
            );
        } catch (\LogicException|\InvalidArgumentException) {
            return false;
        }
    }

    private function matchesLegacyAssignment(
        Document $document,
        UserPosition $effectivePosition,
    ): bool {
        $jabatanId = (string) $effectivePosition->jabatan_id;
        $assignedJabatanIds = $this->legacyIdList($document->assigned_to);

        if (in_array($jabatanId, $assignedJabatanIds, true)) {
            return true;
        }

        $recipientPositionId = (int) ($document->users_to ?? 0);
        if ($recipientPositionId < 1) {
            return false;
        }

        try {
            return $this->positionIdentityResolver->contains(
                $effectivePosition,
                $recipientPositionId,
            );
        } catch (\LogicException|\InvalidArgumentException) {
            return false;
        }
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

    private function roleCode(UserPosition $position): string
    {
        $position->loadMissing('jabatan');

        return Str::of((string) $position->jabatan?->kode)
            ->trim()
            ->upper()
            ->replace('-', '_')
            ->toString();
    }

    private function notFound(): Response
    {
        return Response::denyAsNotFound(
            'Dokumen tidak ditemukan atau tidak dapat diakses.',
            'pdf_delivery_not_accessible',
        );
    }
}
