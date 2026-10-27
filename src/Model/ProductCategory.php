<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Satu kategori beserta produk di dalamnya. */
final class ProductCategory
{
    /** @param list<Product> $products */
    public function __construct(
        public readonly string $categoryUuid,
        public readonly string $categoryName,
        public readonly array $products = [],
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'category_uuid'),
            Cast::string($data, 'category_name'),
            array_map(Product::fromArray(...), Cast::rows($data, 'products')),
        );
    }
}
