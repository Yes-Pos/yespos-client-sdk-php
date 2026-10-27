<?php

declare(strict_types=1);

namespace Yespos\Client\Resource;

use Yespos\Client\Http\HttpClient;
use Yespos\Client\Model\ChangeOrderPaymentInput;
use Yespos\Client\Model\CreateOrderInput;
use Yespos\Client\Model\Order;
use Yespos\Client\Model\OrderList;
use Yespos\Client\YesposException;

/** Order milik client. */
final class Orders
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Menempatkan order dan meminta iPaymu atau TSM menagihnya. Responsnya
     * membawa apa yang dibutuhkan pembeli: `qrString` (QRIS), `paymentNo` dan
     * `expiredAt` (virtual account), atau `appLink` (EDC).
     */
    public function create(CreateOrderInput $input): Order
    {
        return Order::fromArray($this->require($this->http->post('/orders', $input->toArray())));
    }

    /** Satu order beserta status dan rincian pembayarannya. */
    public function get(string $uuid): Order
    {
        return Order::fromArray($this->require($this->http->get('/orders/' . rawurlencode($uuid))));
    }

    /** Order milik client, terbaru lebih dulu. */
    public function list(int $page = 0, int $limit = 0): OrderList
    {
        $payload = $this->http->get('/orders', ['page' => $page ?: null, 'limit' => $limit ?: null]) ?? [];

        return OrderList::fromArray($payload);
    }

    /**
     * Memindahkan order pending ke metode lain yang aktif, dengan total, item,
     * split, dan pembeli yang sama. Mengirim metode yang sedang dipakai akan
     * memperbarui pembayarannya, misalnya virtual account kedaluwarsa.
     */
    public function changePaymentMethod(string $uuid, ChangeOrderPaymentInput $input): Order
    {
        $path = '/orders/' . rawurlencode($uuid) . '/payment-method';

        return Order::fromArray($this->require($this->http->post($path, $input->toArray())));
    }

    /**
     * @param  array<mixed>|null   $data
     * @return array<string,mixed>
     */
    private function require(?array $data): array
    {
        if ($data === null) {
            throw new YesposException(404, 'Order tidak ditemukan.');
        }

        return $data;
    }
}
