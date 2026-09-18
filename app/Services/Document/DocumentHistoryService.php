<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocumentHistory;
use App\Models\UserPosition;
use App\Services\User\ActivePositionService;
use Illuminate\Support\Str;
use RuntimeException;

final class DocumentHistoryService
{
    public function __construct(private readonly ActivePositionService $activePosition) {}

    public function upload(int $documentId, ?string $sourceName, ?int $unitKerjaId = null): DocumentHistory
    {
        return $this->record(DocumentHistory::ACTION_UPLOAD, $documentId, $sourceName, $unitKerjaId);
    }

    public function edited(int $documentId, ?string $sourceName, ?int $unitKerjaId = null): DocumentHistory
    {
        return $this->record(DocumentHistory::ACTION_EDITED, $documentId, $sourceName, $unitKerjaId);
    }

    public function submit(int $documentId, ?string $sourceName, ?int $unitKerjaId = null): DocumentHistory
    {
        return $this->record(DocumentHistory::ACTION_SUBMIT, $documentId, $sourceName, $unitKerjaId);
    }

    public function verify(int $documentId, ?string $sourceName, ?int $unitKerjaId = null): DocumentHistory
    {
        return $this->record(DocumentHistory::ACTION_VERIFY, $documentId, $sourceName, $unitKerjaId);
    }

    public function reject(int $documentId, ?string $sourceName, ?int $unitKerjaId = null): DocumentHistory
    {
        return $this->record(DocumentHistory::ACTION_REJECT, $documentId, $sourceName, $unitKerjaId);
    }

    public function delete(int $documentId, ?string $sourceName, ?int $unitKerjaId = null): DocumentHistory
    {
        return $this->record(DocumentHistory::ACTION_DELETE, $documentId, $sourceName, $unitKerjaId);
    }

    private function record(
        string $action,
        int $documentId,
        ?string $sourceName,
        ?int $unitKerjaId,
    ): DocumentHistory {
        $actor = $this->activePosition->get();

        if (! $actor instanceof UserPosition) {
            throw new RuntimeException('Posisi aktif tidak tersedia untuk mencatat histori dokumen.');
        }

        $document = Document::withTrashed()->whereKey($documentId)->first();
        $resolvedSourceName = $sourceName ?: (string) ($document?->src_name ?? '');

        return DocumentHistory::query()->create([
            'id_user' => $actor->getKey(),
            'id_unit_kerja' => $unitKerjaId ?? $document?->id_unit_kerja ?? $actor->unit_kerja_id,
            'id_jabatan' => $actor->jabatan_id,
            'id_dokumen' => $documentId,
            'src_name' => Str::substr($resolvedSourceName, 0, 55),
            'md5' => null,
            'assigned_to' => Str::substr((string) ($document?->assigned_to ?? ''), 0, 20) ?: null,
            'action' => $action,
        ]);
    }
}
