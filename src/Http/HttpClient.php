<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

use JsonException;
use Yespos\Client\YesposException;

/**
 * Transport untuk `/api/client`: menandatangani setiap request dan membuka
 * amplop `{message, data}` dari respons.
 *
 * Dipakai oleh resource; panggil langsung hanya untuk endpoint yang belum
 * dibungkus SDK.
 */
final class HttpClient
{
    private readonly string $baseUrl;
    private readonly Transport $transport;

    public function __construct(private readonly ClientOptions $options)
    {
        $baseUrl = rtrim(trim($options->baseUrl), '/');
        if ($baseUrl === '' || $options->key === '' || $options->secret === '') {
            throw new YesposException(500, 'baseUrl, key dan secret wajib diisi saat membuat Client.');
        }

        $this->baseUrl = $baseUrl;
        $this->transport = $options->transport ?? new CurlTransport();
    }

    /** URL publik sebuah file yang diunggah; `GET /media/:token` tidak butuh login. */
    public function mediaUrl(string $token): string
    {
        return $this->baseUrl . '/media/' . rawurlencode($token);
    }

    /**
     * @param  array<string,string|int|null> $query
     * @return array<mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        return $this->request('GET', $path, null, $query);
    }

    /**
     * @param  array<string,mixed>|null $body
     * @return array<mixed>|null
     */
    public function post(string $path, ?array $body = null): ?array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Mengirim satu request yang sudah ditandatangani lalu membuka amplopnya.
     *
     * @param  array<string,mixed>|null     $body
     * @param  array<string,string|int|null> $query
     * @return array<mixed>|null
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): ?array
    {
        // Body diserialisasi di sini supaya tanda tangan menutupi persis apa
        // yang dikirim.
        $payload = $body === null ? '' : self::encode($body);
        $signature = Signature::sign($method, $this->options->key, $this->options->secret, $payload);

        $url = $this->baseUrl . '/api/client' . $path;
        $pairs = [];
        foreach ($query as $name => $value) {
            if ($value !== null && $value !== '') {
                $pairs[$name] = (string) $value;
            }
        }
        if ($pairs !== []) {
            $url .= '?' . http_build_query($pairs);
        }

        $response = $this->transport->send(
            $method,
            $url,
            [
                'Content-Type' => 'application/json',
                'Accept-Language' => $this->options->language,
                'va' => $this->options->key,
                'signature' => $signature,
                'timestamp' => (string) time(),
            ],
            $payload === '' ? null : $payload,
            $this->options->timeout,
        );

        $parsed = self::decode($response->body);

        if ($response->status < 200 || $response->status > 299) {
            $message = $parsed['message'] ?? null;
            if (is_array($message)) {
                $message = implode(' ', array_map(
                    static fn (mixed $line): string => is_scalar($line) ? (string) $line : '',
                    $message,
                ));
            }
            $message = is_string($message) && trim($message) !== ''
                ? $message
                : sprintf('Permintaan gagal (%d).', $response->status);

            throw new YesposException($response->status, $message, $parsed['details'] ?? null);
        }

        $data = $parsed['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    /** @param array<string,mixed> $body */
    private static function encode(array $body): string
    {
        try {
            return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $failure) {
            throw new YesposException(500, 'Gagal menyusun body permintaan: ' . $failure->getMessage());
        }
    }

    /**
     * Body yang bukan JSON diperlakukan sebagai respons kosong, sama seperti
     * SDK TypeScript dan Go-nya.
     *
     * @return array<string,mixed>
     */
    private static function decode(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $parsed = json_decode($text, true);

        return is_array($parsed) ? $parsed : [];
    }
}
