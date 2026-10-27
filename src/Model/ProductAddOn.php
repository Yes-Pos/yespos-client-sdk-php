<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Tambahan berbayar pada sebuah produk. */
final class ProductAddOn
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $addOnName,
        public readonly int $price,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'uuid'),
            Cast::string($data, 'add_on_name'),
            Cast::amount($data, 'price'),
        );
    }
}
