<?php

namespace App\Actions\UserManagement;

use App\Http\Requests\User\UserPositionStoreRequest;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Models\UserPositionDocument;
use App\Services\User\PositionSwitcher;
use App\Services\User\UserManagementAccessService;
use App\Services\User\UserManagementAuditLogger;
use App\Services\User\UserPositionDocumentWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateManagedUserPosition
{
    public function __construct(
        private PositionSwitcher $positionSwitcher,
        private UserManagementAccessService $userManagementAccessService,
        private UserManagementAuditLogger $userManagementAuditLogger,
        private UserPositionDocumentWriter $userPositionDocumentWriter
    ) {}

    public function handle(
        UserPositionStoreRequest $request,
        User $user,
        UserPosition $position,
        ?UserPosition $actor
    ): UserPosition {
        $data = $request->validated();
        $documentData = $this->documentDataFrom($data);
        $positionData = $this->positionDataFrom($data);
        $storedFilePath = null;

        if (
            ! $this->userManagementAccessService->isJabatanManageable((int) $positionData['jabatan_id'], $actor) ||
            ! $this->userManagementAccessService->isPositionWithinScope(
                (int) $positionData['jabatan_id'],
                $positionData['instansi_id'] ?? null,
                $positionData['unit_kerja_id'] ?? null,
                $actor
            )
        ) {
            throw new AuthorizationException('Jabatan atau scope posisi yang dipilih tidak termasuk kewenangan jabatan aktif Anda.');
        }

        try {
            $beforeState = $this->userManagementAuditLogger->positionSnapshot($position);

            Log::channel('module_users')->info('User position update request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'jabatan_id' => $positionData['jabatan_id'] ?? null,
                'instansi_id' => $positionData['instansi_id'] ?? null,
                'unit_kerja_id' => $positionData['unit_kerja_id'] ?? null,
                'activate_after_save' => $request->boolean('is_active'),
                'document_type' => $documentData['document_type'],
                'document_number' => $documentData['document_number'],
            ]);

            $uploadedSkFile = $request->file('file_sk');

            if ($uploadedSkFile instanceof UploadedFile) {
                $storedFilePath = $uploadedSkFile->store('sk', 'public');
            }

            $activateAfterSave = $request->boolean('is_active');

            DB::transaction(function () use (
                $user,
                $position,
                $positionData,
                $documentData,
                $activateAfterSave,
                $uploadedSkFile,
                $storedFilePath
            ): void {
                $position->fill([
                    'jabatan_id' => $positionData['jabatan_id'],
                    'instansi_id' => $positionData['instansi_id'] ?? null,
                    'unit_kerja_id' => $positionData['unit_kerja_id'] ?? null,
                    'started_at' => $positionData['started_at'],
                    'ended_at' => $positionData['ended_at'] ?? null,
                    'notes' => $positionData['notes'] ?? null,
                ]);
                $position->save();

                if ($uploadedSkFile instanceof UploadedFile && $storedFilePath) {
                    $this->userPositionDocumentWriter->storePrimarySkDocument($position, $uploadedSkFile, $storedFilePath, $positionData, $documentData);
                } else {
                    $this->userPositionDocumentWriter->updatePrimarySkDocumentMetadata($position, $documentData);
                }

                if ($activateAfterSave) {
                    $this->positionSwitcher->setActive($user, $position->id);
                }
            });

            Log::channel('module_users')->info('User position updated', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'activate_after_save' => $activateAfterSave,
            ]);

            $position->refresh();
            $position->loadMissing('primaryDocument');

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_POSITION_UPDATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'message' => 'Posisi user diperbarui melalui management users.',
                'before_state' => $beforeState,
                'after_state' => $this->userManagementAuditLogger->positionSnapshot($position),
                'metadata' => [
                    'document_uploaded' => $uploadedSkFile instanceof UploadedFile,
                    'activate_after_save' => $activateAfterSave,
                ],
                'http_status' => 200,
            ], $request);

            return $position;
        } catch (Throwable $e) {
            if ($storedFilePath && Storage::disk('public')->exists($storedFilePath)) {
                Storage::disk('public')->delete($storedFilePath);
            }

            Log::channel('module_users')->error('User position update failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_POSITION_UPDATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'message' => 'Gagal memperbarui posisi user melalui management users.',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function positionDataFrom(array $data): array
    {
        return collect($data)
            ->only([
                'jabatan_id',
                'instansi_id',
                'unit_kerja_id',
                'started_at',
                'ended_at',
                'notes',
                'is_active',
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{document_type: string, document_number: mixed, document_date: mixed, issued_by: mixed}
     */
    private function documentDataFrom(array $data): array
    {
        return [
            'document_type' => $data['document_type'] ?? UserPositionDocument::TYPE_APPOINTMENT_SK,
            'document_number' => $data['document_number'] ?? null,
            'document_date' => $data['document_date'] ?? null,
            'issued_by' => $data['issued_by'] ?? null,
        ];
    }
}
