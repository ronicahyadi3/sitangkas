<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserImportPasswordPolicy;
use App\Exceptions\LegacyImport\LegacyUserImportBlockedException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;

final class LegacyUserImportPasswordPolicyResolver
{
    public const string DevelopmentStrategy = 'development_shared_password';

    public const string ProductionStrategy = 'preserve_legacy_hash_force_change';

    private const int MinimumDevelopmentPasswordLength = 16;

    public function __construct(
        private Application $application,
        private Hasher $hasher,
    ) {}

    public function resolveConfigured(): LegacyUserImportPasswordPolicy
    {
        $execution = config('legacy_import.execution', []);
        $decisions = is_array($execution['decisions'] ?? null)
            ? $execution['decisions']
            : [];
        $overrideConfiguration = is_array($execution['development_password_override'] ?? null)
            ? $execution['development_password_override']
            : [];

        return $this->resolve(
            (string) ($decisions['password_strategy'] ?? ''),
            $overrideConfiguration,
        );
    }

    /**
     * @param  array{enabled?: bool, password?: mixed, force_change?: bool}  $overrideConfiguration
     */
    public function resolve(
        string $configuredStrategy,
        #[\SensitiveParameter] array $overrideConfiguration,
    ): LegacyUserImportPasswordPolicy {
        $environment = (string) $this->application->environment();

        if ($configuredStrategy !== self::ProductionStrategy) {
            throw new LegacyUserImportBlockedException('password_policy', [
                'Strategi password produksi wajib preserve_legacy_hash_force_change.',
            ]);
        }

        if (($overrideConfiguration['enabled'] ?? false) !== true) {
            return new LegacyUserImportPasswordPolicy(
                configuredStrategy: $configuredStrategy,
                effectiveStrategy: self::ProductionStrategy,
                mustChangePassword: true,
                resolvedEnvironment: $environment,
                sharedPasswordHash: null,
            );
        }

        if (! $this->application->environment('local')) {
            throw new LegacyUserImportBlockedException('password_policy', [
                'Shared password development hanya boleh diaktifkan pada environment local.',
            ]);
        }

        $sharedPassword = $overrideConfiguration['password'] ?? null;

        if (! is_string($sharedPassword) || trim($sharedPassword) === '') {
            throw new LegacyUserImportBlockedException('password_policy', [
                'Shared password development wajib diisi ketika override diaktifkan.',
            ]);
        }

        if (mb_strlen($sharedPassword) < self::MinimumDevelopmentPasswordLength) {
            throw new LegacyUserImportBlockedException('password_policy', [
                'Shared password development minimal 16 karakter.',
            ]);
        }

        $sharedPasswordHash = $this->hasher->make($sharedPassword);
        unset($sharedPassword);

        return new LegacyUserImportPasswordPolicy(
            configuredStrategy: $configuredStrategy,
            effectiveStrategy: self::DevelopmentStrategy,
            mustChangePassword: ($overrideConfiguration['force_change'] ?? false) === true,
            resolvedEnvironment: $environment,
            sharedPasswordHash: $sharedPasswordHash,
        );
    }
}
