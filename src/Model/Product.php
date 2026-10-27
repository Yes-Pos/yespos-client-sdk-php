<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Produk yang dijual di sebuah outlet. */
class Product
{
    /**
     * @param list<ProductVariant> $variants
     * @param list<ProductAddOn>   $addOns
     * @param list<InventoryStock> $inventories
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $price,
        public readonly string $unit,
        public readonly string $type,
        public readonly float $currentStock,
        public readonly bool $trackStock,
        public readonly array $variants = [],
        public readonly array $addOns = [],
        public readonly array $inventories = [],
        public readonly ?string $barcodeData = null,
        public readonly ?string $mainImageToken = null,
        public readonly ?string $mainImageUrl = null,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'uuid'),
            Cast::string($data, 'name'),
            Cast::nullableString($data, 'description'),
            Cast::amount($data, 'price'),
            Cast::string($data, 'unit'),
            Cast::string($data, 'type'),
            Cast::float($data, 'current_stock'),
            Cast::bool($data, 'track_stock'),
            array_map(ProductVariant::fromArray(...), Cast::rows($data, 'variants')),
            array_map(ProductAddOn::fromArray(...), Cast::rows($data, 'add_ons')),
            array_map(InventoryStock::fromArray(...), Cast::rows($data, 'inventories')),
            Cast::nullableString($data, 'barcode_data'),
            Cast::nullableString($data, 'main_image_token'),
            Cast::nullableString($data, 'main_image_url'),
        );
    }

    /**
     * Produk yang melacak stok habis bila stoknya — atau stok semua
     * variannya — tinggal nol. Produk yang tidak melacak stok tidak pernah habis.
     */
    public function isSoldOut(): bool
    {
        if (!$this->trackStock) {
            return false;
        }
        if ($this->variants === []) {
            return $this->currentStock <= 0;
        }
        foreach ($this->variants as $variant) {
            if ($variant->currentStock > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Harga terendah dan tertinggi, dihitung dari varian bila ada.
     *
     * @return array{min:int,max:int}
     */
    public function priceRange(): array
    {
        if ($this->variants === []) {
            return ['min' => $this->price, 'max' => $this->price];
        }
        $prices = array_map(static fn (ProductVariant $variant): int => $variant->price, $this->variants);

        return ['min' => min($prices), 'max' => max($prices)];
    }

    /** Mencari varian berdasarkan uuid. */
    public function variant(string $uuid): ?ProductVariant
    {
        foreach ($this->variants as $variant) {
            if ($variant->uuid === $uuid) {
                return $variant;
            }
        }

        return null;
    }
}
