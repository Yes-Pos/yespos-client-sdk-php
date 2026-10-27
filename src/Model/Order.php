<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Order beserta status dan rincian pembayarannya. */
final class Order
{
    /**
     * @param list<OrderItem> $items
     * @param list<SplitItem> $split
     * @param string          $status mula-mula `"pending"`, lalu `"<status>_<settlement>"`
     * @param string|null     $qrString   QRIS: render menjadi kode QR
     * @param string|null     $appLink    EDC: buka di perangkat EDC
     * @param string|null     $paymentNo  nomor virtual account
     */
    public function __construct(
        public readonly string $uuid,
        public readonly int $totalPayment,
        public readonly string $paymentMethod,
        public readonly ?string $paymentChannel,
        public readonly string $status,
        public readonly array $items = [],
        public readonly ?Customer $customer = null,
        public readonly array $split = [],
        public readonly ?string $qrString = null,
        public readonly ?string $appLink = null,
        public readonly ?string $paymentNo = null,
        public readonly ?string $paymentName = null,
        public readonly ?string $expiredAt = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?string $referenceIpaymu = null,
        public readonly ?string $createdAt = null,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $customer = $data['customer'] ?? null;

        return new self(
            Cast::string($data, 'uuid'),
            Cast::amount($data, 'total_payment'),
            Cast::string($data, 'payment_method'),
            Cast::nullableString($data, 'payment_channel'),
            Cast::string($data, 'status'),
            array_map(OrderItem::fromArray(...), Cast::rows($data, 'items')),
            is_array($customer) ? Customer::fromArray($customer) : null,
            array_map(SplitItem::fromArray(...), Cast::rows($data, 'split')),
            Cast::nullableString($data, 'qr_string'),
            Cast::nullableString($data, 'app_link'),
            Cast::nullableString($data, 'payment_no'),
            Cast::nullableString($data, 'payment_name'),
            Cast::nullableString($data, 'expired_at'),
            Cast::nullableString($data, 'notify_url'),
            Cast::nullableString($data, 'reference_ipaymu'),
            Cast::nullableString($data, 'created_at'),
        );
    }

    public function state(): OrderState
    {
        return OrderState::fromStatus($this->status);
    }

    /** Benar selama order masih menunggu dibayar. */
    public function isPending(): bool
    {
        return $this->state() === OrderState::Pending;
    }

    /** Benar begitu pembayarannya masuk. */
    public function isPaid(): bool
    {
        return $this->state() === OrderState::Paid;
    }
}
