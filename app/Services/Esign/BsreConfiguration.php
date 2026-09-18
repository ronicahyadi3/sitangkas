<?php

namespace App\Services\Esign;

use App\Exceptions\Esign\EsignConfigurationException;

final readonly class BsreConfiguration
{
    /**
     * @param  array{default: int, verify: int}  $connectTimeouts
     * @param  array{status: int, totp: int, certificate: int, sign: int, verify: int}  $timeouts
     * @param  array{sign: string, verify: string, user_status: string, totp: string, certificate_chain: string}  $endpoints
     */
    private function __construct(
        public bool $enabled,
        public ?string $baseUrl,
        private ?string $username,
        private ?string $password,
        public bool $verifyTls,
        public bool $allowInsecureHttp,
        private array $connectTimeouts,
        private array $timeouts,
        public string $location,
        public string $defaultReason,
        private array $endpoints,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public static function fromArray(array $configuration): self
    {
        $enabled = (bool) ($configuration['enabled'] ?? false);
        $baseUrl = self::nullableString($configuration['base_url'] ?? null);
        $username = self::nullableString($configuration['username'] ?? null);
        $password = self::nullableSecret($configuration['password'] ?? null);
        $verifyTls = (bool) ($configuration['verify_tls'] ?? true);
        $allowInsecureHttp = (bool) ($configuration['allow_insecure_http'] ?? false);
        $location = self::string($configuration['location'] ?? '');
        $defaultReason = self::string($configuration['default_reason'] ?? '');
        $connectTimeouts = self::connectTimeouts($configuration['connect_timeouts'] ?? []);
        $timeouts = self::timeouts($configuration['timeouts'] ?? []);
        $endpoints = self::endpoints($configuration['endpoints'] ?? []);

        if (! $enabled) {
            return new self(
                enabled: false,
                baseUrl: $baseUrl,
                username: $username,
                password: $password,
                verifyTls: $verifyTls,
                allowInsecureHttp: $allowInsecureHttp,
                connectTimeouts: $connectTimeouts,
                timeouts: $timeouts,
                location: $location,
                defaultReason: $defaultReason,
                endpoints: $endpoints,
            );
        }

        $issues = [];

        if ($baseUrl === null) {
            $issues[] = 'missing_base_url';
        } elseif (! self::isValidBaseUrl($baseUrl)) {
            $issues[] = 'invalid_base_url';
        } elseif (strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === 'http' && ! $allowInsecureHttp) {
            $issues[] = 'insecure_http_not_allowed';
        }

        if ($username === null) {
            $issues[] = 'missing_username';
        }

        if ($password === null) {
            $issues[] = 'missing_password';
        }

        if (! $verifyTls) {
            $issues[] = 'tls_verification_disabled';
        }

        if ($location === '') {
            $issues[] = 'missing_location';
        }

        if ($defaultReason === '') {
            $issues[] = 'missing_default_reason';
        }

        foreach ($connectTimeouts as $operation => $timeout) {
            if ($timeout <= 0) {
                $issues[] = "invalid_connect_timeout_{$operation}";
            }
        }

        foreach ($timeouts as $operation => $timeout) {
            if ($timeout <= 0) {
                $issues[] = "invalid_timeout_{$operation}";
            }
        }

        foreach ($endpoints as $operation => $endpoint) {
            if (! self::isValidV2Endpoint($endpoint)) {
                $issues[] = "invalid_endpoint_{$operation}";
            }
        }

        if ($issues !== []) {
            throw new EsignConfigurationException($issues);
        }

        return new self(
            enabled: true,
            baseUrl: rtrim($baseUrl, '/'),
            username: $username,
            password: $password,
            verifyTls: $verifyTls,
            allowInsecureHttp: $allowInsecureHttp,
            connectTimeouts: $connectTimeouts,
            timeouts: $timeouts,
            location: $location,
            defaultReason: $defaultReason,
            endpoints: $endpoints,
        );
    }

    public function username(): string
    {
        $this->assertEnabled();

        return $this->username ?? throw new EsignConfigurationException(['missing_username']);
    }

    public function baseUrl(): string
    {
        $this->assertEnabled();

        return $this->baseUrl ?? throw new EsignConfigurationException(['missing_base_url']);
    }

    public function password(): string
    {
        $this->assertEnabled();

        return $this->password ?? throw new EsignConfigurationException(['missing_password']);
    }

    public function connectTimeout(string $operation): int
    {
        $this->assertEnabled();

        if (! array_key_exists($operation, $this->timeouts)) {
            throw new EsignConfigurationException(['unsupported_connect_timeout_operation']);
        }

        return $operation === 'verify'
            ? $this->connectTimeouts['verify']
            : $this->connectTimeouts['default'];
    }

    public function timeout(string $operation): int
    {
        $this->assertEnabled();

        return $this->timeouts[$operation]
            ?? throw new EsignConfigurationException(['unsupported_timeout_operation']);
    }

    public function endpoint(string $operation): string
    {
        $this->assertEnabled();

        return $this->endpoints[$operation]
            ?? throw new EsignConfigurationException(['unsupported_endpoint_operation']);
    }

    public function assertEnabled(): void
    {
        if (! $this->enabled) {
            throw new EsignConfigurationException(['integration_disabled']);
        }
    }

    /**
     * Prevent credentials from being exposed by dump/debug tooling.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'enabled' => $this->enabled,
            'baseUrl' => $this->baseUrl,
            'username' => $this->username === null ? null : '[REDACTED]',
            'password' => $this->password === null ? null : '[REDACTED]',
            'verifyTls' => $this->verifyTls,
            'allowInsecureHttp' => $this->allowInsecureHttp,
            'connectTimeouts' => $this->connectTimeouts,
            'timeouts' => $this->timeouts,
            'location' => $this->location,
            'defaultReason' => $this->defaultReason,
            'endpoints' => $this->endpoints,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = self::string($value);

        return $value === '' ? null : $value;
    }

    private static function nullableSecret(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array{default: int, verify: int}
     */
    private static function connectTimeouts(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'default' => (int) ($value['default'] ?? 0),
            'verify' => (int) ($value['verify'] ?? 0),
        ];
    }

    /**
     * @return array{status: int, totp: int, certificate: int, sign: int, verify: int}
     */
    private static function timeouts(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'status' => (int) ($value['status'] ?? 0),
            'totp' => (int) ($value['totp'] ?? 0),
            'certificate' => (int) ($value['certificate'] ?? 0),
            'sign' => (int) ($value['sign'] ?? 0),
            'verify' => (int) ($value['verify'] ?? 0),
        ];
    }

    /**
     * @return array{sign: string, verify: string, user_status: string, totp: string, certificate_chain: string}
     */
    private static function endpoints(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'sign' => self::string($value['sign'] ?? ''),
            'verify' => self::string($value['verify'] ?? ''),
            'user_status' => self::string($value['user_status'] ?? ''),
            'totp' => self::string($value['totp'] ?? ''),
            'certificate_chain' => self::string($value['certificate_chain'] ?? ''),
        ];
    }

    private static function isValidBaseUrl(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https'], true)
            && isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    private static function isValidV2Endpoint(string $endpoint): bool
    {
        return str_starts_with($endpoint, '/api/v2/')
            && ! str_contains($endpoint, '..')
            && parse_url($endpoint, PHP_URL_HOST) === null
            && parse_url($endpoint, PHP_URL_QUERY) === null
            && parse_url($endpoint, PHP_URL_FRAGMENT) === null;
    }
}
