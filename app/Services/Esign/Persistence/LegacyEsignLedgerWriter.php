<?php

namespace App\Services\Esign\Persistence;

use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Models\AfterSign;
use App\Models\BeforeSign;
use App\Models\Document;
use App\Models\DocumentHistory;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignAttemptLegacyLink;
use App\Models\UserPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyEsignLedgerWriter
{
    public function writeBefore(
        EsignAttempt $attempt,
        DocumentArtifact $artifact,
        string $maskedNik,
        string $md5,
    ): void {
        DB::transaction(function () use ($attempt, $artifact, $maskedNik, $md5): void {
            if ($this->linkExists($attempt, 'before_signs')) {
                return;
            }

            $document = $artifact->document()->first();
            $srcName = $document?->src_name ?: $artifact->stored_name;
            $beforeSign = BeforeSign::query()->create([
                'nik' => Str::limit($maskedNik, 100, ''),
                'id_data' => $attempt->document_id,
                'src_name' => Str::limit((string) $srcName, 150, ''),
                'md5' => $md5,
                'size' => (string) $artifact->size_bytes,
            ]);

            $this->createLink(
                attempt: $attempt,
                artifact: $artifact,
                legacyTable: 'before_signs',
                legacyId: (int) $beforeSign->getKey(),
                srcName: $srcName,
                checksum: $artifact->file_sha256,
            );
        }, attempts: 3);
    }

    public function writeAfter(
        EsignAttempt $attempt,
        ?DocumentArtifact $artifact,
        string $maskedNik,
        ?string $md5,
        bool $succeeded,
        string $safeResponse,
    ): void {
        DB::transaction(function () use (
            $attempt,
            $artifact,
            $maskedNik,
            $md5,
            $succeeded,
            $safeResponse,
        ): void {
            if ($this->linkExists($attempt, 'after_signs')) {
                return;
            }

            $sourceArtifact = $attempt->sourceArtifact()->first();
            $document = $attempt->document()->first();
            $srcName = $document?->src_name ?: $artifact?->stored_name ?: $sourceArtifact?->stored_name;
            $afterSign = AfterSign::query()->create([
                'nik' => Str::limit($maskedNik, 100, ''),
                'src_name' => Str::limit((string) $srcName, 150, ''),
                'md5' => $md5,
                'response' => Str::limit($safeResponse, 255, ''),
                'id_data' => $attempt->document_id,
                'status' => $succeeded,
            ]);

            $this->createLink(
                attempt: $attempt,
                artifact: $artifact ?? $sourceArtifact,
                legacyTable: 'after_signs',
                legacyId: (int) $afterSign->getKey(),
                srcName: $srcName,
                checksum: $artifact?->file_sha256,
            );
        }, attempts: 3);
    }

    public function writeSuccessfulDocumentHistory(
        EsignAttempt $attempt,
        DocumentArtifact $artifact,
        string $md5,
    ): void {
        DB::transaction(function () use ($attempt, $artifact, $md5): void {
            if ($this->linkExists($attempt, 'document_process')) {
                return;
            }

            $document = Document::withTrashed()
                ->lockForUpdate()
                ->findOrFail($attempt->document_id);
            $position = UserPosition::withTrashed()->findOrFail($attempt->signer_user_position_id);
            $srcName = $document->src_name ?: $artifact->stored_name;
            $history = DocumentHistory::query()->create([
                'id_user' => $position->getKey(),
                'id_unit_kerja' => $position->unit_kerja_id ?? $document->id_unit_kerja,
                'id_jabatan' => $position->jabatan_id,
                'id_dokumen' => $document->getKey(),
                'src_name' => Str::substr((string) $srcName, 0, 55),
                'md5' => $md5,
                'assigned_to' => Str::substr((string) ($document->assigned_to ?? ''), 0, 20) ?: null,
                'action' => DocumentHistory::ACTION_TTE,
            ]);

            $workflow = $attempt->step()->first()?->workflow()->first();

            if ($workflow?->status === DocumentSigningWorkflowStatus::Completed
                && $document->signed_at === null) {
                $document->signed_at = now();
                $document->save();
            }

            $this->createLink(
                attempt: $attempt,
                artifact: $artifact,
                legacyTable: 'document_process',
                legacyId: (int) $history->getKey(),
                srcName: $srcName,
                checksum: $artifact->file_sha256,
                legacyDocumentProcessId: (int) $history->getKey(),
            );
        }, attempts: 3);
    }

    private function linkExists(EsignAttempt $attempt, string $legacyTable): bool
    {
        return EsignAttemptLegacyLink::query()
            ->where('esign_attempt_id', $attempt->getKey())
            ->where('legacy_table', $legacyTable)
            ->lockForUpdate()
            ->exists();
    }

    private function createLink(
        EsignAttempt $attempt,
        ?DocumentArtifact $artifact,
        string $legacyTable,
        int $legacyId,
        ?string $srcName,
        ?string $checksum,
        ?int $legacyDocumentProcessId = null,
    ): void {
        $step = $attempt->step()->first();

        EsignAttemptLegacyLink::query()->create([
            'esign_attempt_id' => $attempt->getKey(),
            'document_artifact_id' => $artifact?->getKey(),
            'document_signing_workflow_id' => $step?->document_signing_workflow_id,
            'document_signing_step_id' => $attempt->document_signing_step_id,
            'legacy_table' => $legacyTable,
            'legacy_id' => $legacyId,
            'legacy_document_id' => $attempt->document_id,
            'legacy_document_process_id' => $legacyDocumentProcessId,
            'legacy_src_name' => $srcName === null ? null : Str::limit($srcName, 150, ''),
            'legacy_checksum' => $checksum,
            'mapping_status' => 'mapped',
            'metadata' => ['projection' => 'canonical_dual_write'],
            'mapped_at' => now(),
        ]);
    }
}
