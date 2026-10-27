<?php

declare(strict_types=1);

namespace Yespos\Client\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Yespos\Client\Client;
use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Http\Signature;
use Yespos\Client\Model\ChangeOrderPaymentInput;
use Yespos\Client\Model\CreateOrderInput;
use Yespos\Client\Model\Customer;
use Yespos\Client\Model\OrderItem;
use Yespos\Client\YesposException;

final class ClientTest extends TestCase
{
    private MockApi $api;

    protected function setUp(): void
    {
        $this->api = new MockApi();
    }

    private function client(float $timeout = 15.0, string $language = 'id', string $baseUrl = 'https://api.test'): Client
    {
        return new Client(new ClientOptions($baseUrl, MockApi::KEY, MockApi::SECRET, $timeout, $language, $this->api));
    }

    #[Test]
    public function signature_matches_the_backend(): void
    {
        // Vektor tetap: backend menghashkan body kosong sebagai "{}".
        self::assertSame(Signature::sign('GET', 'k', 's', '{}'), Signature::sign('GET', 'k', 's', ''));

        $signature = Signature::sign('POST', 'k', 's', '{"a":1}');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);

        // Method dan body sama-sama mengubah tanda tangan.
        self::assertNotSame($signature, Signature::sign('GET', 'k', 's', '{"a":1}'));
        self::assertNotSame($signature, Signature::sign('POST', 'k', 's', '{"a":2}'));

        // Huruf kecil pada method diperlakukan sama dengan huruf besar.
        self::assertSame($signature, Signature::sign('post', 'k', 's', '{"a":1}'));
    }

    #[Test]
    public function signature_is_accepted_by_a_server_that_checks_it(): void
    {
        self::assertCount(2, $this->client()->outlets->list());
    }

    #[Test]
    public function signature_is_rejected_when_the_secret_is_wrong(): void
    {
        $wrong = new Client(new ClientOptions('https://api.test', MockApi::KEY, 'nope', 15.0, 'id', $this->api));

        try {
            $wrong->outlets->list();
            self::fail('seharusnya ditolak');
        } catch (YesposException $failure) {
            self::assertSame(401, $failure->status);
            self::assertTrue($failure->isAuthError());
        }
    }

    #[Test]
    public function it_refuses_to_build_without_credentials(): void
    {
        $this->expectException(YesposException::class);
        new Client(new ClientOptions('', '', ''));
    }

    #[Test]
    public function it_trims_a_trailing_slash_and_builds_media_urls(): void
    {
        $padded = $this->client(baseUrl: 'https://api.test///');
        self::assertSame('https://api.test/media/2_abc', $padded->mediaUrl('2_abc'));
    }

    #[Test]
    public function it_sends_the_configured_language(): void
    {
        $this->client(language: 'en')->outlets->list();
        self::assertSame('en', $this->api->lastRequest()['language']);

        $this->client()->outlets->list();
        self::assertSame('id', $this->api->lastRequest()['language']);
    }

    #[Test]
    public function it_lists_products_and_maps_them_by_uuid_once(): void
    {
        $categories = $this->client()->products->list('o1');
        self::assertSame(
            ['Makanan', 'Favorit'],
            array_map(static fn ($category) => $category->categoryName, $categories),
        );

        // Nasi Goreng ada di dua kategori tapi hanya tercatat sekali.
        $products = $this->client()->products->map('o1');
        self::assertSame(['p1', 'p2'], array_keys($products));
        self::assertSame(25000, $products['p1']->price);
        // Harga yang datang sebagai string tetap terbaca sebagai rupiah bulat.
        self::assertSame(18000, $products['p2']->price);
    }

    #[Test]
    public function it_gets_one_product_with_its_categories(): void
    {
        $product = $this->client()->products->get('o1', 'p2');
        self::assertSame('Kopi Susu', $product->name);
        self::assertSame(['Makanan'], $product->categories);
        self::assertCount(2, $product->variants);
        self::assertSame(['min' => 18000, 'max' => 24000], $product->priceRange());
    }

    #[Test]
    public function it_reports_an_unknown_product_as_404(): void
    {
        try {
            $this->client()->products->get('o1', 'nope');
            self::fail('seharusnya gagal');
        } catch (YesposException $failure) {
            self::assertSame(404, $failure->status);
            self::assertSame('Produk tidak ditemukan.', $failure->getMessage());
        }
    }

    #[Test]
    public function it_creates_reads_and_lists_orders(): void
    {
        $client = $this->client();
        $order = $client->orders->create(new CreateOrderInput(
            totalPayment: 50000,
            paymentMethod: 'qris',
            customer: new Customer('Budi', 'budi@example.com', '0812'),
            items: [new OrderItem('Nasi Goreng', 2, 25000, 50000)],
            paymentChannel: 'qris',
        ));

        self::assertNotNull($order->qrString);
        self::assertTrue($client->orders->get($order->uuid)->isPending());

        $list = $client->orders->list(page: 1, limit: 10);
        self::assertSame(1, $list->page);
        self::assertSame(1, $list->total);
        self::assertCount(1, $list->data);
    }

    #[Test]
    public function it_changes_the_payment_method_of_a_pending_order(): void
    {
        $client = $this->client();
        $order = $client->orders->create(new CreateOrderInput(
            totalPayment: 25000,
            paymentMethod: 'qris',
            customer: new Customer('Budi', 'budi@example.com', '0812'),
            items: [new OrderItem('Nasi Goreng', 1, 25000, 25000)],
            paymentChannel: 'qris',
        ));

        $changed = $client->orders->changePaymentMethod($order->uuid, new ChangeOrderPaymentInput('va', 'bca'));
        self::assertSame('va', $changed->paymentMethod);
        self::assertSame('8002300000999999', $changed->paymentNo);
        self::assertNull($changed->qrString);
    }

    #[Test]
    public function it_joins_a_list_of_validation_messages(): void
    {
        $this->api->failNext(400, ['message' => ['payment_method:required', 'email:email']]);

        $this->expectExceptionMessage('payment_method:required email:email');
        $this->client()->outlets->list();
    }

    #[Test]
    public function it_keeps_the_details_field_of_a_refusal(): void
    {
        $this->api->failNext(422, [
            'message' => 'tidak lengkap',
            'details' => [['uuid' => 'p1', 'missing' => ['image']]],
        ]);

        try {
            $this->client()->outlets->list();
            self::fail('seharusnya gagal');
        } catch (YesposException $failure) {
            self::assertSame([['uuid' => 'p1', 'missing' => ['image']]], $failure->details);
        }
    }

    #[Test]
    public function it_falls_back_to_a_message_when_the_body_has_none(): void
    {
        $this->api->failNext(500, []);

        $this->expectExceptionMessage('Permintaan gagal (500).');
        $this->client()->outlets->list();
    }

    #[Test]
    public function it_builds_a_client_from_environment_variables(): void
    {
        $client = Client::fromEnv([
            'YESPOS_API_BASE_URL' => 'https://api.test',
            'YESPOS_CLIENT_KEY' => MockApi::KEY,
            'YESPOS_CLIENT_SECRET' => MockApi::SECRET,
        ]);

        self::assertSame('https://api.test/media/abc', $client->mediaUrl('abc'));
    }

    #[Test]
    public function the_transport_is_reachable_for_endpoints_the_sdk_does_not_wrap(): void
    {
        $rows = $this->client()->http->get('/outlets');
        self::assertIsArray($rows);
        self::assertCount(2, $rows);
    }
}
