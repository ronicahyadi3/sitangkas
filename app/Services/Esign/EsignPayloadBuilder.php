<?php

namespace App\Services\Esign;

use App\Data\Esign\SignaturePropertyData;
use App\Data\Esign\SignRequestData;
use App\Data\Esign\UserStatusRequestData;
use App\Data\Esign\VerifyPdfData;
use App\Enums\Esign\EsignErrorCode;
use App\Enums\Esign\SignatureDisplayMode;
use App\Exceptions\Esign\EsignOperationException;

final class EsignPayloadBuilder
{
    /**
     * @return array{
     *     nik: string,
     *     passphrase: string,
     *     signatureProperties: list<array<string, float|int|string>>,
     *     file: list<string>
     * }
     */
    public function sign(SignRequestData $request, string $correlationId): array
    {
        $this->assertNik($request->nik(), 'sign', $correlationId);
        $this->assertPdf($request->pdfContents(), 'sign', $correlationId);

        if (trim($request->passphrase()) === '') {
            throw $this->invalidRequest('sign', $correlationId);
        }

        $signatureProperties = $request->signatureProperties();

        if (count($signatureProperties) !== 1) {
            throw $this->invalidRequest('sign', $correlationId);
        }

        return [
            'nik' => $request->nik(),
            'passphrase' => $request->passphrase(),
            'signatureProperties' => [
                $this->signatureProperty($signatureProperties[0], $correlationId),
            ],
            'file' => [
                base64_encode($request->pdfContents()),
            ],
        ];
    }

    /** @return array<string, float|int|string> */
    private function signatureProperty(
        SignaturePropertyData $property,
        string $correlationId,
    ): array {
        if ($property->displayMode === SignatureDisplayMode::Invisible) {
            return ['tampilan' => 'INVISIBLE'];
        }

        $imageContents = $property->imageContents();

        if (! is_string($imageContents)
            || ! str_starts_with($imageContents, "\x89PNG\r\n\x1a\n")
            || $property->pageNumber === null
            || $property->pageNumber < 1
            || $property->originX === null
            || $property->originY === null
            || $property->width === null
            || $property->height === null
            || ! is_finite($property->originX)
            || ! is_finite($property->originY)
            || ! is_finite($property->width)
            || ! is_finite($property->height)
            || $property->originX < 0
            || $property->originY < 0
            || $property->width <= 0
            || $property->height <= 0
            || ! $this->validOptionalText($property->location)
            || ! $this->validOptionalText($property->reason)
            || ! $this->validOptionalText($property->contactInfo)) {
            throw $this->invalidRequest('sign', $correlationId);
        }

        return [
            'imageBase64' => base64_encode($imageContents),
            'tampilan' => 'VISIBLE',
            'page' => $property->pageNumber,
            'originX' => $property->originX,
            'originY' => $property->originY,
            'width' => $property->width,
            'height' => $property->height,
            'location' => $property->location ?? 'null',
            'reason' => $property->reason ?? 'null',
            'contactInfo' => $property->contactInfo ?? 'null',
        ];
    }

    private function validOptionalText(?string $value): bool
    {
        return $value === null || (trim($value) !== '' && strlen($value) <= 500);
    }

    /** @return array{file: string} */
    public function verify(VerifyPdfData $request, string $correlationId): array
    {
        $this->assertPdf($request->pdfContents(), 'verify', $correlationId);

        return [
            'file' => base64_encode($request->pdfContents()),
        ];
    }

    /** @return array{nik: string} */
    public function userStatus(UserStatusRequestData $request, string $correlationId): array
    {
        $this->assertNik($request->nik(), 'user_status', $correlationId);

        return [
            'nik' => $request->nik(),
        ];
    }

    private function assertNik(string $nik, string $endpoint, string $correlationId): void
    {
        if (preg_match('/^\d{16}$/D', $nik) !== 1) {
            throw $this->invalidRequest($endpoint, $correlationId);
        }
    }

    private function assertPdf(string $pdfContents, string $endpoint, string $correlationId): void
    {
        if ($pdfContents === '' || ! str_starts_with($pdfContents, '%PDF-')) {
            throw new EsignOperationException(
                errorCode: EsignErrorCode::DocumentInvalid,
                retryable: false,
                endpoint: $endpoint,
                correlationId: $correlationId,
            );
        }
    }

    private function invalidRequest(string $endpoint, string $correlationId): EsignOperationException
    {
        return new EsignOperationException(
            errorCode: EsignErrorCode::InvalidRequest,
            retryable: false,
            endpoint: $endpoint,
            correlationId: $correlationId,
        );
    }
}
