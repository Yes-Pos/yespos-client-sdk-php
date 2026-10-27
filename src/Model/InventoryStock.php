<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Stok sebuah produk di satu gudang. */
final class InventoryStock
{
    public function __construct(
        public readonly string $inventoryUuid,
        public readonly string $inventoryName,
        public readonly float $currentStock,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'inventory_uuid'),
            Cast::string($data, 'inventory_name'),
            Cast::float($data, 'current_stock'),
        );
    }
}
