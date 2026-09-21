<?php

declare(strict_types=1);

namespace App\Services\Esign\Provisioning;

use App\Data\Esign\DocumentSigningWorkflowDefinition;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\UserPosition;
use Illuminate\Support\Str;

final class DocumentSigningWorkflowDefinitionRegistry
{
    public function forDocument(
        Document $document,
        Document $rootDocument,
    ): ?DocumentSigningWorkflowDefinition {
        $paymentType = Str::upper(trim((string) $document->payment_type));
        $documentType = Str::upper(trim((string) $document->src_type));

        if (in_array($documentType, ['BMD', 'SPJ'], true)) {
            return null;
        }

        return match ($paymentType) {
            'UP' => $this->up($document, $documentType),
            'GU_SKPD' => $this->guSkpd($document, $documentType),
            'GU_UK' => $this->guUk($document, $documentType),
            'LS', 'LS_GAJI' => $this->ls($document, $rootDocument, $documentType),
            'TU' => $this->tu($document, $rootDocument, $documentType),
            'KKPD' => $this->kkpd($document, $documentType),
            default => null,
        };
    }

    private function up(Document $document, string $documentType): ?DocumentSigningWorkflowDefinition
    {
        return match ($documentType) {
            'SPP' => $this->definition('default', ['BP', 'PA']),
            'SPM', 'SPTJM', 'SP_PENGAJUAN' => $this->definition('default', ['PA']),
            'SP' => $this->definition('default', ['PPK_SKPD']),
            'SP2D' => $this->assignedBudDefinition($document),
            default => null,
        };
    }

    private function guSkpd(Document $document, string $documentType): ?DocumentSigningWorkflowDefinition
    {
        return match ($documentType) {
            'NPD' => $this->definition('default', ['PPTK', 'PA']),
            'TBP', 'SPP' => $this->definition('default', ['BP', 'PA']),
            'LPJ' => $this->definition('default', ['BP']),
            'SPM', 'SPTJM', 'SP_PENGAJUAN' => $this->definition('default', ['PA']),
            'SP' => $this->definition('default', ['PPK_SKPD']),
            'SP2D' => $this->assignedBudDefinition($document),
            default => null,
        };
    }

    private function guUk(Document $document, string $documentType): ?DocumentSigningWorkflowDefinition
    {
        return match ($documentType) {
            'NPD' => $this->definition('default', ['PPTK', 'KPA']),
            'TBP' => $this->definition('default', ['BPP', 'KPA']),
            'LPJ_BPP' => $this->definition('default', ['BPP']),
            'LPJ' => $this->definition('default', ['BP']),
            'SPP' => $this->definition('default', ['BP', 'PA']),
            'SPM', 'SPTJM', 'SP_PENGAJUAN' => $this->definition('default', ['PA']),
            'SP' => $this->definition('default', ['PPK_SKPD']),
            'SP2D' => $this->assignedBudDefinition($document),
            default => null,
        };
    }

    private function ls(
        Document $document,
        Document $rootDocument,
        string $documentType,
    ): ?DocumentSigningWorkflowDefinition {
        if ($documentType === 'SP2D') {
            return $this->assignedBudDefinition($document);
        }

        if ($documentType === 'SP') {
            return $this->definition('default', ['PPK_SKPD']);
        }

        $cashierRole = $this->cashierRole($document, $rootDocument);
        $headRole = $cashierRole === 'BPP' ? 'KPA' : 'PA';
        $variant = Str::lower($cashierRole);

        return match ($documentType) {
            'SPP' => $this->definition($variant, [$cashierRole, 'PPTK', $headRole]),
            'SPM', 'SPTJM', 'SP_PENGAJUAN' => $this->definition($variant, [$headRole]),
            default => null,
        };
    }

    private function tu(
        Document $document,
        Document $rootDocument,
        string $documentType,
    ): ?DocumentSigningWorkflowDefinition {
        if ($documentType === 'SP2D') {
            return $this->assignedBudDefinition($document);
        }

        if ($documentType === 'SP') {
            return $this->definition('default', ['PPK_SKPD']);
        }

        $cashierRole = $this->cashierRole($document, $rootDocument);
        $headRole = $cashierRole === 'BPP' ? 'KPA' : 'PA';
        $variant = Str::lower($cashierRole);

        return match ($documentType) {
            'PENGAJUAN' => $this->definition($variant, ['PPTK', $headRole, 'BUD']),
            'SPP' => $this->definition($variant, [$cashierRole, 'PPTK', $headRole]),
            'TBP', 'LPJ', 'STS' => $this->definition($variant, [$cashierRole, $headRole]),
            'SPM', 'SPTJM', 'SP_PENGAJUAN' => $this->definition($variant, [$headRole]),
            default => null,
        };
    }

    private function kkpd(Document $document, string $documentType): ?DocumentSigningWorkflowDefinition
    {
        $headRole = $this->headRoleForUnit($document);

        return match ($documentType) {
            'DPR' => $this->definition('default', ['PPTK']),
            'DPT' => $this->definition(Str::lower($headRole), [$headRole]),
            'NPD' => $this->definition(Str::lower($headRole), ['PPTK', $headRole]),
            'SPP' => $this->definition('default', ['BP', 'PA']),
            'SPM', 'SPTJM', 'SP_PENGAJUAN' => $this->definition('default', ['PA']),
            'SP' => $this->definition('default', ['PPK_SKPD']),
            'SP2D' => $this->assignedBudDefinition($document),
            default => null,
        };
    }

    private function assignedBudDefinition(Document $document): DocumentSigningWorkflowDefinition
    {
        $recipientRole = $this->roleCode($document->recipientPosition);

        if (! in_array($recipientRole, ['BUD', 'KUASA_BUD'], true)) {
            throw new EsignInvariantViolationException('sp2d_assigned_signer_invalid');
        }

        return $this->definition('assigned_'.Str::lower($recipientRole), [$recipientRole]);
    }

    private function cashierRole(Document $document, Document $rootDocument): string
    {
        foreach ([$document->uploadedByPosition, $rootDocument->uploadedByPosition] as $position) {
            $roleCode = $this->roleCode($position);

            if (in_array($roleCode, ['BP', 'BPP'], true)) {
                return $roleCode;
            }
        }

        return $this->headRoleForUnit($document) === 'KPA' ? 'BPP' : 'BP';
    }

    private function headRoleForUnit(Document $document): string
    {
        $document->loadMissing('unitKerja');

        return filled($document->unitKerja?->getAttribute('skpd_id')) ? 'KPA' : 'PA';
    }

    private function roleCode(?UserPosition $position): ?string
    {
        if (! $position instanceof UserPosition) {
            return null;
        }

        $position->loadMissing('jabatan');
        $roleCode = $position->jabatan?->kode;

        return is_string($roleCode) && $roleCode !== ''
            ? Str::of($roleCode)->trim()->upper()->replace('-', '_')->toString()
            : null;
    }

    /** @param non-empty-list<string> $roleCodes */
    private function definition(string $variant, array $roleCodes): DocumentSigningWorkflowDefinition
    {
        return new DocumentSigningWorkflowDefinition(
            variant: $variant,
            roleCodes: $roleCodes,
        );
    }
}
