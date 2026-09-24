<?php

namespace App\Data\Esign;

use App\Exceptions\Esign\EsignInvariantViolationException;
use Carbon\CarbonImmutable;

final readonly class SigningSessionData
{
    public function __construct(
        public string $sessionId,
        public int $documentId,
        public int $workflowId,
        public int $workflowLockVersion,
        public int $stepId,
        public int $sourceArtifactId,
        public int $sourceArtifactVersion,
        public string $sourceArtifactSha256,
        public int $actorUserId,
        public int $realUserPositionId,
        public string $effectiveRoleCode,
        public int $effectiveUnitKerjaId,
        public int $effectiveInstansiId,
        public int $selectedYear,
        public int $signerUserId,
        public int $signerUserPositionId,
        public string $signerName,
        public string $maskedNik,
        public bool $placementRequired,
        public string $signatureState,
        public int $verifiedSignatureCount,
        /** @var list<array{page: int, width: float, height: float, rotation: int}> */
        public array $pageGeometries,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $expiresAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'document_id' => $this->documentId,
            'workflow_id' => $this->workflowId,
            'workflow_lock_version' => $this->workflowLockVersion,
            'step_id' => $this->stepId,
            'source_artifact_id' => $this->sourceArtifactId,
            'source_artifact_version' => $this->sourceArtifactVersion,
            'source_artifact_sha256' => $this->sourceArtifactSha256,
            'actor_user_id' => $this->actorUserId,
            'real_user_position_id' => $this->realUserPositionId,
            'effective_role_code' => $this->effectiveRoleCode,
            'effective_unit_kerja_id' => $this->effectiveUnitKerjaId,
            'effective_instansi_id' => $this->effectiveInstansiId,
            'selected_year' => $this->selectedYear,
            'signer_user_id' => $this->signerUserId,
            'signer_user_position_id' => $this->signerUserPositionId,
            'signer_name' => $this->signerName,
            'masked_nik' => $this->maskedNik,
            'placement_required' => $this->placementRequired,
            'signature_state' => $this->signatureState,
            'verified_signature_count' => $this->verifiedSignatureCount,
            'page_geometries' => $this->pageGeometries,
            'created_at' => $this->createdAt->toIso8601String(),
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toClientArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'signer_name' => $this->signerName,
            'masked_nik' => $this->maskedNik,
            'placement_required' => $this->placementRequired,
            'artifact_version' => $this->sourceArtifactVersion,
            'artifact_sha256' => $this->sourceArtifactSha256,
            'signature_state' => $this->signatureState,
            'verified_signature_count' => $this->verifiedSignatureCount,
            'pages' => $this->pageGeometries,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        try {
            $session = new self(
                sessionId: self::string($payload, 'session_id'),
                documentId: self::integer($payload, 'document_id'),
                workflowId: self::integer($payload, 'workflow_id'),
                workflowLockVersion: self::integer($payload, 'workflow_lock_version'),
                stepId: self::integer($payload, 'step_id'),
                sourceArtifactId: self::integer($payload, 'source_artifact_id'),
                sourceArtifactVersion: self::integer($payload, 'source_artifact_version'),
                sourceArtifactSha256: self::string($payload, 'source_artifact_sha256'),
                actorUserId: self::integer($payload, 'actor_user_id'),
                realUserPositionId: self::integer($payload, 'real_user_position_id'),
                effectiveRoleCode: self::string($payload, 'effective_role_code'),
                effectiveUnitKerjaId: self::integer($payload, 'effective_unit_kerja_id'),
                effectiveInstansiId: self::integer($payload, 'effective_instansi_id'),
                selectedYear: self::integer($payload, 'selected_year'),
                signerUserId: self::integer($payload, 'signer_user_id'),
                signerUserPositionId: self::integer($payload, 'signer_user_position_id'),
                signerName: self::string($payload, 'signer_name'),
                maskedNik: self::string($payload, 'masked_nik'),
                placementRequired: self::boolean($payload, 'placement_required'),
                signatureState: self::string($payload, 'signature_state'),
                verifiedSignatureCount: self::integer($payload, 'verified_signature_count'),
                pageGeometries: self::pageGeometries($payload),
                createdAt: CarbonImmutable::parse(self::string($payload, 'created_at')),
                expiresAt: CarbonImmutable::parse(self::string($payload, 'expires_at')),
            );

            if (preg_match('/\A[a-f0-9]{64}\z/i', $session->sourceArtifactSha256) !== 1
                || ! in_array($session->signatureState, ['unsigned', 'signed'], true)
                || $session->verifiedSignatureCount < 0
                || $session->pageGeometries === []
                || $session->createdAt->isAfter($session->expiresAt)) {
                throw new EsignInvariantViolationException('signing_session_payload_invalid');
            }

            return $session;
        } catch (\Throwable $exception) {
            throw new EsignInvariantViolationException('signing_session_payload_invalid');
        }
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        if (! is_int($payload[$key] ?? null)) {
            throw new EsignInvariantViolationException('signing_session_payload_invalid');
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        if (! is_string($payload[$key] ?? null) || $payload[$key] === '') {
            throw new EsignInvariantViolationException('signing_session_payload_invalid');
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload */
    private static function boolean(array $payload, string $key): bool
    {
        if (! is_bool($payload[$key] ?? null)) {
            throw new EsignInvariantViolationException('signing_session_payload_invalid');
        }

        return $payload[$key];
    }

    /** @param array<string, mixed> $payload */
    private static function pageGeometries(array $payload): array
    {
        $pages = $payload['page_geometries'] ?? null;

        if (! is_array($pages) || $pages === []) {
            throw new EsignInvariantViolationException('signing_session_payload_invalid');
        }

        return array_map(
            static fn (mixed $page): array => PdfPageGeometryData::fromArray(
                is_array($page) ? $page : [],
            )->toArray(),
            array_values($pages),
        );
    }
}
