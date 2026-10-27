<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Metode pembayaran yang diaktifkan pemilik client (`GET /api/client/payment-method`). */
final class PaymentMethod
{
    public const QRIS = 'qris';
    public const VA = 'va';
    public const EDC = 'edc';

    /** @param list<string> $requiredFields field order tambahan yang diminta metode ini */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $issuer,
        public readonly string $paymentMethod,
        public readonly string $paymentChannel,
        public readonly array $requiredFields = [],
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'name'),
            Cast::string($data, 'type'),
            Cast::string($data, 'issuer'),
            Cast::string($data, 'payment_method'),
            Cast::string($data, 'payment_channel'),
            Cast::strings($data, 'required_fields'),
        );
    }

    /**
     * Bentuk `"<method>:<channel>"` yang dipakai storefront untuk menamai satu
     * pilihan pembayaran, misalnya `"va:bca"`.
     */
    public function value(): string
    {
        return $this->paymentMethod . ':' . $this->paymentChannel;
    }
}
