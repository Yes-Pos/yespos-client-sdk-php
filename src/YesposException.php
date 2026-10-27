<?php

declare(strict_types=1);

namespace Yespos\Client;

use RuntimeException;

/**
 * Kegagalan yang dijawab YesPOS API, atau kegagalan menghubunginya.
 *
 * `$status` berisi status HTTP, atau 0 bila request tidak pernah mendapat
 * respons. `$details` membawa field `details` dari API, misalnya daftar produk
 * yang membuat sebuah pengajuan verifikasi ditolak.
 */
final class YesposException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly mixed $details = null,
    ) {
        parent::__construct($message, $status);
    }

    /** Benar bila kredensial ditolak atau toko salah konfigurasi. */
    public function isAuthError(): bool
    {
        return $this->status === 401 || $this->status === 403;
    }

    /** Benar bila YesPOS sama sekali tidak terjangkau. */
    public function isNetworkError(): bool
    {
        return $this->status === 0 || $this->status === 502;
    }
}
