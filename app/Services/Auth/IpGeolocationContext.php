<?php

namespace App\Services\Auth;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\Asn;
use GeoIp2\Model\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class IpGeolocationContext
{
    /**
     * @var array<string, Reader|null>
     */
    private array $readers = [];

    /**
     * @return array{
     *     network_asn: string|null,
     *     network_organization: string|null,
     *     country_code: string|null,
     *     region: string|null,
     *     city: string|null
     * }
     */
    public function fromIp(?string $ipAddress): array
    {
        if (! $this->enabled() || ! $this->isPublicIpAddress($ipAddress) || ! $this->hasReadableDatabase()) {
            return $this->emptyContext();
        }

        return Cache::remember(
            $this->cacheKey($ipAddress),
            $this->cacheTtlSeconds(),
            fn (): array => [
                ...$this->emptyContext(),
                ...$this->cityContext($ipAddress),
                ...$this->asnContext($ipAddress),
            ]
        );
    }

    /**
     * @return array{
     *     network_asn: null,
     *     network_organization: null,
     *     country_code: null,
     *     region: null,
     *     city: null
     * }
     */
    private function emptyContext(): array
    {
        return [
            'network_asn' => null,
            'network_organization' => null,
            'country_code' => null,
            'region' => null,
            'city' => null,
        ];
    }

    /**
     * @return array{
     *     country_code: string|null,
     *     region: string|null,
     *     city: string|null
     * }
     */
    private function cityContext(string $ipAddress): array
    {
        $reader = $this->reader('city_database_path');

        if (! $reader instanceof Reader) {
            return [
                'country_code' => null,
                'region' => null,
                'city' => null,
            ];
        }

        try {
            return $this->cityModelContext($reader->city($ipAddress));
        } catch (AddressNotFoundException) {
            return [
                'country_code' => null,
                'region' => null,
                'city' => null,
            ];
        } catch (Throwable) {
            return [
                'country_code' => null,
                'region' => null,
                'city' => null,
            ];
        }
    }

    /**
     * @return array{
     *     country_code: string|null,
     *     region: string|null,
     *     city: string|null
     * }
     */
    private function cityModelContext(City $city): array
    {
        return [
            'country_code' => $this->countryCode($city->country->isoCode),
            'region' => $this->text($city->mostSpecificSubdivision->name, 100),
            'city' => $this->text($city->city->name, 100),
        ];
    }

    /**
     * @return array{
     *     network_asn: string|null,
     *     network_organization: string|null
     * }
     */
    private function asnContext(string $ipAddress): array
    {
        $reader = $this->reader('asn_database_path');

        if (! $reader instanceof Reader) {
            return [
                'network_asn' => null,
                'network_organization' => null,
            ];
        }

        try {
            return $this->asnModelContext($reader->asn($ipAddress));
        } catch (AddressNotFoundException) {
            return [
                'network_asn' => null,
                'network_organization' => null,
            ];
        } catch (Throwable) {
            return [
                'network_asn' => null,
                'network_organization' => null,
            ];
        }
    }

    /**
     * @return array{
     *     network_asn: string|null,
     *     network_organization: string|null
     * }
     */
    private function asnModelContext(Asn $asn): array
    {
        return [
            'network_asn' => $asn->autonomousSystemNumber !== null
                ? 'AS'.$asn->autonomousSystemNumber
                : null,
            'network_organization' => $this->text($asn->autonomousSystemOrganization, 255),
        ];
    }

    private function reader(string $configKey): ?Reader
    {
        $path = $this->databasePath($configKey);

        if ($path === null) {
            return null;
        }

        if (array_key_exists($path, $this->readers)) {
            return $this->readers[$path];
        }

        try {
            return $this->readers[$path] = new Reader($path, $this->locales());
        } catch (Throwable) {
            return $this->readers[$path] = null;
        }
    }

    private function databasePath(string $configKey): ?string
    {
        $path = config("auth.audit.login_events.enrichment.geoip.{$configKey}");

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        $path = $this->isAbsolutePath($path) ? $path : base_path($path);

        return is_file($path) && is_readable($path) ? $path : null;
    }

    private function hasReadableDatabase(): bool
    {
        return $this->databasePath('city_database_path') !== null
            || $this->databasePath('asn_database_path') !== null;
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return collect([
            config('app.locale'),
            config('app.fallback_locale'),
            'en',
        ])
            ->filter(fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '')
            ->map(fn (string $locale): string => trim($locale))
            ->unique()
            ->values()
            ->all();
    }

    private function enabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.geoip.enabled', false)
            && config('auth.audit.login_events.enrichment.geoip.provider') === 'maxmind';
    }

    private function cacheTtlSeconds(): int
    {
        return max(60, (int) config('auth.audit.login_events.enrichment.geoip.cache_ttl_seconds', 86400));
    }

    private function cacheKey(string $ipAddress): string
    {
        return 'auth:login-event:geoip:'.hash('sha256', implode('|', [
            $ipAddress,
            config('auth.audit.login_events.enrichment.geoip.provider'),
            config('auth.audit.login_events.enrichment.geoip.city_database_path'),
            config('auth.audit.login_events.enrichment.geoip.asn_database_path'),
        ]));
    }

    private function isPublicIpAddress(?string $ipAddress): bool
    {
        return is_string($ipAddress)
            && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function countryCode(?string $countryCode): ?string
    {
        if (! is_string($countryCode)) {
            return null;
        }

        $countryCode = strtoupper(trim($countryCode));

        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1 ? $countryCode : null;
    }

    private function text(?string $value, int $maxLength): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $maxLength, '');
    }
}
