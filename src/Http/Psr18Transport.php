<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yespos\Client\YesposException;

/**
 * Transport yang memakai HTTP client PSR-18 milikmu sendiri, misalnya Guzzle
 * atau Symfony HttpClient, lengkap dengan middleware yang sudah terpasang.
 *
 * Butuh paket `psr/http-client` dan `psr/http-factory`.
 *
 * Batas waktu diatur oleh client itu sendiri; argumen `$timeout` diabaikan.
 */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
    ) {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse
    {
        $request = $this->requests->createRequest(strtoupper($method), $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streams->createStream($body));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $failure) {
            throw new YesposException(0, 'Tidak dapat terhubung ke server YesPOS.', $failure->getMessage());
        }

        return new TransportResponse($response->getStatusCode(), (string) $response->getBody());
    }
}
