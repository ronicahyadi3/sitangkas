<?php

namespace App\Console\Commands\Esign;

use App\Actions\Esign\RunVisibleSigningContractProof;
use App\Data\Esign\VisibleSignaturePlacementData;
use App\Exceptions\Esign\EsignOperationException;
use App\Services\Esign\QrCodePngGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Throwable;

#[Signature('esign:prove-visible-contract
    {pdf? : PDF source path; defaults to public/sample belum tte.pdf}
    {--placement=* : Repeatable page,originX,originY,width,height placement}
    {--verify-base-url= : HTTPS base URL encoded into each QR code}
    {--live : Send sequential visible signing requests to BSrE}')]
#[Description('Prepare or run an isolated, private BSrE visible multi-operation contract proof.')]
final class ProveVisibleSigningContractCommand extends Command
{
    public function handle(
        RunVisibleSigningContractProof $contractProof,
        QrCodePngGenerator $qrCodeGenerator,
        FilesystemManager $filesystems,
    ): int {
        $privateReportPath = null;

        try {
            $sourcePath = $this->sourcePath();
            $sourcePdfContents = $this->sourcePdfContents($sourcePath);
            $placements = $this->placements();
            $verificationBaseUrl = $this->verificationBaseUrl();
            $runUuid = (string) Str::uuid();
            $privateReportPath = $this->proofDirectory($runUuid).'/report.json';

            $this->displayPlan($sourcePath, $sourcePdfContents, $placements, $verificationBaseUrl);

            if (! $this->isLive()) {
                $report = $this->writePreflightReport(
                    filesystems: $filesystems,
                    qrCodeGenerator: $qrCodeGenerator,
                    runUuid: $runUuid,
                    sourcePath: $sourcePath,
                    sourcePdfContents: $sourcePdfContents,
                    placements: $placements,
                    verificationBaseUrl: $verificationBaseUrl,
                );

                $this->info('Preflight selesai tanpa menghubungi BSrE.');
                $this->line('Private report: '.$report['private_directory'].'/report.json');
                $this->warn('Gunakan --live hanya dari terminal interaktif setelah feature gate diaktifkan.');

                return self::SUCCESS;
            }

            if (! (bool) config('esign.contract_proof.enabled', false)) {
                $this->error('Live contract proof dinonaktifkan. Set SIGNATURE_CONTRACT_PROOF_ENABLED=true lalu refresh config.');

                return self::FAILURE;
            }

            if (! (bool) config('services.bsre_esign.enabled', false)) {
                $this->error('BSrE eSign dinonaktifkan pada konfigurasi aplikasi.');

                return self::FAILURE;
            }

            if (! $this->input->isInteractive()) {
                $this->error('Mode live wajib dijalankan dari terminal interaktif.');

                return self::FAILURE;
            }

            $nik = trim((string) $this->secret('NIK signer (16 digit)'));
            $passphrase = (string) $this->secret('Passphrase signer');

            if (preg_match('/^\d{16}$/D', $nik) !== 1 || trim($passphrase) === '') {
                $this->error('NIK atau passphrase tidak valid. Credential tidak disimpan.');

                return self::FAILURE;
            }

            $confirmationPhrase = 'SIGN VISIBLE '.count($placements);
            $confirmation = trim((string) $this->ask("Ketik '{$confirmationPhrase}' untuk mengirim request produksi"));

            if (! hash_equals($confirmationPhrase, $confirmation)) {
                $this->warn('Live contract proof dibatalkan tanpa request ke BSrE.');

                return self::SUCCESS;
            }

            $report = $contractProof->handle(
                runUuid: $runUuid,
                sourceName: basename($sourcePath),
                sourcePdfContents: $sourcePdfContents,
                nik: $nik,
                passphrase: $passphrase,
                placements: $placements,
                verificationBaseUrl: $verificationBaseUrl,
            );

            $this->info('Visible signing contract proof berhasil dan seluruh operasi telah diverifikasi.');
            $this->line('Private final PDF: '.$report['final_pdf']['path']);
            $this->line('Private report: '.$report['private_directory'].'/report.json');

            return self::SUCCESS;
        } catch (EsignOperationException $exception) {
            $this->error('BSrE contract proof gagal: '.$exception->errorCode->value);

            if ($privateReportPath !== null) {
                $this->line('Private report: '.$privateReportPath);
            }

            if ($exception->correlationId !== null) {
                $this->line('Correlation ID: '.$exception->correlationId);
            }

            return self::FAILURE;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (Throwable $exception) {
            $this->error('Contract proof gagal secara aman ('.$exception::class.'). Periksa report private dan log terproteksi.');

            if ($privateReportPath !== null) {
                $this->line('Private report: '.$privateReportPath);
            }

            return self::FAILURE;
        }
    }

    private function sourcePath(): string
    {
        $argument = $this->argument('pdf');
        $path = is_string($argument) && trim($argument) !== ''
            ? trim($argument)
            : public_path('sample belum tte.pdf');

        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        $realPath = realpath($path);

        if (! is_string($realPath) || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new InvalidArgumentException('PDF source tidak ditemukan atau tidak dapat dibaca.');
        }

        return $realPath;
    }

    private function sourcePdfContents(string $sourcePath): string
    {
        $maximumBytes = max(1, (int) config('esign.processing.max_file_size_mb', 50)) * 1024 * 1024;
        $fileSize = filesize($sourcePath);

        if (! is_int($fileSize) || $fileSize < 5 || $fileSize > $maximumBytes) {
            throw new InvalidArgumentException('Ukuran PDF source tidak diizinkan.');
        }

        $contents = file_get_contents($sourcePath);

        if (! is_string($contents) || ! str_starts_with($contents, '%PDF-')) {
            throw new InvalidArgumentException('Source bukan file PDF yang valid.');
        }

        return $contents;
    }

    /** @return list<VisibleSignaturePlacementData> */
    private function placements(): array
    {
        $values = $this->option('placement');
        $values = is_array($values) ? $values : [];

        if ($values === []) {
            $values = ['1,36,36,100,100', '2,36,36,100,100'];
        }

        $maximumOperations = max(1, (int) config('esign.contract_proof.max_operations', 5));

        if (count($values) > $maximumOperations) {
            throw new InvalidArgumentException("Jumlah placement melebihi batas {$maximumOperations}.");
        }

        return array_map(function (mixed $value): VisibleSignaturePlacementData {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Format placement harus page,originX,originY,width,height.');
            }

            $parts = array_map(trim(...), explode(',', $value));

            if (count($parts) !== 5 || preg_match('/^\d+$/D', $parts[0]) !== 1) {
                throw new InvalidArgumentException("Placement '{$value}' tidak valid.");
            }

            foreach (array_slice($parts, 1) as $number) {
                if (! is_numeric($number)) {
                    throw new InvalidArgumentException("Placement '{$value}' tidak valid.");
                }
            }

            $numbers = array_map(floatval(...), array_slice($parts, 1));

            if (max($numbers) > 20000) {
                throw new InvalidArgumentException("Placement '{$value}' melampaui batas aman.");
            }

            return new VisibleSignaturePlacementData(
                pageNumber: (int) $parts[0],
                originX: $numbers[0],
                originY: $numbers[1],
                width: $numbers[2],
                height: $numbers[3],
            );
        }, $values);
    }

    private function verificationBaseUrl(): string
    {
        $option = $this->option('verify-base-url');
        $url = is_string($option) && trim($option) !== ''
            ? trim($option)
            : (string) config(
                'esign.contract_proof.verification_base_url',
                'https://sitangkas.malangkota.go.id/verify',
            );
        $parts = parse_url($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Verification base URL harus berupa URL HTTPS tanpa credential, query, atau fragment.');
        }

        return rtrim($url, '/');
    }

    /** @param list<VisibleSignaturePlacementData> $placements */
    private function displayPlan(
        string $sourcePath,
        string $sourcePdfContents,
        array $placements,
        string $verificationBaseUrl,
    ): void {
        $this->table(['Contract proof', 'Value'], [
            ['mode', $this->isLive() ? 'LIVE' : 'PREFLIGHT'],
            ['source', basename($sourcePath)],
            ['source size', number_format(strlen($sourcePdfContents)).' bytes'],
            ['source SHA-256', hash('sha256', $sourcePdfContents)],
            ['operations', (string) count($placements)],
            ['strategy', '1 visible property + 1 PDF per sequential request'],
            ['QR base URL', $verificationBaseUrl],
        ]);
    }

    /**
     * @param  list<VisibleSignaturePlacementData>  $placements
     * @return array<string, mixed>
     */
    private function writePreflightReport(
        FilesystemManager $filesystems,
        QrCodePngGenerator $qrCodeGenerator,
        string $runUuid,
        string $sourcePath,
        string $sourcePdfContents,
        array $placements,
        string $verificationBaseUrl,
    ): array {
        $diskName = $this->configString('esign.contract_proof.disk', 'private');
        $directory = $this->proofDirectory($runUuid);
        $disk = $filesystems->disk($diskName);
        $operations = [];

        foreach ($placements as $index => $placement) {
            $operationNumber = $index + 1;
            $publicId = (string) Str::uuid();
            $verificationUrl = $verificationBaseUrl.'/'.$publicId;
            $qrCode = $qrCodeGenerator->generate(
                data: $verificationUrl,
                size: max(128, (int) config('esign.contract_proof.qr_size_pixels', 300)),
                margin: max(4, (int) config('esign.contract_proof.qr_margin_pixels', 12)),
            );
            $qrPath = $directory.'/'.sprintf('qr-%02d.png', $operationNumber);
            $this->put($disk, $qrPath, $qrCode);
            $operations[] = [
                'operation' => $operationNumber,
                'public_id' => $publicId,
                'verification_url' => $verificationUrl,
                'placement' => $placement->toArray(),
                'qr' => [
                    'path' => $qrPath,
                    'size' => strlen($qrCode),
                    'sha256' => hash('sha256', $qrCode),
                    'profile' => $qrCodeGenerator->profile(),
                ],
            ];
        }

        $report = [
            'schema_version' => 1,
            'mode' => 'preflight',
            'status' => 'ready',
            'run_uuid' => $runUuid,
            'created_at' => $this->now()->toIso8601String(),
            'source' => [
                'name' => basename($sourcePath),
                'size' => strlen($sourcePdfContents),
                'sha256' => hash('sha256', $sourcePdfContents),
            ],
            'operation_strategy' => 'sequential_one_visible_property_one_pdf',
            'operation_count' => count($placements),
            'private_disk' => $diskName,
            'private_directory' => $directory,
            'operations' => $operations,
            'network_request_sent' => false,
        ];
        $this->put($disk, $directory.'/report.json', $this->json($report).PHP_EOL);

        return $report;
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Tidak dapat membuat report contract proof.', previous: $exception);
        }
    }

    private function put(FilesystemAdapter $disk, string $path, string $contents): void
    {
        if (! $disk->put($path, $contents)) {
            throw new InvalidArgumentException('Tidak dapat menyimpan artifact contract proof ke private disk.');
        }
    }

    private function proofDirectory(string $runUuid): string
    {
        $root = trim($this->configString('esign.contract_proof.root', 'esign-contract-proofs'), '/\\');

        if ($root === '' || str_contains($root, '..')) {
            throw new InvalidArgumentException('Contract proof storage root tidak valid.');
        }

        return $root.'/'.$this->now()->format('Y/m').'/'.$runUuid;
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function isLive(): bool
    {
        return (bool) $this->option('live');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function now(): Carbon
    {
        return now($this->configString('esign.artifacts.timezone', 'Asia/Jakarta'));
    }
}
