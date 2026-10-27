<?php

declare(strict_types=1);

namespace Yespos\Client\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yespos\Client\Client;
use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Model\CartLine;
use Yespos\Client\Model\Customer;
use Yespos\Client\Model\EdcTerminal;
use Yespos\Client\Model\OrderState;
use Yespos\Client\Model\PaymentMethod;
use Yespos\Client\Model\Product;
use Yespos\Client\Model\ProductVariant;
use Yespos\Client\Shop;
use Yespos\Client\ShopMessages;
use Yespos\Client\ShopOptions;
use Yespos\Client\YesposException;

final class ShopTest extends TestCase
{
    private MockApi $api;
    private Customer $buyer;
    private EdcTerminal $edc;

    protected function setUp(): void
    {
        $this->api = new MockApi();
        $this->buyer = new Customer('Budi', 'budi@example.com', '0812');
        $this->edc = new EdcTerminal('YESPOS', 'T1', 'M1');
    }

    private function shop(?ShopOptions $options = null): Shop
    {
        $client = new Client(new ClientOptions('https://api.test', MockApi::KEY, MockApi::SECRET, 15.0, 'id', $this->api));

        return new Shop($client, $options ?? new ShopOptions());
    }

    /** @return list<string> */
    private function values(Shop $shop): array
    {
        return array_map(static fn (PaymentMethod $method): string => $method->value(), $shop->paymentMethods());
    }

    #[Test]
    public function it_uses_the_configured_outlet(): void
    {
        self::assertSame('o2', $this->shop(new ShopOptions(outletUuid: 'o2'))->outlet()->uuid);
    }

    #[Test]
    public function it_falls_back_to_the_first_outlet(): void
    {
        self::assertSame('o1', $this->shop()->outlet()->uuid);
    }

    #[Test]
    public function it_refuses_an_outlet_that_is_not_the_owners(): void
    {
        try {
            $this->shop(new ShopOptions(outletUuid: 'nope'))->outlet();
            self::fail('seharusnya gagal');
        } catch (YesposException $failure) {
            self::assertSame(404, $failure->status);
        }
    }

    #[Test]
    public function it_hides_edc_when_no_terminal_is_configured(): void
    {
        self::assertSame(['qris:qris', 'va:bca'], $this->values($this->shop()));
    }

    #[Test]
    public function it_offers_edc_once_a_terminal_is_configured(): void
    {
        self::assertContains('edc:linkpayment', $this->values($this->shop(new ShopOptions(edc: $this->edc))));
    }

    #[Test]
    public function it_prices_the_cart_from_the_api(): void
    {
        $items = $this->shop()->priceCart([
            new CartLine('p1', 2),
            new CartLine('p2', 1, 'v2'),
        ]);

        self::assertSame(
            [
                ['product_name' => 'Nasi Goreng', 'quantity' => 2, 'price' => 25000, 'total' => 50000],
                ['product_name' => 'Kopi Susu - Besar', 'quantity' => 1, 'price' => 24000, 'total' => 24000],
            ],
            array_map(static fn ($item) => $item->toArray(), $items),
        );
    }

    #[Test]
    public function it_raises_a_quantity_to_at_least_one(): void
    {
        $items = $this->shop()->priceCart([new CartLine('p1', 2), new CartLine('p1', 0), new CartLine('p1', -3)]);
        self::assertSame([2, 1, 1], array_map(static fn ($item) => $item->quantity, $items));
    }

    #[Test]
    public function it_refuses_a_product_or_variant_the_outlet_no_longer_sells(): void
    {
        foreach ([new CartLine('gone', 1), new CartLine('p2', 1, 'gone')] as $line) {
            try {
                $this->shop()->priceCart([$line]);
                self::fail('seharusnya gagal');
            } catch (YesposException $failure) {
                self::assertSame(409, $failure->status);
            }
        }
    }

    #[Test]
    public function it_places_an_order_with_the_total_it_computed(): void
    {
        $shop = $this->shop(new ShopOptions(notifyUrl: 'https://example.com/cb'));
        $order = $shop->placeOrder([new CartLine('p1', 2)], $this->buyer, 'va:bca');

        self::assertSame(50000, $order->totalPayment);
        self::assertSame('va', $order->paymentMethod);
        self::assertSame('8002300000123456', $order->paymentNo);

        $sent = $this->api->lastRequest()['body'];
        self::assertSame(50000, $sent['total_payment']);
        self::assertSame('bca', $sent['payment_channel']);
        self::assertSame('Budi', $sent['name']);
        self::assertSame('https://example.com/cb', $sent['notify_url']);
        // Terminal EDC tidak ikut terkirim untuk order non-EDC.
        self::assertArrayNotHasKey('app_code', $sent);
    }

    #[Test]
    public function it_sends_the_edc_terminal_only_for_an_edc_order(): void
    {
        $shop = $this->shop(new ShopOptions(edc: $this->edc));

        $shop->placeOrder([new CartLine('p1', 1)], $this->buyer, 'edc:linkpayment');
        self::assertSame('YESPOS', $this->api->lastRequest()['body']['app_code']);

        $shop->placeOrder([new CartLine('p1', 1)], $this->buyer, 'qris:qris');
        self::assertArrayNotHasKey('app_code', $this->api->lastRequest()['body']);
    }

    #[Test]
    public function it_refuses_a_method_the_owner_did_not_activate(): void
    {
        foreach (['va:mandiri', 'edc:linkpayment', 'cash:cash', 'qris', ''] as $payment) {
            try {
                $this->shop()->placeOrder([new CartLine('p1', 1)], $this->buyer, $payment);
                self::fail("seharusnya gagal untuk {$payment}");
            } catch (YesposException $failure) {
                self::assertSame(400, $failure->status, $payment);
            }
        }
    }

    #[Test]
    public function it_changes_the_payment_method_of_a_pending_order(): void
    {
        $shop = $this->shop();
        $order = $shop->placeOrder([new CartLine('p1', 1)], $this->buyer, 'qris:qris');

        $changed = $shop->changePayment($order->uuid, 'va:bca');
        self::assertSame('va', $changed->paymentMethod);
        self::assertSame('8002300000999999', $changed->paymentNo);
        self::assertNull($changed->qrString);
        // Pembeli yang tersimpan bersama order dipakai ulang.
        self::assertArrayNotHasKey('name', $this->api->lastRequest()['body']);
    }

    #[Test]
    public function it_sends_a_buyer_only_when_one_is_given(): void
    {
        $shop = $this->shop();
        $order = $shop->placeOrder([new CartLine('p1', 1)], $this->buyer, 'qris:qris');

        $shop->changePayment($order->uuid, 'va:bca', new Customer('Citra', 'citra@example.com', '0814'));
        $sent = $this->api->lastRequest()['body'];
        self::assertSame('Citra', $sent['name']);
        self::assertSame('0814', $sent['phone']);
    }

    #[Test]
    public function custom_messages_replace_only_what_is_given(): void
    {
        $shop = $this->shop(new ShopOptions(
            messages: new ShopMessages(methodUnavailable: 'Payment method unavailable.'),
        ));

        try {
            $shop->placeOrder([new CartLine('p1', 1)], $this->buyer, 'cash:cash');
            self::fail('seharusnya gagal');
        } catch (YesposException $failure) {
            self::assertSame('Payment method unavailable.', $failure->getMessage());
        }

        try {
            $shop->priceCart([new CartLine('gone', 1)]);
            self::fail('seharusnya gagal');
        } catch (YesposException $failure) {
            self::assertStringContainsString('tidak tersedia', $failure->getMessage());
        }
    }

    #[Test]
    public function it_builds_a_shop_from_environment_variables(): void
    {
        $shop = Shop::fromEnv(null, [
            'YESPOS_API_BASE_URL' => 'https://api.test',
            'YESPOS_CLIENT_KEY' => MockApi::KEY,
            'YESPOS_CLIENT_SECRET' => MockApi::SECRET,
            'YESPOS_OUTLET_UUID' => 'o2',
            'YESPOS_NOTIFY_URL' => 'https://example.com/cb',
            'YESPOS_EDC_APP_CODE' => 'YESPOS',
            'YESPOS_EDC_TERMINAL_CODE' => 'T1',
            'YESPOS_EDC_MERCHANT_CODE' => 'M1',
        ]);

        // Dibangun dari environment, jadi hanya konfigurasinya yang diperiksa.
        self::assertSame('https://api.test/media/abc', $shop->mediaUrl('abc'));
    }

    #[Test]
    public function it_hides_edc_when_the_terminal_is_only_half_configured(): void
    {
        $shop = Shop::fromEnv(null, [
            'YESPOS_API_BASE_URL' => 'https://api.test',
            'YESPOS_CLIENT_KEY' => MockApi::KEY,
            'YESPOS_CLIENT_SECRET' => MockApi::SECRET,
            'YESPOS_EDC_APP_CODE' => 'YESPOS',
        ]);

        // Terminal setengah jadi diperlakukan seperti tidak ada sama sekali.
        $reflection = new \ReflectionProperty(Shop::class, 'options');
        /** @var ShopOptions $options */
        $options = $reflection->getValue($shop);
        self::assertNull($options->edc);
    }

    #[Test]
    public function it_reads_order_states(): void
    {
        self::assertSame(OrderState::Pending, OrderState::fromStatus('pending'));
        self::assertSame(OrderState::Pending, OrderState::fromStatus('pending_unsettled'));
        self::assertSame(OrderState::Paid, OrderState::fromStatus('berhasil_settled'));
        self::assertSame(OrderState::Paid, OrderState::fromStatus('Berhasil'));
        self::assertSame(OrderState::Paid, OrderState::fromStatus('success'));
        self::assertSame(OrderState::Failed, OrderState::fromStatus('expired'));
    }

    #[Test]
    public function it_knows_when_a_product_is_sold_out(): void
    {
        $make = static fn (bool $trackStock, float $stock, array $variants = []): Product => new Product(
            'x', 'x', null, 1, 'pcs', 't', $stock, $trackStock, $variants,
        );
        $variants = [
            new ProductVariant('a', 'a', 1, 0),
            new ProductVariant('b', 'b', 2, 4),
        ];

        self::assertFalse($make(false, 0)->isSoldOut());
        self::assertTrue($make(true, 0)->isSoldOut());
        self::assertFalse($make(true, 3)->isSoldOut());
        self::assertFalse($make(true, 0, $variants)->isSoldOut());
        self::assertTrue($make(true, 0, [$variants[0]])->isSoldOut());
    }

    #[Test]
    public function it_reads_a_price_range_over_variants(): void
    {
        $plain = new Product('x', 'x', null, 10, 'pcs', 't', 0, false);
        self::assertSame(['min' => 10, 'max' => 10], $plain->priceRange());

        $withVariants = new Product('x', 'x', null, 10, 'pcs', 't', 0, false, [
            new ProductVariant('a', 'a', 30, 1),
            new ProductVariant('b', 'b', 20, 1),
        ]);
        self::assertSame(['min' => 20, 'max' => 30], $withVariants->priceRange());
    }
}
