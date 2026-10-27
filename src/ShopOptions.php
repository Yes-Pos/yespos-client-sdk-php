<?php

declare(strict_types=1);

namespace Yespos\Client;

use Yespos\Client\Model\EdcTerminal;

/** Pengaturan perilaku [Shop]. */
final class ShopOptions
{
    /**
     * @param string|null       $outletUuid outlet tempat berjualan; kosong = outlet
     *                                      pertama milik pemilik client
     * @param string|null       $notifyUrl  alamat callback status order dari YesPOS
     * @param EdcTerminal|null  $edc        terminal yang menagih pembayaran kartu;
     *                                      tanpa ini EDC disembunyikan
     */
    public function __construct(
        public readonly ?string $outletUuid = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?EdcTerminal $edc = null,
        public readonly ShopMessages $messages = new ShopMessages(),
    ) {
    }
}
