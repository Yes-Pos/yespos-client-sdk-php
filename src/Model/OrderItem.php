<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Satu baris pada order. */
final class OrderItem
{
    public function __construct(
        public readonly string $productName,
        public readonly int $quantity,
        public readonly int $price,
        public readonly int $total,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'product_name'),
            Cast::int($data, 'quantity'),
            Cast::amount($data, 'price'),
            Cast::amount($data, 'total'),
        );
    }

    /** @return array<string,string|int> */
    public function toArray(): array
    {
        return [
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total' => $this->total,
        ];
    }
}
