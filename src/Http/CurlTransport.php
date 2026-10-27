<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

use Yespos\Client\YesposException;

/** Transport bawaan: cURL, tanpa dependency tambahan. */
final class CurlTransport implements Transport
{
    /**
     * @param array<int,mixed> $options opsi cURL tambahan, misalnya CURLOPT_PROXY
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new YesposException(0, 'Tidak dapat terhubung ke server YesPOS.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, $this->options + [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            // Timeout dihitung dalam milidetik agar nilai pecahan tetap terpakai.
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($timeout * 1000),
        ] + ($body === null ? [] : [CURLOPT_POSTFIELDS => $body]));

        $response = curl_exec($handle);
        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($errno !== 0 || !is_string($response)) {
            $timedOut = $errno === CURLE_OPERATION_TIMEOUTED;

            throw new YesposException(0, $timedOut
                ? 'Permintaan ke YesPOS melebihi batas waktu.'
                : 'Tidak dapat terhubung ke server YesPOS.');
        }

        return new TransportResponse($status, $response);
    }
}
