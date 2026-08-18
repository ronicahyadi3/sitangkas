<?php

namespace App\Services\Auth;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\AnonymousIp;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class IpRiskContext
{
    /**
     * @var array<string, Reader|null>
     */
    private array $readers = [];

    /**
     * @return array{
     *     is_vpn: bool|null,
     *     is_proxy: bool|null,
     *     is_tor: bool|null,
     *     risk_score: int|null
     * }
     */
    public function fromIp(?string $ipAddress): array
    {
        if (! $this->shouldLookup($ipAddress) || ! is_string($ipAddress)) {
            return $this->emptyContext();
        }

        try {
            return Cache::remember(
                $this->cacheKey($ipAddress),
                $this->cacheTtlSeconds(),
                fn (): array => $this->lookup($ipAddress)
            );
        } catch (Throwable) {
            return $this->lookup($ipAddress);
        }
    }

    /**
     * @return array{
     *     is_vpn: null,
     *     is_proxy: null,
     *     is_tor: null,
     *     risk_score: null
     * }
     */
    private function emptyContext(): array
    {
        return [
            'is_vpn' => null,
            'is_proxy' => null,
            'is_tor' => null,
            'risk_score' => null,
        ];
    }

    /**
     * @return array{
     *     is_vpn: bool|null,
     *     is_proxy: bool|null,
     *     is_tor: bool|null,
     *     risk_score: int|null
     * }
     */
    private function lookup(string $ipAddress): array
    {
        if ($this->provider() !== 'maxmind_anonymous_ip') {
            return $this->emptyContext();
        }

        return $this->maxMindAnonymousIpContext($ipAddress);
    }

    /**
     * @return array{
     *     is_vpn: bool|null,
     *     is_proxy: bool|null,
     *     is_tor: bool|null,
     *     risk_score: null
     * }
     */
    private function maxMindAnonymousIpContext(string $ipAddress): array
    {
        $reader = $this->anonymousIpReader();

        if (! $reader instanceof Reader) {
            return $this->emptyContext();
        }

        try {
            return $this->anonymousIpModelContext($reader->anonymousIp($ipAddress));
        } catch (AddressNotFoundException) {
            return $this->emptyContext();
        } catch (Throwable) {
            return $this->emptyContext();
        }
    }

    /**
     * @return array{
     *     is_vpn: bool,
     *     is_proxy: bool,
     *     is_tor: bool,
     *     risk_score: null
     * }
     */
    private function anonymousIpModelContext(AnonymousIp $anonymousIp): array
    {
        return [
            'is_vpn' => $anonymousIp->isAnonymousVpn,
            'is_proxy' => $anonymousIp->isPublicProxy || $anonymousIp->isResidentialProxy,
            'is_tor' => $anonymousIp->isTorExitNode,
            'risk_score' => null,
        ];
    }

    private function anonymousIpReader(): ?Reader
    {
        $path = $this->databasePath();

        if ($path === null) {
            return null;
        }

        if (array_key_exists($path, $this->readers)) {
            return $this->readers[$path];
        }

        try {
            return $this->readers[$path] = new Reader($path);
        } catch (Throwable) {
            return $this->readers[$path] = null;
        }
    }

    private function shouldLookup(?string $ipAddress): bool
    {
        return $this->enabled()
            && $this->mode() === 'audit'
            && ! $this->blockingEnabled()
            && $this->isPublicIpAddress($ipAddress)
            && ! $this->isAllowlisted($ipAddress)
            && $this->provider() === 'maxmind_anonymous_ip'
            && $this->databasePath() !== null;
    }

    private function enabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.ip_risk.enabled', false);
    }

    private function blockingEnabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.ip_risk.blocking_enabled', false);
    }

    private function mode(): string
    {
        $mode = config('auth.audit.login_events.enrichment.ip_risk.mode', 'audit');

        return is_string($mode) ? $mode : 'audit';
    }

    private function provider(): string
    {
        $provider = config('auth.audit.login_events.enrichment.ip_risk.provider', 'none');

        return is_string($provider) ? $provider : 'none';
    }

    private function databasePath(): ?string
    {
        $path = config('auth.audit.login_events.enrichment.ip_risk.anonymous_ip_database_path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        $path = $this->isAbsolutePath($path) ? $path : base_path($path);

        return is_file($path) && is_readable($path) ? $path : null;
    }

    private function cacheTtlSeconds(): int
    {
        return max(60, (int) config('auth.audit.login_events.enrichment.ip_risk.cache_ttl_seconds', 86400));
    }

    private function cacheKey(string $ipAddress): string
    {
        return 'auth:login-event:ip-risk:'.hash('sha256', implode('|', [
            $ipAddress,
            $this->mode(),
            $this->provider(),
            config('auth.audit.login_events.enrichment.ip_risk.anonymous_ip_database_path'),
        ]));
    }

    private function isPublicIpAddress(?string $ipAddress): bool
    {
        return is_string($ipAddress)
            && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function isAllowlisted(string $ipAddress): bool
    {
        $cidrs = config('auth.audit.login_events.enrichment.ip_risk.allowlist_cidrs', []);

        if (! is_array($cidrs) || $cidrs === []) {
            return false;
        }

        $cidrs = array_values(array_filter(
            $cidrs,
            static fn (mixed $cidr): bool => is_string($cidr) && trim($cidr) !== ''
        ));

        if ($cidrs === []) {
            return false;
        }

        try {
            return IpUtils::checkIp($ipAddress, $cidrs);
        } catch (Throwable) {
            return false;
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
