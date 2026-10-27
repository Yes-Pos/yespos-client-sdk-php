<?php

declare(strict_types=1);

namespace Yespos\Client\Tests;

use Yespos\Client\Http\Transport;
use Yespos\Client\Http\TransportResponse;

/**
 * YesPOS client API secukupnya untuk menguji SDK: ia memverifikasi tanda tangan
 * persis seperti `ClientAuthMiddleware`.
 *
 * Dipakai sebagai [Transport] agar test berjalan tanpa jaringan; logika
 * jawabannya dipakai ulang oleh server sungguhan di [HttpServer].
 */
final class MockApi implements Transport
{
    public const KEY = 'test-key';
    public const SECRET = 'test-secret';

    /** @var list<array{method:string,path:string,body:array<string,mixed>,language:string}> */
    public array $requests = [];

    /** @var array<string,array<string,mixed>> */
    private array $orders = [];

    /** @var array{status:int,body:array<string,mixed>}|null */
    private ?array $failure = null;

    /** Menjawab request berikutnya dengan status dan body ini, lalu kembali normal. */
    public function failNext(int $status, array $body): void
    {
        $this->failure = ['status' => $status, 'body' => $body];
    }

    /** @return array{method:string,path:string,body:array<string,mixed>,language:string} */
    public function lastRequest(): array
    {
        $last = $this->requests[array_key_last($this->requests) ?? 0] ?? null;
        if ($last === null) {
            throw new \RuntimeException('mock belum mencatat request apa pun');
        }

        return $last;
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);

        return $this->handle($method, $path, $body ?? '', $headers, $query);
    }

    /**
     * @param  array<string,string> $headers
     * @param  array<string,mixed>  $query
     */
    public function handle(string $method, string $path, string $body, array $headers, array $query = []): TransportResponse
    {
        $headers = array_change_key_case($headers);
        $path = (string) preg_replace('#^/api/client#', '', $path);

        if (str_starts_with($path, '/media/')) {
            return new TransportResponse(200, 'png');
        }

        // Cek yang sama dengan backend: HMAC atas METHOD:key:sha256(body):secret.
        $signed = trim($body) === '' ? '{}' : trim($body);
        $expected = hash_hmac(
            'sha256',
            strtoupper($method) . ':' . self::KEY . ':' . hash('sha256', $signed) . ':' . self::SECRET,
            self::SECRET,
        );
        if (($headers['va'] ?? '') !== self::KEY || ($headers['signature'] ?? '') !== $expected) {
            return self::json(401, ['message' => 'Signature Tidak Sesuai (Invalid signature)']);
        }

        $input = $body === '' ? [] : (array) json_decode($body, true);
        $this->requests[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'body' => $input,
            'language' => $headers['accept-language'] ?? '',
        ];

        if ($this->failure !== null) {
            $failure = $this->failure;
            $this->failure = null;

            return self::json($failure['status'], $failure['body']);
        }

        if ($method === 'GET' && $path === '/outlets') {
            return self::json(200, ['data' => [
                ['uuid' => 'o1', 'name' => 'Cabang Sudirman'],
                ['uuid' => 'o2', 'name' => 'Cabang Thamrin'],
            ]]);
        }
        if ($method === 'GET' && preg_match('#^/outlets/[^/]+/products$#', $path) === 1) {
            return self::json(200, ['data' => self::categories()]);
        }
        if ($method === 'GET' && preg_match('#^/outlets/[^/]+/products/(.+)$#', $path, $found) === 1) {
            foreach (self::categories() as $category) {
                foreach ($category['products'] as $product) {
                    if ($product['uuid'] === rawurldecode($found[1])) {
                        return self::json(200, ['data' => $product + ['categories' => ['Makanan']]]);
                    }
                }
            }

            return self::json(404, ['message' => 'Produk tidak ditemukan.']);
        }
        if ($method === 'GET' && $path === '/payment-method') {
            return self::json(200, ['data' => self::methods()]);
        }
        if ($method === 'GET' && $path === '/orders') {
            return self::json(200, ['data' => [
                'data' => array_values($this->orders),
                'total' => count($this->orders),
                'page' => (int) ($query['page'] ?? 1),
                'limit' => (int) ($query['limit'] ?? 10),
            ]]);
        }
        if ($method === 'POST' && $path === '/orders') {
            $uuid = 'ord-' . bin2hex(random_bytes(6));
            $order = [
                'uuid' => $uuid,
                'total_payment' => $input['total_payment'] ?? 0,
                'payment_method' => $input['payment_method'] ?? '',
                'payment_channel' => $input['payment_channel'] ?? null,
                'status' => 'pending',
                'items' => $input['items'] ?? [],
                'customer' => [
                    'name' => $input['name'] ?? '',
                    'email' => $input['email'] ?? '',
                    'phone' => $input['phone'] ?? '',
                ],
                'created_at' => '2026-10-27T10:00:00+07:00',
            ];
            $order += match ($input['payment_method'] ?? '') {
                'qris' => ['qr_string' => '00020101021226'],
                'va' => ['payment_no' => '8002300000123456', 'payment_name' => 'IPAYMU'],
                'edc' => ['app_link' => 'https://erpay.id/payment?data=ABC'],
                default => [],
            };
            $this->orders[$uuid] = $order;

            return self::json(201, ['data' => $order]);
        }
        if ($method === 'POST' && preg_match('#^/orders/([^/]+)/payment-method$#', $path, $found) === 1) {
            $uuid = rawurldecode($found[1]);
            $order = $this->orders[$uuid] ?? null;
            if ($order === null) {
                return self::json(404, ['message' => 'Order tidak ditemukan.']);
            }
            if ($order['status'] !== 'pending') {
                return self::json(409, ['message' => 'Order sudah tidak pending.']);
            }
            foreach (['qr_string', 'payment_no', 'payment_name', 'app_link'] as $key) {
                unset($order[$key]);
            }
            $order['payment_method'] = $input['payment_method'] ?? '';
            $order['payment_channel'] = $input['payment_channel'] ?? null;
            $order += match ($input['payment_method'] ?? '') {
                'qris' => ['qr_string' => 'NEWQR'],
                'va' => ['payment_no' => '8002300000999999'],
                'edc' => ['app_link' => 'https://erpay.id/payment?data=NEW'],
                default => [],
            };
            $this->orders[$uuid] = $order;

            return self::json(200, ['data' => $order]);
        }
        if ($method === 'GET' && preg_match('#^/orders/(.+)$#', $path, $found) === 1) {
            $order = $this->orders[rawurldecode($found[1])] ?? null;

            return $order === null
                ? self::json(400, ['message' => 'record not found'])
                : self::json(200, ['data' => $order]);
        }

        return self::json(404, ['message' => 'not found']);
    }

    /** @return list<array<string,mixed>> */
    public static function categories(): array
    {
        $nasiGoreng = [
            'uuid' => 'p1',
            'name' => 'Nasi Goreng',
            'price' => 25000,
            'unit' => 'porsi',
            'type' => 'fnb_main_product',
            'current_stock' => 0,
            'track_stock' => false,
            'main_image_token' => 'img1',
            'main_image_url' => 'https://cdn.example.com/media/img1',
            'variants' => [],
        ];

        return [
            [
                'category_uuid' => 'c1',
                'category_name' => 'Makanan',
                'products' => [
                    $nasiGoreng,
                    [
                        'uuid' => 'p2',
                        // Harga sebagai string: API kadang mengirimnya begitu.
                        'name' => 'Kopi Susu',
                        'price' => '18000.00',
                        'unit' => 'cup',
                        'type' => 'fnb_main_product',
                        'current_stock' => 0,
                        'track_stock' => true,
                        'variants' => [
                            ['uuid' => 'v1', 'name' => 'Kecil', 'price' => 18000, 'current_stock' => 5],
                            ['uuid' => 'v2', 'name' => 'Besar', 'price' => 24000, 'current_stock' => 0],
                        ],
                    ],
                ],
            ],
            // Satu produk bisa berada di beberapa kategori; SDK harus mencatatnya sekali.
            ['category_uuid' => 'c2', 'category_name' => 'Favorit', 'products' => [$nasiGoreng]],
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function methods(): array
    {
        return [
            ['name' => 'QRIS', 'type' => 'qris', 'issuer' => 'iPaymu', 'payment_method' => 'qris', 'payment_channel' => 'qris', 'required_fields' => []],
            ['name' => 'BCA', 'type' => 'bank_transfer', 'issuer' => 'iPaymu', 'payment_method' => 'va', 'payment_channel' => 'bca', 'required_fields' => []],
            [
                'name' => 'Credit Card', 'type' => 'credit_card', 'issuer' => 'TSM',
                'payment_method' => 'edc', 'payment_channel' => 'linkpayment',
                'required_fields' => ['app_code', 'terminal_code', 'merchant_code'],
            ],
        ];
    }

    /** @param array<string,mixed> $body */
    private static function json(int $status, array $body): TransportResponse
    {
        return new TransportResponse($status, (string) json_encode($body));
    }
}
