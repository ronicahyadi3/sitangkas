<?php

namespace App\Actions\Esign;

use App\Contracts\Esign\EsignGateway;
use App\Data\Esign\SignaturePropertyData;
use App\Data\Esign\SignRequestData;
use App\Data\Esign\VerificationResultData;
use App\Data\Esign\VerifyPdfData;
use App\Data\Esign\VisibleSignaturePlacementData;
use App\Exceptions\Esign\EsignOperationException;
use App\Services\Esign\QrCodePngGenerator;
use DomainException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JsonException;
use SensitiveParameter;
use Throwable;

final readonly class RunVisibleSigningContractProof
{
    public function __construct(
        private EsignGateway $gateway,
        private QrCodePngGenerator $qrCodeGenerator,
        private FilesystemManager $filesystems,
    ) {}

    /**
     * @param  list<VisibleSignaturePlacementData>  $placements
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function handle(
        string $runUuid,
        string $sourceName,
        #[SensitiveParameter] string $sourcePdfContents,
        #[SensitiveParameter] string $nik,
        #[SensitiveParameter] string $passphrase,
        array $placements,
        string $verificationBaseUrl,
    ): array {
        $maximumOperations = max(1, (int) config('esign.contract_proof.max_operations', 5));

        if (! Str::isUuid($runUuid)
            || $placements === []
            || count($placements) > $maximumOperations
            || ! str_starts_with($sourcePdfContents, '%PDF-')) {
            throw new DomainException('Visible signing contract proof input is invalid.');
        }

        foreach ($placements as $placement) {
            if (! $placement instanceof VisibleSignaturePlacementData) {
                throw new DomainException('Visible signing contract proof placement is invalid.');
            }
        }

        $diskName = $this->configString('esign.contract_proof.disk', 'private');
        $directory = $this->proofDirectory($runUuid);
        $disk = $this->filesystems->disk($diskName);
        $currentPdfContents = $sourcePdfContents;
        $report = [
            'schema_version' => 1,
            'mode' => 'live_visible_contract_proof',
            'status' => 'running',
            'run_uuid' => $runUuid,
            'created_at' => $this->now()->toIso8601String(),
            'source' => [
                'name' => basename($sourceName),
                'size' => strlen($sourcePdfContents),
                'sha256' => hash('sha256', $sourcePdfContents),
            ],
            'operation_strategy' => 'sequential_one_visible_property_one_pdf',
            'operation_count' => count($placements),
            'completed_operation_count' => 0,
            'private_disk' => $diskName,
            'private_directory' => $directory,
            'baseline_verification' => null,
            'operations' => [],
            'failure' => null,
        ];

        $this->writeReport($disk, $directory, $report);

        try {
            $baselineVerification = $this->gateway->verify(new VerifyPdfData(
                pdfContents: $currentPdfContents,
                correlationId: (string) Str::uuid(),
            ));
            $baselineSignatureCount = $baselineVerification->signatureCount;
            $report['baseline_verification'] = $this->verificationSummary($baselineVerification);
            $this->writeReport($disk, $directory, $report);

            foreach ($placements as $index => $placement) {
                $operationNumber = $index + 1;
                $publicId = (string) Str::uuid();
                $verificationUrl = rtrim($verificationBaseUrl, '/').'/'.$publicId;
                $qrCode = $this->qrCodeGenerator->generate(
                    data: $verificationUrl,
                    size: max(128, (int) config('esign.contract_proof.qr_size_pixels', 300)),
                    margin: max(4, (int) config('esign.contract_proof.qr_margin_pixels', 12)),
                );
                $qrPath = $directory.'/'.sprintf('qr-%02d.png', $operationNumber);
                $signedPdfPath = $directory.'/'.sprintf('signed-operation-%02d.pdf', $operationNumber);
                $inputSize = strlen($currentPdfContents);
                $inputSha256 = hash('sha256', $currentPdfContents);

                $this->put($disk, $qrPath, $qrCode);

                $report['operations'][] = [
                    'operation' => $operationNumber,
                    'status' => 'prepared',
                    'public_id' => $publicId,
                    'verification_url' => $verificationUrl,
                    'placement' => $placement->toArray(),
                    'qr' => [
                        'path' => $qrPath,
                        'size' => strlen($qrCode),
                        'sha256' => hash('sha256', $qrCode),
                        'profile' => $this->qrCodeGenerator->profile(),
                    ],
                    'input_pdf' => [
                        'size' => $inputSize,
                        'sha256' => $inputSha256,
                    ],
                    'signed_pdf' => null,
                    'sign_response' => null,
                    'verification' => null,
                ];
                $this->writeReport($disk, $directory, $report);

                $signResult = $this->gateway->sign(new SignRequestData(
                    nik: $nik,
                    passphrase: $passphrase,
                    pdfContents: $currentPdfContents,
                    correlationId: (string) Str::uuid(),
                    signatureProperties: [
                        SignaturePropertyData::visible(
                            imageContents: $qrCode,
                            pageNumber: $placement->pageNumber,
                            originX: $placement->originX,
                            originY: $placement->originY,
                            width: $placement->width,
                            height: $placement->height,
                            location: $this->nullableConfigString('services.bsre_esign.location'),
                            reason: $this->nullableConfigString('services.bsre_esign.default_reason'),
                        ),
                    ],
                ));

                $currentPdfContents = $signResult->signedPdfContents();
                $this->put($disk, $signedPdfPath, $currentPdfContents);

                $report['operations'][$index]['status'] = 'signed_unverified';
                $report['operations'][$index]['signed_pdf'] = [
                    'path' => $signedPdfPath,
                    'size' => $signResult->pdfSize,
                    'sha256' => $signResult->pdfSha256,
                ];
                $report['operations'][$index]['sign_response'] = $signResult->toArray();
                $this->writeReport($disk, $directory, $report);

                $verification = $this->gateway->verify(new VerifyPdfData(
                    pdfContents: $currentPdfContents,
                    correlationId: (string) Str::uuid(),
                ));
                $expectedSignatureCount = $baselineSignatureCount + $operationNumber;

                if (! $verification->isValid() || $verification->signatureCount !== $expectedSignatureCount) {
                    throw new DomainException('Visible signing verification invariant failed.');
                }

                $report['operations'][$index]['status'] = 'verified';
                $report['operations'][$index]['verification'] = $this->verificationSummary($verification);
                $report['completed_operation_count'] = $operationNumber;
                $this->writeReport($disk, $directory, $report);
            }

            $finalPdfPath = $directory.'/final.pdf';
            $this->put($disk, $finalPdfPath, $currentPdfContents);
            $report['status'] = 'completed';
            $report['completed_at'] = $this->now()->toIso8601String();
            $report['final_pdf'] = [
                'path' => $finalPdfPath,
                'size' => strlen($currentPdfContents),
                'sha256' => hash('sha256', $currentPdfContents),
            ];
            $this->writeReport($disk, $directory, $report);

            return $report;
        } catch (Throwable $exception) {
            $report['status'] = 'failed';
            $report['failed_at'] = $this->now()->toIso8601String();
            $report['failure'] = $this->safeFailure($exception);
            $this->writeReport($disk, $directory, $report);

            throw $exception;
        }
    }

    /** @return array<string, bool|int|string|null> */
    private function verificationSummary(VerificationResultData $verification): array
    {
        return [
            'signature_count' => $verification->signatureCount,
            'conclusion' => $verification->conclusion,
            'valid' => $verification->isValid(),
            'http_status' => $verification->httpStatus,
            'latency_ms' => $verification->latencyMs,
            'correlation_id' => $verification->correlationId,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    private function safeFailure(Throwable $exception): array
    {
        if ($exception instanceof EsignOperationException) {
            return $exception->context();
        }

        return [
            'error_code' => $exception instanceof DomainException
                ? 'contract_proof.verification_invariant_failed'
                : 'contract_proof.internal_error',
            'exception_class' => $exception::class,
        ];
    }

    /** @param array<string, mixed> $report */
    private function writeReport(FilesystemAdapter $disk, string $directory, array $report): void
    {
        try {
            $contents = json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new DomainException('Unable to encode contract proof report.', previous: $exception);
        }

        $this->put($disk, $directory.'/report.json', $contents.PHP_EOL);
    }

    private function put(FilesystemAdapter $disk, string $path, string $contents): void
    {
        if (! $disk->put($path, $contents)) {
            throw new DomainException('Unable to persist a private contract proof artifact.');
        }
    }

    private function proofDirectory(string $runUuid): string
    {
        $root = trim($this->configString('esign.contract_proof.root', 'esign-contract-proofs'), '/\\');

        if ($root === '' || str_contains($root, '..')) {
            throw new DomainException('Contract proof storage root is invalid.');
        }

        return $root.'/'.$this->now()->format('Y/m').'/'.$runUuid;
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function nullableConfigString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function now(): Carbon
    {
        return now($this->configString('esign.artifacts.timezone', 'Asia/Jakarta'));
    }
}
