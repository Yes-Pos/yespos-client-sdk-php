<?php

/**
 * Toko online kecil di atas SDK YesPOS.
 *
 * Polanya sama dengan contoh lain: SDK hanya hidup di server, dan browser
 * memanggil endpoint JSON milik aplikasi sendiri. Secret tidak pernah
 * meninggalkan proses ini, dan harga selalu dihitung ulang dari API — keranjang
 * yang diutak-atik di browser tidak mengubah tagihan pembeli.
 *
 *   export YESPOS_API_BASE_URL="https://api.yespos.id"
 *   export YESPOS_CLIENT_KEY="…"
 *   export YESPOS_CLIENT_SECRET="…"
 *   export YESPOS_OUTLET_UUID="…"                     # opsional
 *   export YESPOS_NOTIFY_URL="https://…/api/notify"   # opsional
 *
 *   php -S localhost:8080 examples/storefront/router.php
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Yespos\Client\Model\CartLine;
use Yespos\Client\Model\Customer;
use Yespos\Client\Model\Order;
use Yespos\Client\Model\Product;
use Yespos\Client\Model\ProductVariant;
use Yespos\Client\Shop;
use Yespos\Client\YesposException;

/** Mencatat ke stderr, agar terlihat di terminal yang menjalankan `php -S`. */
function logLine(string $message): void
{
    file_put_contents('php://stderr', '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/** @param array<string,mixed>|list<mixed> $payload */
function sendJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Meneruskan status dan pesan dari YesPOS apa adanya, kecuali kegagalan
 * kredensial — itu masalah server, bukan urusan pembeli.
 */
function sendFailure(YesposException $failure): void
{
    if ($failure->isAuthError()) {
        logLine('kredensial YesPOS ditolak: ' . $failure->getMessage());
        sendJson(500, ['message' => 'Toko sedang tidak dapat melayani pesanan.']);

        return;
    }
    if ($failure->isNetworkError()) {
        logLine('YesPOS tidak terjangkau: ' . $failure->getMessage());
        sendJson(502, ['message' => 'Server pembayaran sedang tidak dapat dihubungi.']);

        return;
    }
    sendJson($failure->status, ['message' => $failure->getMessage()]);
}

/** @return array<string,mixed> */
function readJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    $parsed = json_decode($raw, true);

    return is_array($parsed) ? $parsed : [];
}

/** Bagian order yang aman ditampilkan ke pembeli. */
function orderView(Order $order): array
{
    $view = [
        'uuid' => $order->uuid,
        'total_payment' => $order->totalPayment,
        'payment_method' => $order->paymentMethod,
        'status' => $order->status,
        'state' => $order->state()->value,
    ];
    // Tiap metode memberi petunjuk bayar di field yang berbeda.
    if ($order->qrString !== null) {
        $view['qr_string'] = $order->qrString;
    } elseif ($order->paymentNo !== null) {
        $view['payment_no'] = $order->paymentNo;
        $view['payment_name'] = $order->paymentName;
        $view['expired_at'] = $order->expiredAt;
    } elseif ($order->appLink !== null) {
        $view['app_link'] = $order->appLink;
    }

    return $view;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

if ($method === 'GET' && $path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');

    return true;
}

try {
    // Satu Shop per request: PHP tidak berbagi state antar-request.
    $shop = Shop::fromEnv();

    if ($method === 'GET' && $path === '/api/catalog') {
        $categories = [];
        foreach ($shop->products() as $category) {
            $products = array_map(static function (Product $product) use ($shop): array {
                ['min' => $min, 'max' => $max] = $product->priceRange();

                // Produk lama hanya menyimpan token; URL publiknya dibangun di sini.
                $image = $product->mainImageUrl
                    ?? ($product->mainImageToken !== null ? $shop->mediaUrl($product->mainImageToken) : null);

                return [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'price' => $min,
                    'price_max' => $max,
                    'image' => $image,
                    'sold_out' => $product->isSoldOut(),
                    'variants' => array_map(
                        static fn (ProductVariant $variant): array => [
                            'uuid' => $variant->uuid,
                            'name' => $variant->name,
                            'price' => $variant->price,
                            'sold_out' => $product->trackStock && $variant->currentStock <= 0,
                        ],
                        $product->variants,
                    ),
                ];
            }, $category->products);

            $categories[] = ['name' => $category->categoryName, 'products' => $products];
        }

        sendJson(200, ['outlet' => $shop->outlet()->name, 'categories' => $categories]);

        return true;
    }

    if ($method === 'GET' && $path === '/api/payment-methods') {
        // EDC disembunyikan Shop bila terminalnya tidak dikonfigurasi.
        sendJson(200, array_map(
            static fn ($item): array => ['value' => $item->value(), 'label' => $item->name],
            $shop->paymentMethods(),
        ));

        return true;
    }

    if ($method === 'POST' && $path === '/api/checkout') {
        $body = readJsonBody();
        $rows = is_array($body['lines'] ?? null) ? $body['lines'] : [];
        $buyer = is_array($body['customer'] ?? null) ? $body['customer'] : [];

        if ($rows === []) {
            sendJson(400, ['message' => 'Keranjang masih kosong.']);

            return true;
        }
        foreach (['name', 'email', 'phone'] as $field) {
            if (!is_string($buyer[$field] ?? null) || $buyer[$field] === '') {
                sendJson(400, ['message' => 'Nama, email, dan nomor telepon wajib diisi.']);

                return true;
            }
        }

        $lines = array_map(
            static fn (array $row): CartLine => new CartLine(
                (string) ($row['product_uuid'] ?? ''),
                (int) ($row['quantity'] ?? 1),
                ($row['variant_uuid'] ?? '') !== '' ? (string) $row['variant_uuid'] : null,
            ),
            array_values(array_filter($rows, 'is_array')),
        );

        // Harga dan total dihitung SDK dari API, bukan dari body di atas.
        $order = $shop->placeOrder(
            $lines,
            new Customer($buyer['name'], $buyer['email'], $buyer['phone']),
            (string) ($body['payment'] ?? ''),
        );
        sendJson(201, orderView($order));

        return true;
    }

    if ($method === 'GET' && preg_match('#^/api/orders/([^/]+)$#', $path, $found) === 1) {
        sendJson(200, orderView($shop->order(rawurldecode($found[1]))));

        return true;
    }

    if ($method === 'POST' && $path === '/api/notify') {
        // Callback status order dari YesPOS. Aplikasi sungguhan memakai ini untuk
        // memperbarui pesanannya sendiri, lalu membaca ulang order lewat SDK
        // sebagai sumber kebenaran — jangan percaya begitu saja isi body callback.
        $payload = readJsonBody();
        $uuid = is_string($payload['uuid'] ?? null) ? $payload['uuid'] : '';
        logLine(sprintf('callback: order %s berstatus "%s"', $uuid, (string) ($payload['status'] ?? '')));

        if ($uuid !== '') {
            try {
                $order = $shop->order($uuid);
                logLine(sprintf('callback: status terverifikasi "%s" (%s)', $order->status, $order->state()->value));
            } catch (YesposException) {
                // Order yang tak terbaca tidak boleh menggagalkan callback.
            }
        }
        http_response_code(200);

        return true;
    }

    sendJson(404, ['message' => 'Halaman tidak ditemukan.']);
} catch (YesposException $failure) {
    sendFailure($failure);
}

return true;
