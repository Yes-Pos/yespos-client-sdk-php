# Contoh pemakaian

Dua program yang bisa langsung dijalankan. Keduanya memakai environment variable yang sama:

```bash
export YESPOS_API_BASE_URL="https://api.yespos.id"
export YESPOS_CLIENT_KEY="…"
export YESPOS_CLIENT_SECRET="…"
export YESPOS_OUTLET_UUID="…"                      # opsional, kosong = outlet pertama
export YESPOS_NOTIFY_URL="https://…/api/notify"    # opsional, callback status order
```

Jalankan dari akar repo setelah `composer install`.

## `quickstart.php` — CLI

Menampilkan outlet, katalog, dan metode pembayaran yang aktif; bisa sekalian membuat satu order percobaan.

```bash
php examples/quickstart.php                                          # lihat katalog
php examples/quickstart.php --order=p1 --qty=2 --payment=va:bca      # buat order
php examples/quickstart.php --order=p2 --variant=v1 --payment=qris:qris
php examples/quickstart.php --status=<uuid-order>                    # cek status
```

Flag lain: `--outlet=<uuid>`.

## `storefront/` — toko online

Server HTTP dengan katalog, keranjang, checkout, dan penerima callback.

```bash
php -S localhost:8080 examples/storefront/router.php
```

| Endpoint | Guna |
|---|---|
| `GET /` | Halaman toko (HTML + JS, tanpa dependency) |
| `GET /api/catalog` | Outlet dan produknya |
| `GET /api/payment-methods` | Metode aktif; EDC muncul hanya bila terminalnya dikonfigurasi |
| `POST /api/checkout` | Membuat order dari keranjang |
| `GET /api/orders/{uuid}` | Status dan instruksi bayar sebuah order |
| `POST /api/notify` | Callback status order dari YesPOS |

Untuk produksi, taruh `router.php` di belakang Nginx/Apache seperti front controller biasa — `php -S` hanya untuk mencoba.

### Pola yang dipakai

**SDK hanya hidup di server.** Browser memanggil endpoint JSON milik aplikasi sendiri, jadi `YESPOS_CLIENT_SECRET` tidak pernah ikut terkirim ke mana pun selain header signature.

**Harga tidak pernah datang dari browser.** `POST /api/checkout` hanya menerima `product_uuid`, `variant_uuid`, dan `quantity`; `$shop->placeOrder()` mengambil harganya dari API lalu menghitung total sendiri. Angka total yang tampil di halaman cuma perkiraan untuk pembeli.

**Status akhir datang lewat callback, bukan polling.** `POST /api/notify` mencatat perubahan status, lalu membaca ulang order lewat SDK sebagai sumber kebenaran — isi body callback tidak dipercaya begitu saja. Di halaman, status hanya dicek saat pembeli menekan tombol.

**Error dari YesPOS diteruskan apa adanya**, kecuali kegagalan kredensial: itu masalah server, jadi pembeli hanya melihat pesan umum sementara detailnya masuk ke log.

## Menguji tanpa kredensial asli

Kedua contoh ini berbicara dengan API sungguhan. Untuk mencobanya tanpa akun, arahkan `YESPOS_API_BASE_URL` ke server tiruan — `tests/MockApi.php` berisi satu yang memverifikasi signature persis seperti backend, dan `tests/server.php` menyajikannya lewat `php -S`.
