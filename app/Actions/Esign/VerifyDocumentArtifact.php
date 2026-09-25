<?php

namespace App\Actions\Esign;

use App\Contracts\Esign\EsignGateway;
use App\Data\Esign\SignatureInformationData;
use App\Data\Esign\VerificationResultData;
use App\Data\Esign\VerifyPdfData;
use App\Models\Esign\DocumentArtifact;
use App\Services\Esign\DocumentArtifactIntegrityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

final class VerifyDocumentArtifact
{
    public function __construct(
        private readonly DocumentArtifactIntegrityService $integrity,
        private readonly EsignGateway $gateway,
        private readonly CacheFactory $cache,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * @return array{
     *     artifact: array{public_id: string, version: int, type: string, original_name: string|null},
     *     document: array{number: string|null, type: string|null, payment_type: string|null},
     *     verification: array{
     *         status: 'valid'|'invalid'|'no_signature',
     *         conclusion: string,
     *         description: string|null,
     *         signature_count: int,
     *         signatures: list<array{
     *             index: int,
     *             signer_name: string,
     *             signed_at: string|null,
     *             reason: string|null,
     *             location: string|null,
     *             integrity_valid: bool|null,
     *             certificate_trusted: bool|null,
     *             long_term_validation: bool|null
     *         }>,
     *         checked_at: string,
     *         cached: bool
     *     }
     * }
     */
    public function handle(DocumentArtifact $artifact): array
    {
        $pdfContents = $this->integrity->readVerifiedPdfContents($artifact);
        $cache = $this->cacheRepository();
        $cacheKey = $this->cacheKey($artifact);
        $cached = $cache->get($cacheKey);

        if ($this->isValidCachedSummary($cached)) {
            return $this->response($artifact, $cached, true);
        }

        $verification = $this->gateway->verify(new VerifyPdfData($pdfContents));
        $summary = $this->normalizeVerification($verification);

        $cache->put($cacheKey, $summary, now()->addMinutes($this->cacheTtlMinutes()));

        return $this->response($artifact, $summary, false);
    }

    /**
     * @return array{
     *     status: 'valid'|'invalid'|'no_signature',
     *     conclusion: string,
     *     description: string|null,
     *     signature_count: int,
     *     signatures: list<array{
     *         index: int,
     *         signer_name: string,
     *         signed_at: string|null,
     *         reason: string|null,
     *         location: string|null,
     *         integrity_valid: bool|null,
     *         certificate_trusted: bool|null,
     *         long_term_validation: bool|null
     *     }>,
     *     checked_at: string
     * }
     */
    private function normalizeVerification(VerificationResultData $verification): array
    {
        $status = match (true) {
            $verification->signatureCount === 0 => 'no_signature',
            $verification->isValid() => 'valid',
            default => 'invalid',
        };

        return [
            'status' => $status,
            'conclusion' => Str::limit($verification->conclusion, 100, ''),
            'description' => $this->safeString($verification->description, 500),
            'signature_count' => $verification->signatureCount,
            'signatures' => array_map(
                fn (SignatureInformationData $signature, int $index): array => [
                    'index' => $index,
                    'signer_name' => $this->signerName($signature),
                    'signed_at' => $this->date($signature->signatureDate),
                    'reason' => $this->safeString($signature->reason, 500),
                    'location' => $this->safeString($signature->location, 255),
                    'integrity_valid' => $signature->integrityValid,
                    'certificate_trusted' => $signature->certificateTrusted,
                    'long_term_validation' => $signature->longTermValidation,
                ],
                $verification->signatures,
                array_keys($verification->signatures),
            ),
            'checked_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    private function signerName(SignatureInformationData $signature): string
    {
        $name = $this->safeString($signature->signerName, 255);

        if ($name !== null) {
            return $name;
        }

        foreach ($signature->certificateDetails as $certificate) {
            $commonName = $this->safeString($certificate->commonName, 255);

            if ($commonName !== null) {
                return $commonName;
            }
        }

        return 'Tidak diketahui';
    }

    private function safeString(?string $value, int $limit): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $limit, '');
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cacheRepository(): CacheRepository
    {
        $store = $this->config->get('esign.verification.cache_store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    private function cacheKey(DocumentArtifact $artifact): string
    {
        $policyVersion = $this->config->get('esign.verification.policy_version', 'bsre-v2-v1');
        $policyVersion = is_string($policyVersion) && $policyVersion !== ''
            ? $policyVersion
            : 'bsre-v2-v1';

        return 'esign:artifact-verification:'.hash(
            'sha256',
            $policyVersion.'|'.(string) $artifact->file_sha256,
        );
    }

    private function cacheTtlMinutes(): int
    {
        $minutes = $this->config->get('esign.verification.cache_ttl_minutes', 60);

        return is_int($minutes) && $minutes >= 1 && $minutes <= 1440 ? $minutes : 60;
    }

    private function isValidCachedSummary(mixed $value): bool
    {
        if (! is_array($value)
            || ! in_array($value['status'] ?? null, ['valid', 'invalid', 'no_signature'], true)
            || ! is_string($value['conclusion'] ?? null)
            || ! $this->isNullableString($value['description'] ?? null)
            || ! is_int($value['signature_count'] ?? null)
            || ($value['signature_count'] ?? -1) < 0
            || ! is_string($value['checked_at'] ?? null)
            || strtotime($value['checked_at']) === false
            || ! is_array($value['signatures'] ?? null)
            || ! array_is_list($value['signatures'])
            || count($value['signatures']) !== $value['signature_count']) {
            return false;
        }

        foreach ($value['signatures'] as $index => $signature) {
            if (! is_array($signature)
                || ($signature['index'] ?? null) !== $index
                || ! is_string($signature['signer_name'] ?? null)
                || trim($signature['signer_name']) === ''
                || ! $this->isNullableString($signature['signed_at'] ?? null)
                || (is_string($signature['signed_at'] ?? null)
                    && strtotime($signature['signed_at']) === false)
                || ! $this->isNullableString($signature['reason'] ?? null)
                || ! $this->isNullableString($signature['location'] ?? null)
                || ! $this->isNullableBoolean($signature['integrity_valid'] ?? null)
                || ! $this->isNullableBoolean($signature['certificate_trusted'] ?? null)
                || ! $this->isNullableBoolean($signature['long_term_validation'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function isNullableString(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    private function isNullableBoolean(mixed $value): bool
    {
        return $value === null || is_bool($value);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function response(DocumentArtifact $artifact, array $summary, bool $cached): array
    {
        $artifact->loadMissing('document:id,nomor,src_type,payment_type');

        return [
            'artifact' => [
                'public_id' => (string) $artifact->public_id,
                'version' => (int) $artifact->version,
                'type' => $artifact->artifact_type->value,
                'original_name' => $this->safeString($artifact->original_name, 255),
            ],
            'document' => [
                'number' => $this->safeString($artifact->document?->nomor, 255),
                'type' => $this->safeString($artifact->document?->src_type, 50),
                'payment_type' => $this->safeString($artifact->document?->payment_type, 50),
            ],
            'verification' => [
                ...$summary,
                'cached' => $cached,
            ],
        ];
    }
}
