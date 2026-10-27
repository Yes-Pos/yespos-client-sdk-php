<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Varian sebuah produk, dengan harga dan stoknya sendiri. */
final class ProductVariant
{
    /** @param list<InventoryStock> $inventories */
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly int $price,
        public readonly float $currentStock,
        public readonly ?string $barcodeData = null,
        public readonly ?string $mainImageToken = null,
        /** URL publik yang dibangun API; pakai ini alih-alih menyusunnya dari token. */
        public readonly ?string $mainImageUrl = null,
        public readonly array $inventories = [],
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'uuid'),
            Cast::string($data, 'name'),
            Cast::amount($data, 'price'),
            Cast::float($data, 'current_stock'),
            Cast::nullableString($data, 'barcode_data'),
            Cast::nullableString($data, 'main_image_token'),
            Cast::nullableString($data, 'main_image_url'),
            array_map(InventoryStock::fromArray(...), Cast::rows($data, 'inventories')),
        );
    }
}
