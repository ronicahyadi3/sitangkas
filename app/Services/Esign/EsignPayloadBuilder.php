<?php

namespace App\Services\Esign;

use App\Data\Esign\SignRequestData;
use App\Data\Esign\UserStatusRequestData;
use App\Data\Esign\VerifyPdfData;
use App\Enums\Esign\EsignErrorCode;
use App\Exceptions\Esign\EsignOperationException;

final class EsignPayloadBuilder
{
    /**
     * @return array{
     *     nik: string,
     *     passphrase: string,
     *     signatureProperties: list<array{tampilan: string}>,
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

        return [
            'nik' => $request->nik(),
            'passphrase' => $request->passphrase(),
            'signatureProperties' => [
                ['tampilan' => 'INVISIBLE'],
            ],
            'file' => [
                base64_encode($request->pdfContents()),
            ],
        ];
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
