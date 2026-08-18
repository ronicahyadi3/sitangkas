<?php

namespace App\Services\Auth;

use DateTimeInterface;
use Illuminate\Support\Arr;
use RuntimeException;

class LoginEventIntegrity
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function hash(array $attributes): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return hash_hmac(
            'sha256',
            $this->canonicalPayload($attributes),
            $this->auditHashKey()
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function canonicalPayload(array $attributes): string
    {
        $payload = [
            'event_hash_payload_version' => config(
                'auth.audit.login_events.enrichment.integrity.event_hash_payload_version',
                'v1'
            ),
            'event' => $this->normalize($this->hashableAttributes($attributes)),
        ];

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function hashableAttributes(array $attributes): array
    {
        $attributes = Arr::except($attributes, [
            'event_hash',
            'login_identifier',
        ]);

        ksort($attributes);

        return $attributes;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.uP');
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function normalizeArray(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }

    private function enabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.integrity.event_hash_enabled', true);
    }

    private function auditHashKey(): string
    {
        $auditHashKey = config('auth.audit.hash_key', config('auth.audit_hash_key'));

        if (is_string($auditHashKey) && $auditHashKey !== '') {
            return $auditHashKey;
        }

        if (app()->environment('local', 'testing')) {
            $appKey = config('app.key');

            if (is_string($appKey) && $appKey !== '') {
                return $appKey;
            }
        }

        throw new RuntimeException('AUDIT_HASH_KEY must be configured for authentication audit event hashing.');
    }
}
