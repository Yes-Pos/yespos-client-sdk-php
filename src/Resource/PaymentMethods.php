<?php

declare(strict_types=1);

namespace Yespos\Client\Resource;

use Yespos\Client\Http\HttpClient;
use Yespos\Client\Model\PaymentMethod;

/** Metode pembayaran yang boleh dipakai order. */
final class PaymentMethods
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Metode yang diaktifkan pemilik client: QRIS, virtual account per bank,
     * dan EDC. Metode di luar daftar ini akan ditolak saat membuat order.
     *
     * @return list<PaymentMethod>
     */
    public function list(): array
    {
        $rows = $this->http->get('/payment-method') ?? [];

        return array_map(PaymentMethod::fromArray(...), array_values(array_filter($rows, 'is_array')));
    }
}
