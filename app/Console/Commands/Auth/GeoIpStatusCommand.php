<?php

namespace App\Console\Commands\Auth;

use App\Services\Auth\IpGeolocationContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('auth:geoip-status {--ip=8.8.8.8 : Public IP used for sample lookup} {--no-lookup : Skip sample lookup} {--fail-on-missing : Return failure when GeoIP is enabled but a database is missing}')]
#[Description('Display authentication GeoIP/ASN enrichment configuration and readiness.')]
class GeoIpStatusCommand extends Command
{
    public function handle(IpGeolocationContext $ipGeolocationContext): int
    {
        $enabled = $this->enabled();
        $provider = $this->provider();
        $cityDatabase = $this->databaseStatus('city_database_path');
        $asnDatabase = $this->databaseStatus('asn_database_path');

        $this->displayConfig($enabled, $provider);
        $this->displayDatabaseStatus($cityDatabase, $asnDatabase);

        $exitCode = $this->readinessExitCode($enabled, $provider, $cityDatabase, $asnDatabase);

        if (! $this->noLookup()) {
            $this->displayLookup($enabled, $provider, $cityDatabase, $asnDatabase, $ipGeolocationContext);
        }

        return $exitCode;
    }

    private function displayConfig(bool $enabled, string $provider): void
    {
        $this->table(
            ['Config', 'Value'],
            [
                ['enabled', $this->yesNo($enabled)],
                ['provider', $provider],
                ['cache_ttl_seconds', (string) $this->cacheTtlSeconds()],
                ['database_path legacy fallback', $this->valueOrDash($this->configString('database_path'))],
            ]
        );
    }

    /**
     * @param  array{configured_path: string|null, resolved_path: string|null, readable: bool}  $cityDatabase
     * @param  array{configured_path: string|null, resolved_path: string|null, readable: bool}  $asnDatabase
     */
    private function displayDatabaseStatus(array $cityDatabase, array $asnDatabase): void
    {
        $this->table(
            ['Database', 'Configured Path', 'Resolved Path', 'Readable', 'Columns'],
            [
                [
                    'City',
                    $this->valueOrDash($cityDatabase['configured_path']),
                    $this->valueOrDash($cityDatabase['resolved_path']),
                    $this->yesNo($cityDatabase['readable']),
                    'country_code, region, city',
                ],
                [
                    'ASN',
                    $this->valueOrDash($asnDatabase['configured_path']),
                    $this->valueOrDash($asnDatabase['resolved_path']),
                    $this->yesNo($asnDatabase['readable']),
                    'network_asn, network_organization',
                ],
            ]
        );
    }

    /**
     * @param  array{configured_path: string|null, resolved_path: string|null, readable: bool}  $cityDatabase
     * @param  array{configured_path: string|null, resolved_path: string|null, readable: bool}  $asnDatabase
     */
    private function readinessExitCode(bool $enabled, string $provider, array $cityDatabase, array $asnDatabase): int
    {
        if (! $enabled) {
            $this->warn('GeoIP enrichment disabled. login_events GeoIP/ASN columns will remain null.');

            return self::SUCCESS;
        }

        if ($provider !== 'maxmind') {
            $this->error('GeoIP provider enabled but unsupported. Supported provider: maxmind.');

            return self::FAILURE;
        }

        if (! $cityDatabase['readable'] && ! $asnDatabase['readable']) {
            $this->error('GeoIP enabled but no readable City or ASN database was found.');

            return self::FAILURE;
        }

        if ($cityDatabase['readable'] && $asnDatabase['readable']) {
            $this->info('GeoIP/ASN enrichment ready.');

            return self::SUCCESS;
        }

        $message = 'GeoIP partially ready. One database is readable and the other one is missing.';

        if ($this->failOnMissing()) {
            $this->error($message);

            return self::FAILURE;
        }

        $this->warn($message);

        return self::SUCCESS;
    }

    /**
     * @param  array{configured_path: string|null, resolved_path: string|null, readable: bool}  $cityDatabase
     * @param  array{configured_path: string|null, resolved_path: string|null, readable: bool}  $asnDatabase
     */
    private function displayLookup(
        bool $enabled,
        string $provider,
        array $cityDatabase,
        array $asnDatabase,
        IpGeolocationContext $ipGeolocationContext
    ): void {
        if (! $enabled || $provider !== 'maxmind' || (! $cityDatabase['readable'] && ! $asnDatabase['readable'])) {
            $this->line('Sample lookup skipped.');

            return;
        }

        $ipAddress = $this->optionString('ip');

        if (! $this->isPublicIpAddress($ipAddress)) {
            $this->warn('Sample lookup skipped because --ip is not a public IP address.');

            return;
        }

        $context = $ipGeolocationContext->fromIp($ipAddress);

        $this->table(
            ['Lookup Field', 'Value'],
            [
                ['ip_address', $ipAddress],
                ['country_code', $this->valueOrDash($context['country_code'] ?? null)],
                ['region', $this->valueOrDash($context['region'] ?? null)],
                ['city', $this->valueOrDash($context['city'] ?? null)],
                ['network_asn', $this->valueOrDash($context['network_asn'] ?? null)],
                ['network_organization', $this->valueOrDash($context['network_organization'] ?? null)],
            ]
        );
    }

    /**
     * @return array{configured_path: string|null, resolved_path: string|null, readable: bool}
     */
    private function databaseStatus(string $key): array
    {
        $configuredPath = $this->configString($key);
        $resolvedPath = $configuredPath !== null ? $this->resolvedPath($configuredPath) : null;

        return [
            'configured_path' => $configuredPath,
            'resolved_path' => $resolvedPath,
            'readable' => $resolvedPath !== null && is_file($resolvedPath) && is_readable($resolvedPath),
        ];
    }

    private function resolvedPath(string $path): string
    {
        return $this->isAbsolutePath($path) ? $path : base_path($path);
    }

    private function configString(string $key): ?string
    {
        $value = config("auth.audit.login_events.enrichment.geoip.{$key}");

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = Str::of((string) $value)->trim()->toString();

        return $value !== '' ? $value : null;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = Str::of((string) $value)->trim()->toString();

        return $value !== '' ? $value : null;
    }

    private function enabled(): bool
    {
        return (bool) config('auth.audit.login_events.enrichment.geoip.enabled', false);
    }

    private function provider(): string
    {
        return $this->configString('provider') ?? '-';
    }

    private function cacheTtlSeconds(): int
    {
        return max(60, (int) config('auth.audit.login_events.enrichment.geoip.cache_ttl_seconds', 86400));
    }

    private function noLookup(): bool
    {
        return (bool) $this->option('no-lookup');
    }

    private function failOnMissing(): bool
    {
        return (bool) $this->option('fail-on-missing');
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

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function valueOrDash(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '-';
        }

        $value = Str::of((string) $value)->trim()->toString();

        return $value !== '' ? $value : '-';
    }
}
