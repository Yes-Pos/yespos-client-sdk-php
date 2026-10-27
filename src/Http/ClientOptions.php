<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

/** Konfigurasi untuk membuat [\Yespos\Client\Client] atau [HttpClient]. */
final class ClientOptions
{
    /**
     * @param string         $baseUrl  alamat YesPOS API, misalnya "https://api.yespos.id";
     *                                 garis miring di akhir boleh ada
     * @param string         $key      client credential key, dikirim sebagai header `va`
     * @param string         $secret   penandatangan setiap request; harus tetap di server
     * @param float          $timeout  batas waktu satu request dalam detik
     * @param string         $language bahasa pesan API
     * @param Transport|null $transport pengirim request; bawaannya cURL
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $key,
        public readonly string $secret,
        public readonly float $timeout = 15.0,
        public readonly string $language = 'id',
        public readonly ?Transport $transport = null,
    ) {
    }
}
