<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

use Yespos\Client\YesposException;

/**
 * Pengirim request HTTP. Bawaannya [CurlTransport]; pakai [Psr18Transport]
 * untuk memakai HTTP client sendiri (Guzzle, Symfony HttpClient, dll.).
 */
interface Transport
{
    /**
     * @param  array<string,string> $headers
     * @param  float                $timeout batas waktu dalam detik
     * @throws YesposException      dengan status 0 bila server tak terjangkau
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse;
}
