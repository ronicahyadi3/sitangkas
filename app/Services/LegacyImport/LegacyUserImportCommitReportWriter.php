<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserImportResult;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class LegacyUserImportCommitReportWriter
{
    private const string StatusCompleted = 'completed';

    private const string StatusFailed = 'failed';

    public function __construct(private FilesystemManager $filesystems) {}

    /** @throws JsonException */
    public function writeCompleted(
        LegacyUserImportResult $result,
        DateTimeInterface $startedAt,
    ): string {
        $finishedAt = CarbonImmutable::parse($result->completedAt)->utc();

        return $this->write(
            status: self::StatusCompleted,
            sourceFingerprint: $result->sourceFingerprint,
            report: [
                'schema_version' => 1,
                'generated_at' => CarbonImmutable::now()->utc()->toIso8601String(),
                'mode' => 'commit',
                'status' => self::StatusCompleted,
                'writes_target_database' => true,
                'transaction_committed' => true,
                'started_at' => $this->asUtc($startedAt)->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_ms' => $this->durationMilliseconds($startedAt, $finishedAt),
                'source_fingerprint' => $result->sourceFingerprint,
                'counts' => [
                    'users' => $result->userCount,
                    'canonical_positions' => $result->canonicalPositionCount,
                    'alias_positions' => $result->aliasPositionCount,
                ],
                'decisions' => $result->decisions,
                'validation' => $result->validation,
                'failure' => null,
            ],
        );
    }

    /** @throws JsonException */
    public function writeFailed(
        DateTimeInterface $startedAt,
        string $stage,
        string $failureCode,
        int $blockerCount = 0,
        ?string $sourceFingerprint = null,
    ): string {
        $this->assertSafeIdentifier($stage, 'stage');
        $this->assertSafeIdentifier($failureCode, 'failure code');

        if ($blockerCount < 0) {
            throw new InvalidArgumentException('Blocker count tidak boleh negatif.');
        }

        $finishedAt = CarbonImmutable::now()->utc();

        return $this->write(
            status: self::StatusFailed,
            sourceFingerprint: $sourceFingerprint,
            report: [
                'schema_version' => 1,
                'generated_at' => $finishedAt->toIso8601String(),
                'mode' => 'commit',
                'status' => self::StatusFailed,
                'writes_target_database' => false,
                'transaction_committed' => false,
                'started_at' => $this->asUtc($startedAt)->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_ms' => $this->durationMilliseconds($startedAt, $finishedAt),
                'source_fingerprint' => $sourceFingerprint,
                'counts' => null,
                'decisions' => $this->configuredDecisions(),
                'validation' => null,
                'failure' => [
                    'stage' => $stage,
                    'code' => $failureCode,
                    'blocker_count' => $blockerCount,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $report
     *
     * @throws JsonException
     */
    private function write(string $status, ?string $sourceFingerprint, array $report): string
    {
        $fingerprint = $this->normalizedFingerprint($sourceFingerprint);
        $report['source_fingerprint'] = $fingerprint;
        $diskName = (string) config('legacy_import.reports.disk', 'local');
        $directory = trim(
            (string) config('legacy_import.reports.commit_directory', 'legacy-import/users/commits'),
            '/',
        );

        if ($diskName === '' || $directory === '') {
            throw new RuntimeException('Konfigurasi penyimpanan laporan commit legacy tidak valid.');
        }

        if (config("filesystems.disks.{$diskName}.visibility") === 'public') {
            throw new RuntimeException('Laporan commit legacy tidak boleh disimpan pada disk public.');
        }

        $filename = implode('-', [
            'commit',
            CarbonImmutable::now()->utc()->format('Ymd-His-u'),
            $status,
            Str::substr($fingerprint ?? 'no-fingerprint', 0, 12),
            Str::lower((string) Str::ulid()),
        ]).'.json';
        $reportPath = $directory.'/'.$filename;
        $contents = json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
        $disk = $this->filesystems->disk($diskName);

        if (! $disk->put($reportPath, $contents)) {
            throw new RuntimeException('Laporan commit legacy tidak dapat disimpan ke disk private.');
        }

        return $disk->path($reportPath);
    }

    private function normalizedFingerprint(?string $sourceFingerprint): ?string
    {
        if ($sourceFingerprint === null) {
            return null;
        }

        $normalized = Str::lower(trim($sourceFingerprint));

        if (preg_match('/\A[a-f0-9]{64}\z/', $normalized) !== 1) {
            throw new InvalidArgumentException('Source fingerprint laporan commit harus berupa SHA-256 penuh.');
        }

        return $normalized;
    }

    private function assertSafeIdentifier(string $value, string $label): void
    {
        if (preg_match('/\A[a-z][a-z0-9_:-]{0,99}\z/', $value) !== 1) {
            throw new InvalidArgumentException("{$label} laporan commit tidak valid.");
        }
    }

    private function asUtc(DateTimeInterface $dateTime): CarbonImmutable
    {
        return CarbonImmutable::instance($dateTime)->utc();
    }

    private function durationMilliseconds(
        DateTimeInterface $startedAt,
        DateTimeInterface $finishedAt,
    ): int {
        return max(
            0,
            (int) round($this->asUtc($startedAt)->diffInMilliseconds($finishedAt)),
        );
    }

    /** @return array<string, string> */
    private function configuredDecisions(): array
    {
        $execution = config('legacy_import.execution', []);
        $decisions = is_array($execution['decisions'] ?? null)
            ? $execution['decisions']
            : [];
        $passwordOverride = is_array($execution['development_password_override'] ?? null)
            ? $execution['development_password_override']
            : [];

        return [
            'password_strategy' => (string) ($decisions['password_strategy'] ?? ''),
            'development_password_override' => ($passwordOverride['enabled'] ?? false) === true
                ? 'enabled'
                : 'disabled',
            'force_password_change' => ($passwordOverride['force_change'] ?? false) === true
                ? 'yes'
                : 'no',
            'account_status_strategy' => (string) ($decisions['account_status_strategy'] ?? ''),
            'file_sk_strategy' => (string) ($decisions['file_sk_strategy'] ?? ''),
        ];
    }
}
