<?php

declare(strict_types=1);

namespace Yespos\Client;

use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Model\CartLine;
use Yespos\Client\Model\ChangeOrderPaymentInput;
use Yespos\Client\Model\CreateOrderInput;
use Yespos\Client\Model\Customer;
use Yespos\Client\Model\EdcTerminal;
use Yespos\Client\Model\Order;
use Yespos\Client\Model\Outlet;
use Yespos\Client\Model\PaymentMethod;
use Yespos\Client\Model\ProductCategory;
use Yespos\Client\Model\ProductDetail;
use Yespos\Client\Model\OrderItem;

/**
 * Storefront di atas [Client]: ia tahu outlet mana yang dipakai berjualan,
 * menyembunyikan EDC bila tak ada terminal yang dikonfigurasi, dan — yang
 * terpenting — menghitung harga setiap order dari API, sehingga keranjang yang
 * diutak-atik tidak mengubah tagihan pembeli.
 *
 * ```php
 * $shop = new Shop(new ClientOptions($baseUrl, $key, $secret), new ShopOptions(outletUuid: $uuid));
 * $order = $shop->placeOrder(
 *     [new CartLine('abc-123', 2)],
 *     new Customer('Budi', 'budi@example.com', '08123456789'),
 *     'va:bca',
 * );
 * ```
 */
final class Shop
{
    public readonly Client $client;

    public function __construct(
        Client|ClientOptions $client,
        private readonly ShopOptions $options = new ShopOptions(),
    ) {
        $this->client = $client instanceof Client ? $client : new Client($client);
    }

    /** URL publik sebuah gambar yang diunggah. */
    public function mediaUrl(string $token): string
    {
        return $this->client->mediaUrl($token);
    }

    /** Outlet tempat toko berjualan: `outletUuid`, atau outlet pertama pemilik client. */
    public function outlet(): Outlet
    {
        $outlets = $this->client->outlets->list();

        if ($this->options->outletUuid === null || $this->options->outletUuid === '') {
            $first = $outlets[0] ?? null;
            if ($first === null) {
                throw new YesposException(404, $this->options->messages->outletNotFound);
            }

            return $first;
        }

        foreach ($outlets as $outlet) {
            if ($outlet->uuid === $this->options->outletUuid) {
                return $outlet;
            }
        }

        throw new YesposException(404, $this->options->messages->outletNotFound);
    }

    /**
     * Produk outlet toko, dikelompokkan per kategori.
     *
     * @return list<ProductCategory>
     */
    public function products(): array
    {
        return $this->client->products->list($this->outlet()->uuid);
    }

    /** Satu produk outlet toko. */
    public function product(string $productUuid): ProductDetail
    {
        return $this->client->products->get($this->outlet()->uuid, $productUuid);
    }

    /**
     * Metode yang diaktifkan; EDC hanya muncul bila terminalnya dikonfigurasi.
     *
     * @return list<PaymentMethod>
     */
    public function paymentMethods(): array
    {
        $methods = $this->client->paymentMethods->list();

        return array_values(array_filter(
            $methods,
            fn (PaymentMethod $method): bool => $method->paymentMethod !== PaymentMethod::EDC || $this->options->edc !== null,
        ));
    }

    /** Satu order milik toko. */
    public function order(string $uuid): Order
    {
        return $this->client->orders->get($uuid);
    }

    /**
     * Mengubah baris keranjang menjadi item order, dengan harga diambil dari
     * API. Gagal bila sebuah baris menunjuk produk atau varian yang sudah tidak
     * dijual outlet.
     *
     * @param  list<CartLine>  $lines
     * @return list<OrderItem>
     */
    public function priceCart(array $lines): array
    {
        $products = $this->client->products->map($this->outlet()->uuid);

        $items = [];
        foreach ($lines as $line) {
            $product = $products[$line->productUuid] ?? null;
            if ($product === null) {
                throw new YesposException(409, $this->options->messages->productUnavailable);
            }

            $name = $product->name;
            $price = $product->price;
            if ($line->variantUuid !== null && $line->variantUuid !== '') {
                $variant = $product->variant($line->variantUuid);
                if ($variant === null) {
                    throw new YesposException(409, $this->options->messages->productUnavailable);
                }
                $name = $product->name . ' - ' . $variant->name;
                $price = $variant->price;
            }

            $quantity = max(1, $line->quantity);
            $items[] = new OrderItem($name, $quantity, $price, $price * $quantity);
        }

        return $items;
    }

    /**
     * Menempatkan order untuk baris keranjang yang diberikan. Harga datang dari
     * API, bukan dari pemanggil, dan metode pembayarannya harus salah satu yang
     * diaktifkan pemilik client.
     *
     * @param list<CartLine> $lines
     * @param string         $payment ditulis `"<method>:<channel>"`, misalnya `"va:bca"`
     */
    public function placeOrder(array $lines, Customer $customer, string $payment): Order
    {
        $items = $this->priceCart($lines);
        [$method, $channel] = $this->resolvePayment($payment);

        $total = array_sum(array_map(static fn (OrderItem $item): int => $item->total, $items));

        return $this->client->orders->create(new CreateOrderInput(
            totalPayment: $total,
            paymentMethod: $method,
            customer: $customer,
            items: $items,
            paymentChannel: $channel,
            notifyUrl: $this->options->notifyUrl,
            edc: $this->edcFor($method),
        ));
    }

    /**
     * Memindahkan order pending ke metode aktif yang lain. YesPOS mempertahankan
     * total, item, split, dan pembelinya, lalu mengembalikan instruksi
     * pembayaran yang baru.
     *
     * `$customer` hanya diisi untuk order yang belum punya pembeli tersimpan.
     */
    public function changePayment(string $uuid, string $payment, ?Customer $customer = null): Order
    {
        [$method, $channel] = $this->resolvePayment($payment);

        return $this->client->orders->changePaymentMethod($uuid, new ChangeOrderPaymentInput(
            paymentMethod: $method,
            paymentChannel: $channel,
            customer: $customer,
            edc: $this->edcFor($method),
        ));
    }

    /** Terminal EDC hanya ikut terkirim untuk order EDC. */
    private function edcFor(string $method): ?EdcTerminal
    {
        return $method === PaymentMethod::EDC ? $this->options->edc : null;
    }

    /**
     * Mencocokkan `"<method>:<channel>"` dengan metode yang aktif.
     *
     * @return array{0:string,1:string}
     */
    private function resolvePayment(string $payment): array
    {
        [$method, $channel] = array_pad(explode(':', $payment, 2), 2, '');

        foreach ($this->paymentMethods() as $available) {
            if ($available->paymentMethod === $method && $available->paymentChannel === $channel) {
                return [$method, $channel];
            }
        }

        throw new YesposException(400, $this->options->messages->methodUnavailable);
    }

    /**
     * Membuat toko dari variabel environment yang biasa dipakai:
     * `YESPOS_API_BASE_URL`, `YESPOS_CLIENT_KEY`, `YESPOS_CLIENT_SECRET`,
     * `YESPOS_OUTLET_UUID`, `YESPOS_NOTIFY_URL`, dan `YESPOS_EDC_APP_CODE` /
     * `_TERMINAL_CODE` / `_MERCHANT_CODE`.
     *
     * Field `$options` yang terisi menimpa nilai dari environment.
     *
     * @param array<string,string>|null $env
     */
    public static function fromEnv(?ShopOptions $options = null, ?array $env = null): self
    {
        $appCode = Env::read($env, 'YESPOS_EDC_APP_CODE');
        $terminalCode = Env::read($env, 'YESPOS_EDC_TERMINAL_CODE');
        $merchantCode = Env::read($env, 'YESPOS_EDC_MERCHANT_CODE');

        // Terminal yang hanya setengah terisi tetap menyembunyikan EDC.
        $edc = $appCode !== '' && $terminalCode !== '' && $merchantCode !== ''
            ? new EdcTerminal($appCode, $terminalCode, $merchantCode)
            : null;

        $outletUuid = Env::read($env, 'YESPOS_OUTLET_UUID');
        $notifyUrl = Env::read($env, 'YESPOS_NOTIFY_URL');

        return new self(
            new ClientOptions(
                Env::read($env, 'YESPOS_API_BASE_URL'),
                Env::read($env, 'YESPOS_CLIENT_KEY'),
                Env::read($env, 'YESPOS_CLIENT_SECRET'),
            ),
            new ShopOptions(
                outletUuid: $options?->outletUuid ?? ($outletUuid !== '' ? $outletUuid : null),
                notifyUrl: $options?->notifyUrl ?? ($notifyUrl !== '' ? $notifyUrl : null),
                edc: $options?->edc ?? $edc,
                messages: $options?->messages ?? new ShopMessages(),
            ),
        );
    }
}
