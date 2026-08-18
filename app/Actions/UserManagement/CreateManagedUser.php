<?php

namespace App\Actions\UserManagement;

use App\Http\Requests\User\UserStoreRequest;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateManagedUser
{
    public function __construct(
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(UserStoreRequest $request, ?UserPosition $actor): User
    {
        try {
            $userData = collect($request->validated())
                ->only([
                    'nik',
                    'nip',
                    'nama',
                    'email',
                    'account_type',
                    'status',
                    'status_reason',
                    'tahun_aktif',
                    'password',
                ])
                ->all();
            $userData = $this->withStatusAuditAttributes($userData);

            Log::channel('module_users')->info('User create request', [
                'actor_id' => auth()->id(),
                'nik' => $request->input('nik'),
                'nama' => $request->input('nama'),
                'email' => $request->input('email'),
                'account_type' => $request->input('account_type'),
                'status' => $request->input('status'),
                'tahun_aktif' => $request->input('tahun_aktif'),
            ]);

            $user = DB::transaction(function () use ($userData): User {
                $user = User::create($userData);
                $user->forceFill([
                    'password_changed_at' => null,
                    'must_change_password' => true,
                ])->save();

                return $user;
            });

            Log::channel('module_users')->info('User created', [
                'actor_id' => auth()->id(),
                'user_id' => $user->id,
                'nama' => $user->nama,
            ]);

            $afterState = $this->userManagementAuditLogger->userSnapshot($user);

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_USER_CREATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'message' => 'User dibuat melalui management users.',
                'after_state' => $afterState,
                'changed_fields' => array_keys($afterState),
                'metadata' => [
                    'prompt_position_setup' => true,
                    'password_was_initialized' => true,
                    'must_change_password' => true,
                ],
                'http_status' => 201,
            ], $request);

            return $user;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('User create failed', [
                'actor_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_USER_CREATED, [
                'actor_position' => $actor,
                'message' => 'Gagal membuat user melalui management users.',
                'metadata' => [
                    'nik' => $request->input('nik'),
                    'nama' => $request->input('nama'),
                    'email' => $request->input('email'),
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
    private function withStatusAuditAttributes(array $data): array
    {
        $newStatus = (string) ($data['status'] ?? User::STATUS_ACTIVE);
        $statusChanged = $newStatus !== User::STATUS_ACTIVE;

        if ($statusChanged) {
            $data['status_changed_at'] = now();
            $data['status_changed_by_user_id'] = auth()->id();
        }

        if ($newStatus === User::STATUS_LOCKED && $statusChanged) {
            $data['locked_at'] = now();
            $data['lock_reason'] = $data['status_reason'] ?? null;
        }

        return $data;
    }
}
