<?php

namespace App\Services\Esign;

use App\Data\Esign\SigningSecretData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;
use SensitiveParameter;

final class EphemeralSigningSecretStore
{
    private const KEY_PREFIX = 'esign:signing-secret:';

    public function __construct(
        private CacheFactory $cache,
        private ConfigRepository $config,
        private Encrypter $encrypter,
    ) {}

    public function put(#[SensitiveParameter] string $passphrase, int $actorUserId): string
    {
        if (trim($passphrase) === '') {
            throw new EsignInvariantViolationException('signing_secret_empty');
        }

        $createdAt = CarbonImmutable::now();
        $secret = new SigningSecretData(
            reference: (string) Str::uuid(),
            actorUserId: $actorUserId,
            passphrase: $passphrase,
            createdAt: $createdAt,
            expiresAt: $createdAt->addMinutes($this->ttlMinutes()),
        );
        $payload = json_encode($secret->toArray(), JSON_THROW_ON_ERROR);
        $encryptedPayload = $this->encrypter->encrypt($payload, false);

        if (! $this->store()->put($this->key($secret->reference), $encryptedPayload, $secret->expiresAt)) {
            throw new EsignInvariantViolationException('signing_secret_cache_write_failed');
        }

        return $secret->reference;
    }

    public function take(string $reference, int $actorUserId): ?SigningSecretData
    {
        if (! Str::isUuid($reference)) {
            return null;
        }

        $encryptedPayload = $this->store()->pull($this->key($reference));

        if (! is_string($encryptedPayload) || $encryptedPayload === '') {
            return null;
        }

        try {
            $decryptedPayload = $this->encrypter->decrypt($encryptedPayload, false);

            if (! is_string($decryptedPayload)) {
                throw new EsignInvariantViolationException('signing_secret_payload_invalid');
            }

            $payload = json_decode($decryptedPayload, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($payload)
                || ! hash_equals($reference, $this->string($payload, 'reference'))
                || $this->integer($payload, 'actor_user_id') !== $actorUserId) {
                throw new EsignInvariantViolationException('signing_secret_payload_invalid');
            }

            $secret = new SigningSecretData(
                reference: $reference,
                actorUserId: $actorUserId,
                passphrase: $this->string($payload, 'passphrase'),
                createdAt: CarbonImmutable::parse($this->string($payload, 'created_at')),
                expiresAt: CarbonImmutable::parse($this->string($payload, 'expires_at')),
            );
        } catch (\Throwable) {
            throw new EsignInvariantViolationException('signing_secret_payload_invalid');
        }

        return $secret->expiresAt->isPast() ? null : $secret;
    }

    public function forget(string $reference): void
    {
        if (Str::isUuid($reference)) {
            $this->store()->forget($this->key($reference));
        }
    }

    private function ttlMinutes(): int
    {
        $ttl = $this->config->get('esign.processing.secret_ttl_minutes', 30);

        if (! is_int($ttl) || $ttl < 1 || $ttl > 120) {
            throw new EsignInvariantViolationException('signing_secret_ttl_invalid');
        }

        return $ttl;
    }

    private function store(): CacheRepository
    {
        $store = $this->config->get('esign.processing.secret_cache_store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    private function key(string $reference): string
    {
        return self::KEY_PREFIX.$reference;
    }

    /** @param array<string, mixed> $payload */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new EsignInvariantViolationException('signing_secret_payload_invalid');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function integer(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (! is_int($value)) {
            throw new EsignInvariantViolationException('signing_secret_payload_invalid');
        }

        return $value;
    }
}
