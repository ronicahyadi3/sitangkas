<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stringable;
use Throwable;

class EncryptedId
{
    public static function encode(int|string|Stringable|null $id): string
    {
        $plainId = trim((string) $id);

        if ($plainId === '') {
            throw new RuntimeException('Invalid ID');
        }

        try {
            $cipher = Crypt::encryptString($plainId);
            $urlSafeCipher = strtr($cipher, ['+' => '-', '/' => '_']);

            return rtrim($urlSafeCipher, '=');
        } catch (Throwable $throwable) {
            self::debug('encode', [
                'id' => $plainId,
                'error' => $throwable->getMessage(),
            ]);

            throw new RuntimeException('Invalid ID', previous: $throwable);
        }
    }

    public static function decode(int|string|Stringable|null $hash): int
    {
        $encodedId = trim((string) $hash);

        if ($encodedId === '') {
            throw new RuntimeException('Invalid ID');
        }

        if (ctype_digit($encodedId)) {
            return (int) $encodedId;
        }

        try {
            $base64Cipher = strtr($encodedId, ['-' => '+', '_' => '/']);
            $paddingLength = strlen($base64Cipher) % 4;

            if ($paddingLength > 0) {
                $base64Cipher .= str_repeat('=', 4 - $paddingLength);
            }

            $plain = Crypt::decryptString($base64Cipher);

            if (! ctype_digit($plain)) {
                throw new RuntimeException('Non-numeric payload');
            }

            return (int) $plain;
        } catch (Throwable $throwable) {
            self::debug('decode', [
                'hash' => $encodedId,
                'error' => $throwable->getMessage(),
            ]);

            throw new RuntimeException('Invalid ID', previous: $throwable);
        }
    }

    public static function tryDecode(int|string|Stringable|null $hash): ?int
    {
        if (! filled((string) $hash)) {
            return null;
        }

        try {
            return self::decode($hash);
        } catch (Throwable $throwable) {
            self::debug('tryDecode', [
                'hash' => (string) $hash,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function debug(string $action, array $context): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug("EncryptedId {$action} failed", $context);
    }
}
