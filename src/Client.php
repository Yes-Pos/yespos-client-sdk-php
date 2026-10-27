<?php

declare(strict_types=1);

namespace Yespos\Client;

use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Http\HttpClient;
use Yespos\Client\Resource\Orders;
use Yespos\Client\Resource\Outlets;
use Yespos\Client\Resource\PaymentMethods;
use Yespos\Client\Resource\Products;

/**
 * Akses ke YesPOS Client API (`/api/client/*`).
 *
 * Setiap request ditandatangani dengan client secret, jadi ini harus berjalan
 * di server: jangan pernah mengirim secret ke browser.
 *
 * ```php
 * $client = new Client(new ClientOptions($baseUrl, $key, $secret));
 * $outlets = $client->outlets->list();
 * $products = $client->products->list($outlets[0]->uuid);
 * ```
 */
final class Client
{
    public readonly HttpClient $http;
    public readonly Outlets $outlets;
    public readonly Products $products;
    public readonly PaymentMethods $paymentMethods;
    public readonly Orders $orders;

    public function __construct(ClientOptions $options)
    {
        $this->http = new HttpClient($options);
        $this->outlets = new Outlets($this->http);
        $this->products = new Products($this->http);
        $this->paymentMethods = new PaymentMethods($this->http);
        $this->orders = new Orders($this->http);
    }

    /** URL publik sebuah gambar yang diunggah; `GET /media/:token` tidak butuh login. */
    public function mediaUrl(string $token): string
    {
        return $this->http->mediaUrl($token);
    }

    /**
     * Membuat client dari variabel environment yang biasa dipakai:
     * `YESPOS_API_BASE_URL`, `YESPOS_CLIENT_KEY`, `YESPOS_CLIENT_SECRET`.
     *
     * @param array<string,string>|null $env sumber lain selain environment proses
     */
    public static function fromEnv(?array $env = null): self
    {
        return new self(new ClientOptions(
            Env::read($env, 'YESPOS_API_BASE_URL'),
            Env::read($env, 'YESPOS_CLIENT_KEY'),
            Env::read($env, 'YESPOS_CLIENT_SECRET'),
        ));
    }
}
