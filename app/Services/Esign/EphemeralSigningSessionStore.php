<?php

namespace App\Services\Esign;

use App\Data\Esign\SigningSessionData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;

final class EphemeralSigningSessionStore
{
    private const KEY_PREFIX = 'esign:signing-session:';

    public function __construct(
        private CacheFactory $cache,
        private ConfigRepository $config,
        private Encrypter $encrypter,
    ) {}

    public function put(SigningSessionData $session): void
    {
        if (! Str::isUuid($session->sessionId) || $session->expiresAt->isPast()) {
            throw new EsignInvariantViolationException('signing_session_expiry_invalid');
        }

        $json = json_encode($session->toArray(), JSON_THROW_ON_ERROR);
        $encryptedPayload = $this->encrypter->encrypt($json, false);

        if (! $this->store()->put(
            $this->key($session->sessionId),
            $encryptedPayload,
            $session->expiresAt,
        )) {
            throw new EsignInvariantViolationException('signing_session_cache_write_failed');
        }
    }

    public function get(string $sessionId, int $actorUserId): ?SigningSessionData
    {
        if (! Str::isUuid($sessionId)) {
            return null;
        }

        $encryptedPayload = $this->store()->get($this->key($sessionId));

        if (! is_string($encryptedPayload) || $encryptedPayload === '') {
            return null;
        }

        try {
            $payload = json_decode(
                $this->encrypter->decrypt($encryptedPayload, false),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            if (! is_array($payload)) {
                throw new EsignInvariantViolationException('signing_session_payload_invalid');
            }

            $session = SigningSessionData::fromArray($payload);
        } catch (\Throwable $exception) {
            $this->forget($sessionId);

            throw new EsignInvariantViolationException('signing_session_payload_invalid');
        }

        if (! hash_equals($sessionId, $session->sessionId)) {
            $this->forget($sessionId);

            throw new EsignInvariantViolationException('signing_session_id_mismatch');
        }

        if ($session->expiresAt->isPast()) {
            $this->forget($sessionId);

            return null;
        }

        if ($session->actorUserId !== $actorUserId) {
            return null;
        }

        return $session;
    }

    public function forget(string $sessionId): void
    {
        if (Str::isUuid($sessionId)) {
            $this->store()->forget($this->key($sessionId));
        }
    }

    private function store(): CacheRepository
    {
        $store = $this->config->get('esign.signing_session.cache_store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    private function key(string $sessionId): string
    {
        return self::KEY_PREFIX.$sessionId;
    }
}
