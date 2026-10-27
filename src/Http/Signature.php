<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

/** Tanda tangan request yang dicek `ClientAuthMiddleware` di YesPOS. */
final class Signature
{
    /**
     * Menandatangani sebuah request:
     *
     *     HMAC-SHA256(secret, "METHOD:key:sha256(body):secret")
     *
     * Body kosong ditandatangani sebagai `{}`.
     */
    public static function sign(string $method, string $key, string $secret, string $body): string
    {
        $bodyHash = hash('sha256', $body === '' ? '{}' : $body);
        $message = strtoupper($method) . ':' . $key . ':' . $bodyHash . ':' . $secret;

        return hash_hmac('sha256', $message, $secret);
    }
}
