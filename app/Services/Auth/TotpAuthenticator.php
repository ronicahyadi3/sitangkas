<?php

namespace App\Services\Auth;

use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;
use PragmaRX\Google2FALaravel\Google2FA;
use Throwable;

class TotpAuthenticator
{
    private const QR_CODE_MINIMUM_SIZE = 180;

    private const QR_CODE_MARGIN = 12;

    private const QR_CODE_LOGO_PATH = 'assets/img/Logo_Kota_Malang_color.png';

    private const QR_CODE_LOGO_RATIO = 0.18;

    public function __construct(
        private Google2FA $google2fa,
        private MfaPolicy $mfaPolicy
    ) {}

    public function generateSecret(): string
    {
        $this->configureTotpEngine();

        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $this->configureTotpEngine();

        return $this->google2fa->getQRCodeUrl(
            $this->issuer(),
            $this->accountName($user),
            $secret
        );
    }

    public function inlineQrCode(User $user, string $secret, int $size = 220): ?string
    {
        $this->configureTotpEngine();

        try {
            $qrCodeSize = max(self::QR_CODE_MINIMUM_SIZE, $size);
            $logoPath = public_path(self::QR_CODE_LOGO_PATH);

            if (! extension_loaded('gd') || ! is_file($logoPath)) {
                return null;
            }

            $builder = new Builder(
                writer: new PngWriter(),
                writerOptions: [],
                validateResult: false,
                data: $this->provisioningUri($user, $secret),
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $qrCodeSize,
                margin: self::QR_CODE_MARGIN,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                logoPath: $logoPath,
                logoResizeToWidth: $this->logoResizeWidth($qrCodeSize),
                logoPunchoutBackground: true
            );

            return $builder->build()->getDataUri();
        } catch (Throwable) {
            return null;
        }
    }

    public function verify(string $secret, string $oneTimePassword): bool
    {
        if (! $this->mfaPolicy->supportsConfiguredMethod()) {
            return false;
        }

        $normalizedOneTimePassword = $this->normalizeOneTimePassword($oneTimePassword);

        if (! $this->hasExpectedDigits($normalizedOneTimePassword)) {
            return false;
        }

        $this->configureTotpEngine();

        try {
            return $this->google2fa->verifyKey(
                $secret,
                $normalizedOneTimePassword,
                $this->mfaPolicy->totpWindow()
            ) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function normalizeOneTimePassword(string $oneTimePassword): string
    {
        return Str::of($oneTimePassword)
            ->trim()
            ->replaceMatches('/[\s-]+/', '')
            ->toString();
    }

    private function configureTotpEngine(): void
    {
        $this->google2fa->setOneTimePasswordLength($this->mfaPolicy->totpDigits());
        $this->google2fa->setKeyRegeneration($this->mfaPolicy->totpPeriodSeconds());
        $this->google2fa->setWindow($this->mfaPolicy->totpWindow());
    }

    private function hasExpectedDigits(string $oneTimePassword): bool
    {
        return ctype_digit($oneTimePassword)
            && Str::length($oneTimePassword) === $this->mfaPolicy->totpDigits();
    }

    private function logoResizeWidth(int $qrCodeSize): int
    {
        return max(32, min(56, (int) round($qrCodeSize * self::QR_CODE_LOGO_RATIO)));
    }

    private function issuer(): string
    {
        $issuer = config('app.name', 'SITANGKAS');

        return is_string($issuer) && $issuer !== '' ? $issuer : 'SITANGKAS';
    }

    private function accountName(User $user): string
    {
        if (filled($user->email)) {
            return (string) $user->email;
        }

        if (filled($user->nik)) {
            return (string) $user->nik;
        }

        return 'user-'.$user->getKey();
    }
}
