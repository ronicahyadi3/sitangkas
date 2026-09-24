<?php

use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Services\Esign\QrCodePngGenerator;

it('generates a square branded PNG using the trusted Malang City logo profile', function () {
    $generator = app(QrCodePngGenerator::class);
    $png = $generator->generate(
        'https://sitangkas.malangkota.go.id/verify/00000000-0000-4000-8000-000000000000',
        300,
        12,
    );
    $imageInfo = getimagesizefromstring($png);
    $image = imagecreatefromstring($png);

    expect($png)->toStartWith("\x89PNG\r\n\x1a\n")
        ->and($imageInfo)->toBeArray()
        ->and($imageInfo[0])->toBe(300)
        ->and($imageInfo[1])->toBe(300)
        ->and($imageInfo['mime'])->toBe('image/png')
        ->and($image)->toBeInstanceOf(GdImage::class)
        ->and(imageContainsColor($image))->toBeTrue()
        ->and($generator->profile())->toBe([
            'version' => 'malangkota-logo-v1',
            'logo_sha256' => '91a121d914ccc147420090ac99a20396db5fbc3f7e8b3c368cdaeaef49abb91c',
            'logo_width_ratio' => 0.22,
            'logo_punchout_background' => true,
            'error_correction_level' => 'high',
        ]);

    imagedestroy($image);
});

it('refuses to generate a QR when the trusted logo checksum does not match', function () {
    config()->set('esign.visible_editor.qr_logo_sha256', str_repeat('0', 64));

    expect(fn () => app(QrCodePngGenerator::class)->generate('https://example.test/verify/1'))
        ->toThrow(EsignInvariantViolationException::class, 'esign_qr_logo_integrity_invalid');
});

function imageContainsColor(GdImage $image): bool
{
    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $color = imagecolorat($image, $x, $y);
            $red = ($color >> 16) & 0xFF;
            $green = ($color >> 8) & 0xFF;
            $blue = $color & 0xFF;

            if ($red !== $green || $green !== $blue) {
                return true;
            }
        }
    }

    return false;
}
