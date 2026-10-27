# yespos/client

SDK PHP untuk **YesPOS Client API** (`/api/client/*`): outlet, produk, metode pembayaran, dan order dengan **QRIS**, **Virtual Account**, atau **EDC**.

- **Tanpa dependency.** Hanya butuh ekstensi `curl` dan `json` bawaan PHP.
- **PHP 8.1+.**
- **Objek bertipe**, jadi editor bisa melengkapi `$order->paymentNo` dan sejenisnya.
- **Bisa memakai HTTP client sendiri** lewat PSR-18, lengkap dengan middleware yang sudah terpasang.

> ⚠️ **Hanya untuk server.** Setiap request ditandatangani dengan client secret. Jangan pernah menaruh secret di kode yang dikirim ke browser atau aplikasi pengguna.

## Instalasi

```bash
composer require yespos/client
```

## Mulai cepat

```php
use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Model\CartLine;
use Yespos\Client\Model\Customer;
use Yespos\Client\Shop;
use Yespos\Client\ShopOptions;

$shop = new Shop(
    new ClientOptions(
        baseUrl: getenv('YESPOS_API_BASE_URL'),
        key: getenv('YESPOS_CLIENT_KEY'),
        secret: getenv('YESPOS_CLIENT_SECRET'),
    ),
    new ShopOptions(outletUuid: getenv('YESPOS_OUTLET_UUID') ?: null),
);

$categories = $shop->products();
$methods = $shop->paymentMethods();

$order = $shop->placeOrder(
    [new CartLine('abc-123', 2)],                                        // tanpa harga
    new Customer('Budi', 'budi@example.com', '08123456789'),
    'va:bca',                                                            // "<method>:<channel>"
);

$order->paymentNo;  // nomor VA yang dibayar pembeli
$order->expiredAt;  // batas waktu bayar
```

Atau langsung dari environment variable:

```php
$shop = Shop::fromEnv(); // membaca YESPOS_* dari environment proses
```

## Dua lapisan

| Lapisan | Untuk apa |
|---|---|
| `Client` | Akses mentah ke endpoint. Tidak ada aturan tambahan. |
| `Shop` | Logika toko: pilih outlet, sembunyikan EDC bila tak dikonfigurasi, dan **hitung harga dari API**. |

### `Shop` — dipakai storefront

```php
$shop = new Shop($client, new ShopOptions(
    outletUuid: 'uuid-outlet',                                  // kosong = outlet pertama milik pemilik client
    notifyUrl: 'https://…/callback',                            // callback status order
    edc: new EdcTerminal('YESPOS', 'T1', 'M1'),                 // null = EDC disembunyikan
    messages: new ShopMessages(methodUnavailable: 'Payment method unavailable.'), // untuk terjemahan
));

$shop->outlet();                               // Outlet
$shop->products();                             // ProductCategory[]
$shop->product('uuid-produk');                 // ProductDetail
$shop->paymentMethods();                       // PaymentMethod[]
$shop->priceCart($lines);                      // OrderItem[] dengan harga dari API
$shop->placeOrder($lines, $buyer, 'qris:qris');
$shop->order($uuid);
$shop->changePayment($uuid, 'va:bca');         // order pending → metode lain
$shop->mediaUrl($token);                       // URL gambar publik
```

**Harga selalu diambil dari API.** `placeOrder()` hanya menerima `productUuid`, `variantUuid`, dan `quantity`; totalnya dihitung ulang dari harga yang dibaca API. Harga yang dikirim dari browser tidak dipercaya.

`ShopMessages` memakai pesan bawaan untuk argumen yang tidak diisi, jadi cukup isi yang ingin diterjemahkan.

### `Client` — akses langsung

```php
use Yespos\Client\Client;
use Yespos\Client\Http\ClientOptions;

$client = new Client(new ClientOptions(
    baseUrl: 'https://api.yespos.id',
    key: '…',
    secret: '…',
    timeout: 15.0,
    language: 'id',
));

$client->outlets->list();
$client->products->list($outletUuid);
$client->products->get($outletUuid, $productUuid);
$client->products->map($outletUuid);                  // array<uuid, Product>
$client->paymentMethods->list();
$client->orders->create($input);
$client->orders->get($uuid);
$client->orders->list(page: 1, limit: 10);
$client->orders->changePaymentMethod($uuid, $input);
$client->mediaUrl($token);
```

Endpoint yang belum dibungkus bisa dipanggil lewat transport-nya:

```php
$data = $client->http->get('/endpoint-baru');
$data = $client->http->post('/endpoint-baru', ['field' => 'nilai']);
```

### Memakai HTTP client sendiri

Bawaannya cURL. Untuk memakai Guzzle, Symfony HttpClient, atau apa pun yang mendukung PSR-18 — lengkap dengan retry dan logging yang sudah terpasang:

```bash
composer require psr/http-client psr/http-factory guzzlehttp/guzzle
```

```php
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Yespos\Client\Http\Psr18Transport;

$factory = new HttpFactory();
$client = new Client(new ClientOptions(
    baseUrl: $baseUrl,
    key: $key,
    secret: $secret,
    transport: new Psr18Transport(new Guzzle(['timeout' => 15]), $factory, $factory),
));
```

Opsi cURL tambahan — misalnya proxy — lewat `CurlTransport`:

```php
transport: new CurlTransport([CURLOPT_PROXY => 'http://127.0.0.1:8888']),
```

## Metode pembayaran

`GET /api/client/payment-method` hanya mengembalikan metode yang **diaktifkan oleh pemilik client** di dashboard YesPOS. Metode di luar daftar itu akan ditolak saat membuat order.

Satu pilihan ditulis `"<method>:<channel>"`:

| Nilai | Arti | Field di response order |
|---|---|---|
| `qris:qris` | QRIS | `qrString` → render jadi QR |
| `va:bca`, `va:bri`, … | Virtual account per bank | `paymentNo`, `paymentName`, `expiredAt` |
| `edc:linkpayment` | Kartu lewat EDC | `appLink` → buka di perangkat EDC |

```php
foreach ($shop->paymentMethods() as $method) {
    echo $method->value(), ' ', $method->name, "\n"; // "va:bca BCA"
}
```

## Status order

```php
use Yespos\Client\Model\OrderState;

OrderState::fromStatus('pending');          // OrderState::Pending
OrderState::fromStatus('berhasil_settled'); // OrderState::Paid
OrderState::fromStatus('expired');          // OrderState::Failed

$order->state();
$order->isPending();
$order->isPaid();
```

Status akhir dikirim YesPOS ke `notifyUrl`. Untuk menampilkannya di halaman, panggil `$shop->order($uuid)` saat pembeli menekan tombol cek status — hindari polling berkala.

## Nominal uang

Harga dan total berupa `int` rupiah penuh. API kadang mengirim angka bulat, angka desimal, atau string; semuanya diterima dan dibulatkan ke rupiah terdekat.

```php
$order->totalPayment;                          // int, misalnya 50000
number_format($order->totalPayment, 0, ',', '.'); // "50.000"
```

## Error

Semua kegagalan dilempar sebagai `YesposException`.

```php
use Yespos\Client\YesposException;

try {
    $order = $shop->placeOrder($lines, $buyer, 'va:bca');
} catch (YesposException $failure) {
    $failure->status;            // status HTTP; 0 bila server tak terjangkau
    $failure->getMessage();      // pesan dari API, sudah digabung bila berupa daftar
    $failure->details;           // field `details` dari API, bila ada
    $failure->isNetworkError();  // true bila tidak terhubung / timeout
    $failure->isAuthError();     // true untuk 401/403
}
```

Beberapa yang sering muncul:

| Status | Arti |
|---|---|
| 400 | Metode pembayaran tidak tersedia, atau `payment_channel` wajib diisi |
| 404 | Outlet, produk, atau order tidak ditemukan |
| 409 | Produk di keranjang sudah tidak dijual, atau order sudah tidak pending |
| 401 | Signature ditolak — periksa `key` dan `secret` |

## Konfigurasi

```php
new ClientOptions(
    baseUrl: 'https://api.yespos.id', // tanpa / di akhir (tetap aman bila ada)
    key: '…',                          // header `va`
    secret: '…',                       // penandatangan request, simpan di server
    timeout: 15.0,                     // detik, default 15
    language: 'id',                    // bahasa pesan API, default "id"
    transport: null,                   // opsional: CurlTransport atau Psr18Transport sendiri
);
```

Variabel environment yang dibaca `Shop::fromEnv()` / `Client::fromEnv()`:

```
YESPOS_API_BASE_URL, YESPOS_CLIENT_KEY, YESPOS_CLIENT_SECRET,
YESPOS_OUTLET_UUID, YESPOS_NOTIFY_URL,
YESPOS_EDC_APP_CODE, YESPOS_EDC_TERMINAL_CODE, YESPOS_EDC_MERCHANT_CODE
```

Keduanya menerima sumber lain, berguna di dalam test atau saat memakai `.env` sendiri:

```php
$shop = Shop::fromEnv(null, ['YESPOS_API_BASE_URL' => '…', 'YESPOS_CLIENT_KEY' => '…', /* … */]);
```

Argumen `ShopOptions` yang terisi menimpa nilai dari environment.

## Cara kerja signature

SDK menandatangani setiap request seperti yang dicek `ClientAuthMiddleware` di YesPOS:

```
signature = HMAC-SHA256(secret, "METHOD:key:sha256(body || "{}"):secret")
```

Lalu dikirim sebagai header `va`, `signature`, dan `timestamp`. Fungsinya tersedia sebagai `Signature::sign()` bila diperlukan di luar SDK.

## Contoh pemakaian

Dua program siap jalan ada di [`examples/`](examples):

```bash
php examples/quickstart.php                                 # CLI: katalog, metode, buat order
php -S localhost:8080 examples/storefront/router.php        # toko online
```

`storefront` memperagakan pola yang dipakai storefront sungguhan: SDK hanya di server, browser memanggil endpoint JSON milik aplikasi sendiri, dan harga selalu dihitung ulang dari API. Rinciannya di [`examples/README.md`](examples/README.md).

Untuk Laravel, ada aplikasi contoh tersendiri di [`example-laravel`](https://github.com/Yes-Pos/example-laravel): service provider, controller, Blade, dan penerima callback.

## SDK lain

Bentuk API yang sama tersedia untuk bahasa lain:

- [`@yespos/client`](https://github.com/Yes-Pos/yespos-client-sdk) — TypeScript
- [`yespos-client-sdk-go`](https://github.com/Yes-Pos/yespos-client-sdk-go) — Go

## Pengembangan

```bash
composer install
composer test          # phpunit, termasuk mock yang memverifikasi signature
composer validate --strict
```

Test-nya mencakup transport sungguhan: cURL diuji terhadap server `php -S` yang hidup, dan `Psr18Transport` terhadap client PSR-18.

## Rilis

Versi diterbitkan lewat tag git; Packagist membaca tag dari repo ini.

```bash
git tag v0.1.0
git push --follow-tags
```

Workflow `release.yml` menjalankan `composer validate` + test, membuat GitHub Release, lalu memberi tahu Packagist.

### Menyiapkan auto-update ke Packagist

Repo ini memperbarui Packagist lewat GitHub Actions: `packagist.yml` dipanggil
oleh CI setelah `main` hijau, dan oleh Release setelah test di sebuah tag lulus.
Jadi Packagist hanya pernah diperbarui oleh kode yang lolos test.

Dua langkah, sekali saja.

**1. Daftarkan paketnya.** Buka [packagist.org/packages/submit](https://packagist.org/packages/submit),
isi `https://github.com/Yes-Pos/yespos-client-sdk-php`. Packagist membaca
`composer.json`, jadi nama paketnya mengikuti field `name` di situ: `yespos/client`.

**2. Pasang dua secret.** Ambil tokennya di packagist.org → **Profile → Show API Token**,
lalu di GitHub repo → **Settings → Secrets and variables → Actions → New repository secret**:

| Nama | Isi |
|---|---|
| `PACKAGIST_USERNAME` | username packagist.org |
| `PACKAGIST_TOKEN` | API token dari langkah di atas |

Atau lewat [GitHub CLI](https://cli.github.com):

```bash
gh secret set PACKAGIST_USERNAME --repo Yes-Pos/yespos-client-sdk-php
gh secret set PACKAGIST_TOKEN    --repo Yes-Pos/yespos-client-sdk-php
```

Selesai. Sebelum kedua secret ada, langkahnya otomatis dilewati dan menulis
pengingat di ringkasan job — CI dan Release tetap hijau.

**Menyinkronkan versi yang sudah terlanjur rilis.** Actions → **Packagist** →
**Run workflow**. Berguna untuk tag yang dibuat sebelum secret-nya terpasang.

```bash
composer show yespos/client --all | head -20   # versi yang sudah terbaca Packagist
```

**Alternatif tanpa Actions.** [GitHub App Packagist](https://github.com/apps/packagist)
memperbarui pada setiap push tanpa token sama sekali, atau webhook manual ke
`https://packagist.org/api/update-package?username=<USER>&apiToken=<TOKEN>`.
Keduanya aman dipasang berdampingan dengan workflow ini.

## Lisensi

MIT
