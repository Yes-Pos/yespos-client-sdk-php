<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

/**
 * Satu baris keranjang yang dikirim storefront saat checkout: tanpa harga,
 * SDK yang mencarinya ke API.
 */
final class CartLine
{
    public function __construct(
        public readonly string $productUuid,
        public readonly int $quantity = 1,
        public readonly ?string $variantUuid = null,
    ) {
    }
}
