<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Models\UserPositionDocument;
use App\Models\UserPositionYearPermission;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\RequestNetworkContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;
use Throwable;

class UserManagementAuditLogger
{
    public function __construct(
        private RequestNetworkContext $requestNetworkContext,
        private CurrentUserContext $currentUserContext
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function success(string $eventType, array $attributes = [], ?Request $request = null): ?UserManagementAuditEvent
    {
        return $this->record([
            ...$attributes,
            'event_type' => $eventType,
            'result' => UserManagementAuditEvent::RESULT_SUCCESS,
        ], $request);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function failed(string $eventType, array $attributes = [], ?Request $request = null): ?UserManagementAuditEvent
    {
        return $this->record([
            ...$attributes,
            'event_type' => $eventType,
            'result' => UserManagementAuditEvent::RESULT_FAILED,
        ], $request);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function blocked(string $eventType, array $attributes = [], ?Request $request = null): ?UserManagementAuditEvent
    {
        return $this->record([
            ...$attributes,
            'event_type' => $eventType,
            'result' => UserManagementAuditEvent::RESULT_BLOCKED,
            'http_status' => $attributes['http_status'] ?? 403,
        ], $request);
    }

    /**
     * Menulis audit secara fail-safe. Gunakan recordOrFail() jika caller perlu
     * menjadikan kegagalan audit sebagai kegagalan aksi utama.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes, ?Request $request = null): ?UserManagementAuditEvent
    {
        try {
            return $this->recordOrFail($attributes, $request);
        } catch (Throwable $exception) {
            report($exception);

            Log::channel('module_users')->error('User management audit event write failed', [
                'event_type' => $attributes['event_type'] ?? null,
                'result' => $attributes['result'] ?? null,
                'actor_user_id' => $this->modelKey($attributes['actor_user_id'] ?? $attributes['actor'] ?? null),
                'target_user_id' => $this->modelKey($attributes['target_user_id'] ?? $attributes['target_user'] ?? null),
                'target_user_position_id' => $this->modelKey($attributes['target_user_position_id'] ?? $attributes['target_position'] ?? null),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordOrFail(array $attributes, ?Request $request = null): UserManagementAuditEvent
    {
        $eventType = $this->requiredString($attributes, 'event_type');
        $result = $this->stringValue($attributes['result'] ?? UserManagementAuditEvent::RESULT_SUCCESS)
            ?? UserManagementAuditEvent::RESULT_SUCCESS;

        $occurredAt = $this->dateTimeValue($attributes, 'occurred_at');
        $createdAt = $this->dateTimeValue($attributes, 'created_at');
        $targetUserId = $this->modelKey($attributes['target_user_id'] ?? $attributes['target_user'] ?? null);
        $targetPositionId = $this->modelKey($attributes['target_user_position_id'] ?? $attributes['target_position'] ?? null);
        $beforeState = $this->stateValue($attributes['before_state'] ?? null);
        $afterState = $this->stateValue($attributes['after_state'] ?? null);

        $eventAttributes = [
            'event_uuid' => $this->stringValue($attributes['event_uuid'] ?? null) ?? (string) Str::uuid(),
            'actor_user_id' => $this->modelKey(
                $attributes['actor_user_id']
                    ?? $attributes['actor']
                    ?? $attributes['actor_user']
                    ?? $request?->user()
            ),
            'actor_user_position_id' => $this->actorPositionId($attributes, $request),
            'target_user_id' => $targetUserId,
            'target_user_position_id' => $targetPositionId,
            'event_type' => $eventType,
            'result' => $result,
            'resource_type' => $this->resourceType($attributes, $targetUserId, $targetPositionId),
            'resource_id' => $this->resourceId($attributes, $targetUserId, $targetPositionId),
            'reason_code' => $this->stringValue($attributes['reason_code'] ?? null),
            'reason' => $this->stringValue($attributes['reason'] ?? null),
            'message' => $this->limitedString($attributes['message'] ?? null, 500),
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'changed_fields' => $this->changedFields($attributes, $beforeState, $afterState),
            'metadata' => $this->stateValue($attributes['metadata'] ?? null),
            'before_state_hash' => array_key_exists('before_state_hash', $attributes)
                ? $this->stringValue($attributes['before_state_hash'])
                : $this->hashState($beforeState),
            'after_state_hash' => array_key_exists('after_state_hash', $attributes)
                ? $this->stringValue($attributes['after_state_hash'])
                : $this->hashState($afterState),
            'event_hash' => null,
            ...$this->requestAttributes($attributes, $request),
            'occurred_at' => $occurredAt,
            'retention_until' => $this->retentionUntil($attributes, $occurredAt),
            'created_at' => $createdAt,
        ];

        $eventAttributes['event_hash'] = $this->eventHash($attributes, $eventAttributes);

        return UserManagementAuditEvent::create($eventAttributes);
    }

    /**
     * @param  list<string>|null  $only
     * @return array<string, mixed>
     */
    public function snapshot(Model $model, ?array $only = null): array
    {
        $attributes = $model->attributesToArray();

        if ($only === null) {
            return $attributes;
        }

        return Arr::only($attributes, $only);
    }

    /**
     * @return array<string, mixed>
     */
    public function userSnapshot(User $user): array
    {
        return $this->snapshot($user, [
            'id',
            'nik',
            'nip',
            'nama',
            'email',
            'account_type',
            'status',
            'status_changed_at',
            'status_changed_by_user_id',
            'status_reason',
            'must_change_password',
            'password_changed_at',
            'password_expires_at',
            'password_reset_at',
            'password_reset_by_user_id',
            'sessions_invalidated_at',
            'email_verified_at',
            'consecutive_failed_login_count',
            'last_failed_login_at',
            'locked_at',
            'locked_until',
            'lock_reason',
            'last_login_at',
            'identity_verified_at',
            'identity_verified_by_user_id',
            'source_system',
            'external_id',
            'last_synced_at',
            'tahun_aktif',
            'created_by_user_id',
            'updated_by_user_id',
            'deleted_by_user_id',
            'created_at',
            'updated_at',
            'deleted_at',
            'remember_token_expires_at',
            'mfa_enabled_at',
            'mfa_confirmed_at',
            'mfa_last_used_at',
            'mfa_recovery_codes_generated_at',
            'mfa_pending_secret_created_at',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function positionSnapshot(UserPosition $position): array
    {
        $position->loadMissing('primaryDocument');

        return [
            ...$this->snapshot($position, [
                'id',
                'user_id',
                'jabatan_id',
                'instansi_id',
                'unit_kerja_id',
                'is_active',
                'started_at',
                'ended_at',
                'last_used_at',
                'activated_at',
                'activated_by_user_id',
                'deactivated_at',
                'deactivated_by_user_id',
                'deactivation_reason',
                'notes',
                'source_system',
                'external_id',
                'last_synced_at',
                'created_by_user_id',
                'updated_by_user_id',
                'deleted_by_user_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]),
            'primary_document' => $this->positionDocumentSnapshot($position->primaryDocument),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function positionDocumentSnapshot(?UserPositionDocument $document): ?array
    {
        if (! $document instanceof UserPositionDocument) {
            return null;
        }

        return $this->snapshot($document, [
            'id',
            'user_position_id',
            'document_type',
            'document_number',
            'document_date',
            'issued_by',
            'effective_from',
            'effective_until',
            'storage_disk',
            'original_name',
            'mime_type',
            'extension',
            'size_bytes',
            'file_sha256',
            'version',
            'is_primary',
            'verification_status',
            'verified_at',
            'verified_by_user_id',
            'uploaded_at',
            'uploaded_by_user_id',
            'created_by_user_id',
            'updated_by_user_id',
            'deleted_by_user_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function yearPermissionSnapshot(UserPositionYearPermission $permission): array
    {
        return $this->snapshot($permission, [
            'id',
            'user_position_id',
            'tahun',
            'permission_type',
            'status',
            'valid_from',
            'valid_until',
            'reason',
            'reference_number',
            'reference_date',
            'requested_by_user_id',
            'requested_by_position_id',
            'requested_at',
            'granted_by_user_id',
            'granted_by_position_id',
            'granted_at',
            'grant_notes',
            'revoked_by_user_id',
            'revoked_by_position_id',
            'revoked_at',
            'revocation_reason',
            'usage_count',
            'last_used_at',
            'last_used_by_user_id',
            'last_used_by_position_id',
            'created_by_user_id',
            'updated_by_user_id',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function actorPositionId(array $attributes, ?Request $request): int|string|null
    {
        $explicitPosition = $attributes['actor_user_position_id']
            ?? $attributes['actor_position']
            ?? null;

        if ($explicitPosition !== null) {
            return $this->modelKey($explicitPosition);
        }

        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        return $this->currentUserContext->activePosition($request)?->getKey();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function requestAttributes(array $attributes, ?Request $request): array
    {
        $networkContext = $request instanceof Request
            ? $this->requestNetworkContext->fromRequest($request)
            : [];

        return [
            'request_id' => $this->stringValue($attributes['request_id'] ?? ($networkContext['request_id'] ?? null)),
            'correlation_id' => $this->stringValue($attributes['correlation_id'] ?? ($networkContext['correlation_id'] ?? null)),
            'session_id_hash' => $this->sessionIdHash($attributes, $request),
            'source_channel' => $this->limitedString(
                $attributes['source_channel'] ?? ($request instanceof Request ? 'web' : 'system'),
                30
            ) ?? 'web',
            'route_name' => $this->limitedString($attributes['route_name'] ?? $request?->route()?->getName(), 150),
            'request_path' => $this->limitedString($attributes['request_path'] ?? $request?->path(), 500),
            'http_method' => $this->limitedString($attributes['http_method'] ?? $request?->method(), 10),
            'http_status' => $this->smallIntegerValue($attributes['http_status'] ?? null),
            'ip_address' => $this->limitedString($attributes['ip_address'] ?? ($networkContext['ip_address'] ?? null), 45),
            'user_agent' => $this->stringValue($attributes['user_agent'] ?? $request?->userAgent()),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sessionIdHash(array $attributes, ?Request $request): ?string
    {
        if (array_key_exists('session_id_hash', $attributes)) {
            return $this->stringValue($attributes['session_id_hash']);
        }

        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->getId();

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionId, $this->auditHashKey());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resourceType(array $attributes, int|string|null $targetUserId, int|string|null $targetPositionId): ?string
    {
        if (array_key_exists('resource_type', $attributes)) {
            return $this->stringValue($attributes['resource_type']);
        }

        if ($targetPositionId !== null) {
            return UserPosition::class;
        }

        return $targetUserId !== null ? User::class : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resourceId(array $attributes, int|string|null $targetUserId, int|string|null $targetPositionId): ?string
    {
        if (array_key_exists('resource_id', $attributes)) {
            return $this->stringValue($attributes['resource_id']);
        }

        $resourceId = $targetPositionId ?? $targetUserId;

        return $resourceId === null ? null : (string) $resourceId;
    }

    private function modelKey(mixed $value): int|string|null
    {
        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function stateValue(mixed $value): ?array
    {
        if ($value instanceof Model) {
            return $this->snapshot($value);
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|list<mixed>|null  $beforeState
     * @param  array<string, mixed>|list<mixed>|null  $afterState
     * @return list<string>|null
     */
    private function changedFields(array $attributes, ?array $beforeState, ?array $afterState): ?array
    {
        if (array_key_exists('changed_fields', $attributes)) {
            $changedFields = $attributes['changed_fields'];

            if (! is_array($changedFields)) {
                return null;
            }

            return array_values(array_filter(
                $changedFields,
                static fn (mixed $field): bool => is_string($field) && $field !== ''
            ));
        }

        if ($beforeState === null || $afterState === null) {
            return null;
        }

        return collect(array_unique([...array_keys($beforeState), ...array_keys($afterState)]))
            ->filter(fn (string|int $field): bool => ($beforeState[$field] ?? null) !== ($afterState[$field] ?? null))
            ->map(fn (string|int $field): string => (string) $field)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dateTimeValue(array $attributes, string $key): CarbonImmutable
    {
        $value = $attributes[$key] ?? null;

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::instance(now());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function retentionUntil(array $attributes, DateTimeInterface $occurredAt): mixed
    {
        if (array_key_exists('retention_until', $attributes)) {
            return $attributes['retention_until'];
        }

        $retentionDays = config('auth.audit.user_management_events.integrity.retention_days');

        if (! is_string($retentionDays) && ! is_numeric($retentionDays)) {
            return null;
        }

        $retentionDays = filter_var($retentionDays, FILTER_VALIDATE_INT);

        return is_int($retentionDays) && $retentionDays > 0
            ? CarbonImmutable::instance($occurredAt)->addDays($retentionDays)
            : null;
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $hashableValue
     */
    private function hashState(?array $hashableValue): ?string
    {
        if ($hashableValue === null || ! $this->eventHashEnabled()) {
            return null;
        }

        return hash_hmac('sha256', $this->canonicalPayload($hashableValue), $this->auditHashKey());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $eventAttributes
     */
    private function eventHash(array $overrides, array $eventAttributes): ?string
    {
        if (array_key_exists('event_hash', $overrides)) {
            return $this->stringValue($overrides['event_hash']);
        }

        if (! $this->eventHashEnabled()) {
            return null;
        }

        return hash_hmac(
            'sha256',
            $this->canonicalPayload(Arr::except($eventAttributes, ['event_hash'])),
            $this->auditHashKey()
        );
    }

    private function canonicalPayload(mixed $value): string
    {
        return json_encode(
            [
                'event_hash_payload_version' => config(
                    'auth.audit.user_management_events.integrity.event_hash_payload_version',
                    'v1'
                ),
                'payload' => $this->normalize($value),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.uP');
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    private function eventHashEnabled(): bool
    {
        return (bool) config('auth.audit.user_management_events.integrity.event_hash_enabled', true);
    }

    private function auditHashKey(): string
    {
        $auditHashKey = config('auth.audit.hash_key', config('auth.audit_hash_key', config('app.key')));

        return is_string($auditHashKey) && $auditHashKey !== ''
            ? $auditHashKey
            : (string) config('app.key');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requiredString(array $attributes, string $key): string
    {
        $value = $this->stringValue($attributes[$key] ?? null);

        if ($value === null) {
            throw new InvalidArgumentException("User management audit attribute [{$key}] is required.");
        }

        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof Stringable) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function limitedString(mixed $value, int $limit): ?string
    {
        $value = $this->stringValue($value);

        return $value === null ? null : Str::limit($value, $limit, '');
    }

    private function smallIntegerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= 65535 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;

            return $value <= 65535 ? $value : null;
        }

        return null;
    }
}
