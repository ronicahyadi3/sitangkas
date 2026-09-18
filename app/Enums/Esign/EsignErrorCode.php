<?php

namespace App\Enums\Esign;

enum EsignErrorCode: string
{
    case InvalidRequest = 'esign.invalid_request';
    case InvalidPassphrase = 'esign.invalid_passphrase';
    case UserNotRegistered = 'esign.user_not_registered';
    case CertificateUnavailable = 'esign.certificate_unavailable';
    case CertificateExpired = 'esign.certificate_expired';
    case DocumentInvalid = 'esign.document_invalid';
    case ProviderRejected = 'esign.provider_rejected';
    case ProviderUnavailable = 'esign.provider_unavailable';
    case ResultInvalid = 'esign.result_invalid';
    case OutcomeUnknown = 'esign.outcome_unknown';
    case ConcurrentAttempt = 'esign.concurrent_attempt';
    case Unauthorized = 'esign.unauthorized';

    public function message(): string
    {
        return match ($this) {
            self::InvalidRequest => 'Permintaan TTE tidak valid.',
            self::InvalidPassphrase => 'Passphrase TTE tidak sesuai.',
            self::UserNotRegistered => 'Identitas penandatangan belum terdaftar.',
            self::CertificateUnavailable => 'Sertifikat elektronik tidak tersedia.',
            self::CertificateExpired => 'Sertifikat elektronik telah kedaluwarsa.',
            self::DocumentInvalid => 'Dokumen PDF tidak valid untuk diproses.',
            self::ProviderRejected => 'Permintaan ditolak oleh layanan TTE.',
            self::ProviderUnavailable => 'Layanan TTE sedang tidak tersedia.',
            self::ResultInvalid => 'Hasil layanan TTE tidak valid.',
            self::OutcomeUnknown => 'Status proses TTE belum dapat dipastikan.',
            self::ConcurrentAttempt => 'Dokumen sedang diproses oleh permintaan TTE lain.',
            self::Unauthorized => 'Anda tidak berwenang melakukan proses TTE ini.',
        };
    }
}
