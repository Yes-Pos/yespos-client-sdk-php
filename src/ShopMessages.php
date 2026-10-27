<?php

declare(strict_types=1);

namespace Yespos\Client;

/** Pesan yang dilemparkan [Shop] sebagai error, agar storefront bisa menerjemahkannya. */
final class ShopMessages
{
    public function __construct(
        public readonly string $outletNotFound = 'Outlet toko tidak ditemukan. Periksa outletUuid.',
        public readonly string $productUnavailable = 'Ada produk di keranjang yang sudah tidak tersedia. Hapus lalu coba lagi.',
        public readonly string $methodUnavailable = 'Metode pembayaran tidak tersedia.',
    ) {
    }
}
