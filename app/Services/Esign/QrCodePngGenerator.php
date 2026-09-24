<?php

namespace App\Services\Esign;

use App\Exceptions\Esign\EsignInvariantViolationException;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Throwable;

final class QrCodePngGenerator
{
    /**
     * @var array{
     *     version: string,
     *     logo_path: string,
     *     logo_sha256: string,
     *     logo_width_ratio: float,
     *     logo_punchout_background: bool,
     *     error_correction_level: string
     * }|null
     */
    private ?array $verifiedProfile = null;

    public function __construct(
        private ConfigRepository $config,
        private Application $application,
    ) {}

    public function generate(string $data, int $size = 300, int $margin = 12): string
    {
        if (trim($data) === '' || $size < 128 || $size > 1024 || $margin < 4 || $margin * 4 >= $size) {
            throw new InvalidArgumentException('QR code configuration is invalid.');
        }

        $profile = $this->verifiedProfile();
        $logoWidth = (int) round($size * $profile['logo_width_ratio']);

        try {
            $png = (new Builder(
                writer: new PngWriter,
                writerOptions: [
                    PngWriter::WRITER_OPTION_COMPRESSION_LEVEL => 9,
                ],
                validateResult: false,
                data: $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size - (2 * $margin),
                margin: $margin,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                logoPath: $profile['logo_path'],
                logoResizeToWidth: $logoWidth,
                logoPunchoutBackground: $profile['logo_punchout_background'],
            ))->build()->getString();
        } catch (Throwable) {
            throw new EsignInvariantViolationException('esign_qr_generation_failed');
        }

        $imageInfo = @getimagesizefromstring($png);

        if (! str_starts_with($png, "\x89PNG\r\n\x1a\n")
            || ! is_array($imageInfo)
            || ($imageInfo['mime'] ?? null) !== 'image/png'
            || ($imageInfo[0] ?? null) !== $size
            || ($imageInfo[1] ?? null) !== $size) {
            throw new EsignInvariantViolationException('esign_qr_output_invalid');
        }

        return $png;
    }

    /**
     * @return array{
     *     version: string,
     *     logo_sha256: string,
     *     logo_width_ratio: float,
     *     logo_punchout_background: bool,
     *     error_correction_level: string
     * }
     */
    public function profile(): array
    {
        $profile = $this->verifiedProfile();

        return [
            'version' => $profile['version'],
            'logo_sha256' => $profile['logo_sha256'],
            'logo_width_ratio' => $profile['logo_width_ratio'],
            'logo_punchout_background' => $profile['logo_punchout_background'],
            'error_correction_level' => $profile['error_correction_level'],
        ];
    }

    /**
     * @return array{
     *     version: string,
     *     logo_path: string,
     *     logo_sha256: string,
     *     logo_width_ratio: float,
     *     logo_punchout_background: bool,
     *     error_correction_level: string
     * }
     */
    private function verifiedProfile(): array
    {
        if ($this->verifiedProfile !== null) {
            return $this->verifiedProfile;
        }

        $version = $this->config->get('esign.visible_editor.qr_profile_version');
        $relativePath = $this->config->get('esign.visible_editor.qr_logo_relative_path');
        $expectedSha256 = $this->config->get('esign.visible_editor.qr_logo_sha256');
        $logoWidthRatio = $this->config->get('esign.visible_editor.qr_logo_width_ratio');
        $punchoutBackground = $this->config->get('esign.visible_editor.qr_logo_punchout_background');

        if (! is_string($version)
            || preg_match('/\A[a-z0-9][a-z0-9._-]{2,49}\z/', $version) !== 1
            || ! is_string($relativePath)
            || ! $this->validRelativePath($relativePath)
            || ! is_string($expectedSha256)
            || preg_match('/\A[a-f0-9]{64}\z/', $expectedSha256) !== 1
            || (! is_float($logoWidthRatio) && ! is_int($logoWidthRatio))
            || (float) $logoWidthRatio < 0.15
            || (float) $logoWidthRatio > 0.28
            || ! is_bool($punchoutBackground)) {
            throw new EsignInvariantViolationException('esign_qr_profile_invalid');
        }

        $logoPath = $this->application->publicPath($relativePath);
        $actualSha256 = is_file($logoPath) && is_readable($logoPath)
            ? hash_file('sha256', $logoPath)
            : false;
        $logoInfo = is_string($actualSha256) ? @getimagesize($logoPath) : false;

        if (! is_string($actualSha256)
            || ! hash_equals($expectedSha256, $actualSha256)
            || ! is_array($logoInfo)
            || ($logoInfo['mime'] ?? null) !== 'image/png'
            || ! is_int($logoInfo[0] ?? null)
            || ! is_int($logoInfo[1] ?? null)
            || $logoInfo[0] < 128
            || $logoInfo[1] < 128
            || $logoInfo[0] > 2048
            || $logoInfo[1] > 2048
            || ! function_exists('imagecreatefromstring')) {
            throw new EsignInvariantViolationException('esign_qr_logo_integrity_invalid');
        }

        return $this->verifiedProfile = [
            'version' => $version,
            'logo_path' => $logoPath,
            'logo_sha256' => $actualSha256,
            'logo_width_ratio' => (float) $logoWidthRatio,
            'logo_punchout_background' => $punchoutBackground,
            'error_correction_level' => 'high',
        ];
    }

    private function validRelativePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, '..')
            && ! str_contains($path, ':')
            && preg_match('/\A[a-zA-Z0-9._-]+(?:\/[a-zA-Z0-9._-]+)*\z/', $path) === 1;
    }
}
