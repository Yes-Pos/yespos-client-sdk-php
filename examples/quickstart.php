<?php

/**
 * Menampilkan isi toko dari YesPOS, lalu — bila diminta — membuat satu order
 * dan mencetak instruksi pembayarannya.
 *
 *   export YESPOS_API_BASE_URL="https://api.yespos.id"
 *   export YESPOS_CLIENT_KEY="…"
 *   export YESPOS_CLIENT_SECRET="…"
 *
 *   php examples/quickstart.php                              # lihat katalog
 *   php examples/quickstart.php --order=p1 --payment=va:bca  # buat order
 *   php examples/quickstart.php --status=<uuid-order>        # cek status
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Yespos\Client\Model\CartLine;
use Yespos\Client\Model\Customer;
use Yespos\Client\Model\Order;
use Yespos\Client\Shop;
use Yespos\Client\ShopOptions;
use Yespos\Client\YesposException;

$flags = getopt('', ['outlet::', 'order::', 'variant::', 'qty::', 'payment::', 'status::']);
$outletUuid = $flags['outlet'] ?? null;
$productUuid = $flags['order'] ?? null;
$variantUuid = $flags['variant'] ?? null;
$quantity = (int) ($flags['qty'] ?? 1);
$payment = (string) ($flags['payment'] ?? 'qris:qris');
$orderUuid = $flags['status'] ?? null;

/** Melebarkan teks ke lebar kolom; dihitung per karakter, bukan per byte. */
function pad(string $text, int $width): string
{
    $gap = $width - mb_strlen($text);

    return $gap > 0 ? $text . str_repeat(' ', $gap) : $text;
}

/** Memformat nominal menjadi "Rp25.000". */
function rupiah(int $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

function printOrder(Order $order): void
{
    printf("Order  : %s\n", $order->uuid);
    printf("Total  : %s\n", rupiah($order->totalPayment));
    printf("Status : %s (%s)\n", $order->status, $order->state()->value);

    // Tiap metode memberi petunjuk bayar di field yang berbeda.
    if ($order->qrString !== null) {
        printf("QRIS   : %s\n", $order->qrString);
    } elseif ($order->paymentNo !== null) {
        printf("VA     : %s a.n. %s\n", $order->paymentNo, $order->paymentName ?? '-');
        if ($order->expiredAt !== null) {
            printf("Tempo  : %s\n", $order->expiredAt);
        }
    } elseif ($order->appLink !== null) {
        printf("EDC    : %s\n", $order->appLink);
    }
}

try {
    // Kredensial dibaca dari environment; secret tidak pernah ikut ke mana-mana
    // selain header signature.
    $shop = Shop::fromEnv(new ShopOptions(outletUuid: is_string($outletUuid) ? $outletUuid : null));

    if (is_string($orderUuid) && $orderUuid !== '') {
        printOrder($shop->order($orderUuid));
        exit(0);
    }

    $outlet = $shop->outlet();
    printf("Outlet: %s (%s)\n\n", $outlet->name, $outlet->uuid);

    foreach ($shop->products() as $category) {
        printf("# %s\n", $category->categoryName);
        foreach ($category->products as $product) {
            ['min' => $min, 'max' => $max] = $product->priceRange();
            $harga = $min === $max ? rupiah($min) : rupiah($min) . ' – ' . rupiah($max);
            printf("  %s %s %s%s\n", pad($product->uuid, 38), pad($harga, 22), $product->name, $product->isSoldOut() ? '  [habis]' : '');
            foreach ($product->variants as $variant) {
                printf("    varian %s %s %s\n", pad($variant->uuid, 29), pad(rupiah($variant->price), 22), $variant->name);
            }
        }
        echo "\n";
    }

    echo "# Metode pembayaran aktif\n";
    foreach ($shop->paymentMethods() as $method) {
        printf("  %-18s %s\n", $method->value(), $method->name);
    }

    if (!is_string($productUuid) || $productUuid === '') {
        echo "\nTambahkan --order=<uuid-produk> untuk membuat order percobaan.\n";
        exit(0);
    }

    // Harga tidak ikut dikirim: SDK mengambilnya dari API, lalu menghitung total
    // sendiri. Keranjang yang diutak-atik tidak mengubah tagihan.
    $order = $shop->placeOrder(
        [new CartLine($productUuid, $quantity, is_string($variantUuid) ? $variantUuid : null)],
        new Customer('Budi Contoh', 'budi@example.com', '08123456789'),
        $payment,
    );

    echo "\n";
    printOrder($order);
    printf("\nCek status: php examples/quickstart.php --status=%s\n", $order->uuid);
} catch (YesposException $failure) {
    fprintf(STDERR, "yespos: %s (status %d)\n", $failure->getMessage(), $failure->status);
    if ($failure->isAuthError()) {
        fwrite(STDERR, "Periksa YESPOS_CLIENT_KEY dan YESPOS_CLIENT_SECRET.\n");
    }
    exit(1);
}
