<?php

namespace App\Services\User;

use App\Models\UserPosition;
use App\Models\UserPositionDocument;
use Illuminate\Http\UploadedFile;

class UserPositionDocumentWriter
{
    /**
     * @param  array<string, mixed>  $positionData
     * @param  array{document_type: string, document_number: mixed, document_date: mixed, issued_by: mixed}  $documentData
     */
    public function storePrimarySkDocument(
        UserPosition $position,
        UploadedFile $uploadedFile,
        string $storedFilePath,
        array $positionData,
        array $documentData
    ): UserPositionDocument {
        $position->documents()
            ->primary()
            ->update([
                'is_primary' => false,
                'updated_by_user_id' => auth()->id(),
            ]);

        $documentType = (string) $documentData['document_type'];
        $nextVersion = ((int) $position->documents()
            ->where('document_type', $documentType)
            ->max('version')) + 1;
        $realPath = $uploadedFile->getRealPath();

        return $position->documents()->create([
            'document_type' => $documentType,
            'document_number' => $documentData['document_number'],
            'document_date' => $documentData['document_date'],
            'issued_by' => $documentData['issued_by'],
            'effective_from' => $positionData['started_at'] ?? null,
            'effective_until' => $positionData['ended_at'] ?? null,
            'storage_disk' => 'public',
            'file_path' => $storedFilePath,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'stored_name' => basename($storedFilePath),
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $uploadedFile->extension(),
            'size_bytes' => $uploadedFile->getSize(),
            'file_sha256' => $realPath ? hash_file('sha256', $realPath) : null,
            'version' => $nextVersion,
            'is_primary' => true,
            'verification_status' => UserPositionDocument::VERIFICATION_DRAFT,
            'uploaded_at' => now(),
            'uploaded_by_user_id' => auth()->id(),
            'created_by_user_id' => auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * @param  array{document_type: string, document_number: mixed, document_date: mixed, issued_by: mixed}  $documentData
     */
    public function updatePrimarySkDocumentMetadata(UserPosition $position, array $documentData): void
    {
        $primaryDocument = $position->primaryDocument()->first();

        if (! $primaryDocument instanceof UserPositionDocument) {
            return;
        }

        $primaryDocument->fill([
            'document_type' => $documentData['document_type'],
            'document_number' => $documentData['document_number'],
            'document_date' => $documentData['document_date'],
            'issued_by' => $documentData['issued_by'],
            'effective_from' => $position->started_at,
            'effective_until' => $position->ended_at,
            'updated_by_user_id' => auth()->id(),
        ]);
        $primaryDocument->save();
    }
}
