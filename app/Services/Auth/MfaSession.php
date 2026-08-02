<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MfaSession
{
    public const VERIFIED_SESSION_KEY = 'auth_mfa_verified';

    public function __construct(private MfaPolicy $mfaPolicy) {}

    /**
     * @return array{user_id: string, real_user_position_id: string, method: string, verified_at: string, expires_at: string}
     */
    public function markVerified(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        ?string $method = null,
        ?Carbon $verifiedAt = null
    ): array {
        $verifiedAt ??= now();
        $expiresAt = $this->mfaPolicy->verifiedExpiresAt($realActiveUserPosition, $verifiedAt);

        $state = [
            'user_id' => (string) $user->getKey(),
            'real_user_position_id' => (string) $realActiveUserPosition->getKey(),
            'method' => $method ?: $this->mfaPolicy->method(),
            'verified_at' => $verifiedAt->getTimestamp(),
            'expires_at' => $expiresAt->getTimestamp(),
        ];

        $request->session()->put(self::VERIFIED_SESSION_KEY, $state);

        return [
            'user_id' => $state['user_id'],
            'real_user_position_id' => $state['real_user_position_id'],
            'method' => $state['method'],
            'verified_at' => $verifiedAt->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    public function isVerifiedFor(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        ?string $method = null
    ): bool {
        $state = $this->state($request);

        if ($state === null) {
            return false;
        }

        if ($state['expires_at']->isPast()) {
            $this->forget($request);

            return false;
        }

        if ($state['user_id'] !== (string) $user->getKey()) {
            return false;
        }

        if ($state['real_user_position_id'] !== (string) $realActiveUserPosition->getKey()) {
            return false;
        }

        return $method === null || $state['method'] === $method;
    }

    public function shouldChallenge(Request $request, User $user, UserPosition $realActiveUserPosition): bool
    {
        return $this->mfaPolicy->challengeRequiredFor($user, $realActiveUserPosition)
            && ! $this->isVerifiedFor($request, $user, $realActiveUserPosition);
    }

    public function shouldSetup(Request $request, User $user, UserPosition $realActiveUserPosition): bool
    {
        return $this->mfaPolicy->setupRequiredFor($user, $realActiveUserPosition)
            && ! $this->isVerifiedFor($request, $user, $realActiveUserPosition);
    }

    /**
     * @return array{user_id: string, real_user_position_id: string, method: string, verified_at: Carbon, expires_at: Carbon}|null
     */
    public function state(Request $request): ?array
    {
        $state = $request->session()->get(self::VERIFIED_SESSION_KEY);

        if (! is_array($state)) {
            return null;
        }

        $userId = $this->stringValue($state['user_id'] ?? null);
        $realUserPositionId = $this->stringValue($state['real_user_position_id'] ?? null);
        $method = $this->stringValue($state['method'] ?? null);
        $verifiedAt = $this->carbonFromSessionTimestamp($state['verified_at'] ?? null);
        $expiresAt = $this->carbonFromSessionTimestamp($state['expires_at'] ?? null);

        if ($userId === null || $realUserPositionId === null || $method === null || $verifiedAt === null || $expiresAt === null) {
            return null;
        }

        return [
            'user_id' => $userId,
            'real_user_position_id' => $realUserPositionId,
            'method' => $method,
            'verified_at' => $verifiedAt,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{user_id: string, real_user_position_id: string, method: string, verified_at: string, expires_at: string}|null
     */
    public function stateForAudit(Request $request): ?array
    {
        $state = $this->state($request);

        if ($state === null) {
            return null;
        }

        return [
            'user_id' => $state['user_id'],
            'real_user_position_id' => $state['real_user_position_id'],
            'method' => $state['method'],
            'verified_at' => $state['verified_at']->toISOString(),
            'expires_at' => $state['expires_at']->toISOString(),
        ];
    }

    public function expiresAt(Request $request): ?Carbon
    {
        return $this->state($request)['expires_at'] ?? null;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::VERIFIED_SESSION_KEY);
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return null;
    }

    private function carbonFromSessionTimestamp(mixed $value): ?Carbon
    {
        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return null;
    }
}
