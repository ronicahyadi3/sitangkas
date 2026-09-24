<?php

namespace App\Actions\Esign;

use App\Data\Esign\PreparedSigningRenditionData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\SigningSessionConflictException;
use App\Models\User;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Services\Esign\EphemeralPreparedRenditionStore;
use App\Services\Esign\PreparedPdfRenderer;
use App\Services\Esign\QrCodePngGenerator;
use App\Services\Esign\VisibleSigningEditorConfiguration;
use App\Services\Esign\VisibleSigningPlanValidator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

final class PrepareSigningRendition
{
    public function __construct(
        private ResolveSigningSession $resolveSigningSession,
        private VisibleSigningPlanValidator $planValidator,
        private VisibleSigningEditorConfiguration $editorConfiguration,
        private DocumentArtifactIntegrityService $artifactIntegrity,
        private PreparedPdfRenderer $renderer,
        private QrCodePngGenerator $qrCodeGenerator,
        private EphemeralPreparedRenditionStore $renditions,
        private ConfigRepository $config,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(User $user, string $sessionId, array $input): PreparedSigningRenditionData
    {
        if (! Str::isUuid($sessionId)) {
            throw new SigningSessionConflictException('esign.signing_session_invalid');
        }

        try {
            return $this->renditions->lock($sessionId)->block(5, function () use ($user, $sessionId, $input): PreparedSigningRenditionData {
                $context = $this->resolveSigningSession->handle($user, $sessionId);

                if (! $context->session->placementRequired) {
                    throw new SigningSessionConflictException('esign.visible_placement_not_required');
                }

                $plan = $this->planValidator->validate($context->session, $input);
                $createdAt = CarbonImmutable::now();
                $revision = (string) Str::uuid();
                $operationQrImages = [];
                $operations = [];
                $qrProfile = $this->qrCodeGenerator->profile();

                foreach ($plan['placements'] as $placement) {
                    $publicId = (string) Str::uuid();
                    $verificationUrl = $this->verificationUrl($publicId);
                    $qrImage = $this->qrCodeGenerator->generate(
                        data: $verificationUrl,
                        size: $this->qrImageSize(),
                        margin: $this->qrImageMargin(),
                    );
                    $qrPath = $this->preparedDirectory($sessionId, $revision, $createdAt)
                        .'/'.sprintf('qr-%02d.png', (int) $placement['operation_index']);
                    $operationQrImages[$qrPath] = $qrImage;
                    $operations[] = [
                        ...$placement,
                        'verification_public_id' => $publicId,
                        'verification_url' => $verificationUrl,
                        'visual_storage_disk' => $this->preparedDisk(),
                        'visual_file_path' => $qrPath,
                        'qr_size_bytes' => strlen($qrImage),
                        'qr_sha256' => hash('sha256', $qrImage),
                        'qr_profile_version' => $qrProfile['version'],
                        'qr_logo_sha256' => $qrProfile['logo_sha256'],
                    ];
                }
                $rendererVersion = $this->editorConfiguration->rendererVersion();
                $footer = $plan['footer'];

                if (is_array($footer)) {
                    $footer['configuration_sha256'] = hash('sha256', $this->canonicalJson($footer));
                }

                $requestFingerprint = hash('sha256', $this->canonicalJson([
                    'source_artifact_id' => $context->session->sourceArtifactId,
                    'source_artifact_sha256' => $context->session->sourceArtifactSha256,
                    'renderer_version' => $rendererVersion,
                    'signature_operations' => $operations,
                    'footer' => $footer,
                ]));
                $sourcePdf = $this->artifactIntegrity->readVerifiedPdfContents($context->artifact);
                $preparedPdf = $this->renderer->render(
                    sourcePdf: $sourcePdf,
                    pageGeometries: $context->session->pageGeometries,
                    footer: $footer,
                );
                unset($sourcePdf);

                $expiresAt = $context->session->expiresAt;
                if ($expiresAt->isPast()) {
                    throw new SigningSessionConflictException('esign.signing_session_expired');
                }

                $rendition = new PreparedSigningRenditionData(
                    sessionId: $sessionId,
                    revision: $revision,
                    actorUserId: (int) $user->getKey(),
                    sourceArtifactId: $context->session->sourceArtifactId,
                    sourceArtifactSha256: $context->session->sourceArtifactSha256,
                    storageDisk: $this->preparedDisk(),
                    filePath: $this->preparedPath($sessionId, $revision, $createdAt),
                    sizeBytes: strlen($preparedPdf),
                    sha256: hash('sha256', $preparedPdf),
                    requestFingerprint: $requestFingerprint,
                    rendererVersion: $rendererVersion,
                    signatureOperations: $operations,
                    footer: $footer,
                    createdAt: $createdAt,
                    expiresAt: $expiresAt,
                );

                $this->renditions->put($rendition, $preparedPdf, $operationQrImages);
                unset($preparedPdf);

                return $rendition;
            });
        } catch (LockTimeoutException $exception) {
            throw new SigningSessionConflictException('esign.prepared_rendition_busy');
        }
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES);
    }

    private function verificationUrl(string $publicId): string
    {
        $baseUrl = $this->config->get('esign.visible_editor.verification_base_url');

        if (! is_string($baseUrl) || ! str_starts_with($baseUrl, 'https://')) {
            throw new EsignInvariantViolationException('esign_verification_base_url_invalid');
        }

        return rtrim($baseUrl, '/').'/'.$publicId;
    }

    private function preparedDisk(): string
    {
        $disk = $this->config->get('esign.visible_editor.prepared_disk');

        if (! is_string($disk) || $disk === '') {
            throw new EsignInvariantViolationException('prepared_rendition_disk_invalid');
        }

        return $disk;
    }

    private function preparedPath(string $sessionId, string $revision, CarbonImmutable $createdAt): string
    {
        return $this->preparedDirectory($sessionId, $revision, $createdAt).'/prepared.pdf';
    }

    private function preparedDirectory(string $sessionId, string $revision, CarbonImmutable $createdAt): string
    {
        $root = $this->config->get('esign.visible_editor.prepared_root');

        if (! is_string($root)
            || preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', trim($root, '/')) !== 1) {
            throw new EsignInvariantViolationException('prepared_rendition_root_invalid');
        }

        return sprintf(
            '%s/%s/%s/%s/%s/%s',
            trim($root, '/'),
            $createdAt->format('Y'),
            $createdAt->format('m'),
            $createdAt->format('d'),
            $sessionId,
            $revision,
        );
    }

    private function qrImageSize(): int
    {
        $size = $this->config->get('esign.visible_editor.qr_image_size_pixels');

        if (! is_int($size) || $size < 128 || $size > 1024) {
            throw new EsignInvariantViolationException('esign_qr_image_size_invalid');
        }

        return $size;
    }

    private function qrImageMargin(): int
    {
        $margin = $this->config->get('esign.visible_editor.qr_image_margin_pixels');

        if (! is_int($margin) || $margin < 4 || $margin * 4 >= $this->qrImageSize()) {
            throw new EsignInvariantViolationException('esign_qr_image_margin_invalid');
        }

        return $margin;
    }
}
