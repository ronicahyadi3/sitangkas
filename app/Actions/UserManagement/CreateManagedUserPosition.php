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

class CreateManagedUserPosition
{
    public function __construct(
        private PositionSwitcher $positionSwitcher,
        private UserManagementAccessService $userManagementAccessService,
        private UserManagementAuditLogger $userManagementAuditLogger,
        private UserPositionDocumentWriter $userPositionDocumentWriter
    ) {}

    public function handle(UserPositionStoreRequest $request, User $user, ?UserPosition $actor): UserPosition
    {
        $data = $request->validated();
        $documentData = $this->documentDataFrom($data);
        $positionData = $this->positionDataFrom($data);
        $storedFilePath = null;
        $isInitialPosition = ! $user->userPositions()->exists();

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
            Log::channel('module_users')->info('User position create request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'jabatan_id' => $positionData['jabatan_id'] ?? null,
                'instansi_id' => $positionData['instansi_id'] ?? null,
                'unit_kerja_id' => $positionData['unit_kerja_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'document_type' => $documentData['document_type'],
                'document_number' => $documentData['document_number'],
                'is_initial_position' => $isInitialPosition,
            ]);

            $uploadedSkFile = $request->file('file_sk');

            if ($uploadedSkFile instanceof UploadedFile) {
                $storedFilePath = $uploadedSkFile->store('sk', 'public');
            }

            $positionData['user_id'] = $user->id;

            if (empty($positionData['started_at'])) {
                $positionData['started_at'] = now()->toDateString();
            }

            $makeActive = $request->boolean('is_active');

            $position = DB::transaction(function () use (
                $positionData,
                $documentData,
                $user,
                $makeActive,
                $uploadedSkFile,
                $storedFilePath
            ): UserPosition {
                if ($makeActive) {
                    UserPosition::where('user_id', $user->id)
                        ->where('jabatan_id', $positionData['jabatan_id'])
                        ->where('instansi_id', $positionData['instansi_id'])
                        ->where('unit_kerja_id', $positionData['unit_kerja_id'])
                        ->where('is_active', 1)
                        ->update([
                            'is_active' => 0,
                            'updated_by_user_id' => auth()->id(),
                        ]);
                }

                $position = UserPosition::create($positionData);

                if ($uploadedSkFile instanceof UploadedFile && $storedFilePath) {
                    $this->userPositionDocumentWriter->storePrimarySkDocument($position, $uploadedSkFile, $storedFilePath, $positionData, $documentData);
                }

                if ($makeActive) {
                    $position->update(['is_active' => 1]);
                    $this->positionSwitcher->setActive($user, $position->id);
                }

                return $position;
            });

            Log::channel('module_users')->info('User position created', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'is_active' => (bool) $position->is_active,
            ]);

            $position->loadMissing('primaryDocument');

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_POSITION_CREATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'message' => 'Posisi user ditambahkan melalui management users.',
                'after_state' => $this->userManagementAuditLogger->positionSnapshot($position),
                'metadata' => [
                    'document_uploaded' => $uploadedSkFile instanceof UploadedFile,
                    'activate_after_save' => $makeActive,
                    'is_initial_position' => $isInitialPosition,
                ],
                'http_status' => 201,
            ], $request);

            return $position;
        } catch (Throwable $e) {
            if ($storedFilePath && Storage::disk('public')->exists($storedFilePath)) {
                Storage::disk('public')->delete($storedFilePath);
            }

            Log::channel('module_users')->error('User position create failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_POSITION_CREATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'message' => 'Gagal menambahkan posisi user melalui management users.',
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
