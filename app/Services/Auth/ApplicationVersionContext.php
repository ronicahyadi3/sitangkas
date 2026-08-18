<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

class ApplicationVersionContext
{
    private const int MaxLength = 50;

    private const int MaxVersionLength = 32;

    private const int MaxBuildNumberLength = 16;

    private const int CommitLength = 12;

    public function value(): ?string
    {
        $version = $this->configValue('version', self::MaxVersionLength);
        $buildNumber = $this->configValue('build_number', self::MaxBuildNumberLength);
        $buildCommit = $this->shortCommit(config('auth.audit.login_events.enrichment.application.build_commit'));

        $metadata = array_values(array_filter([
            $buildNumber !== null ? 'build.'.$buildNumber : null,
            $buildCommit !== null ? 'sha.'.$buildCommit : null,
        ]));

        if ($version !== null) {
            return $this->limit($metadata === []
                ? $version
                : $version.'+'.implode('.', $metadata));
        }

        if ($metadata !== []) {
            return $this->limit(implode('.', $metadata));
        }

        return null;
    }

    private function configValue(string $key, int $maxLength): ?string
    {
        return $this->normalize(config("auth.audit.login_events.enrichment.application.{$key}"), $maxLength);
    }

    private function normalize(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = (string) preg_replace('/[^A-Za-z0-9._:+-]+/', '-', $value);
        $value = trim($value, '.:+-');

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $maxLength, '');
    }

    private function shortCommit(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $commit = strtolower(trim((string) $value));

        if ($commit === '') {
            return null;
        }

        $commit = (string) preg_replace('/[^a-f0-9]/', '', $commit);

        if (strlen($commit) < 7) {
            return null;
        }

        return substr($commit, 0, self::CommitLength);
    }

    private function limit(string $value): string
    {
        return Str::limit($value, self::MaxLength, '');
    }
}
