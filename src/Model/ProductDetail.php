<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Satu produk beserta nama kategori yang menaunginya. */
final class ProductDetail extends Product
{
    /** @param list<string> $categories */
    public function __construct(Product $product, public readonly array $categories)
    {
        parent::__construct(
            $product->uuid,
            $product->name,
            $product->description,
            $product->price,
            $product->unit,
            $product->type,
            $product->currentStock,
            $product->trackStock,
            $product->variants,
            $product->addOns,
            $product->inventories,
            $product->barcodeData,
            $product->mainImageToken,
            $product->mainImageUrl,
        );
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(Product::fromArray($data), Cast::strings($data, 'categories'));
    }
}
