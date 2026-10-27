<?php

declare(strict_types=1);

namespace Yespos\Client;

/** Pembaca variabel environment, dengan sumber yang bisa diganti saat test. */
final class Env
{
    /** @param array<string,string>|null $env */
    public static function read(?array $env, string $key): string
    {
        if ($env !== null) {
            return $env[$key] ?? '';
        }

        // $_ENV kosong di sebagian konfigurasi PHP, jadi getenv() yang dipakai
        // lebih dulu.
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fallback = $_ENV[$key] ?? $_SERVER[$key] ?? '';

        return is_scalar($fallback) ? (string) $fallback : '';
    }
}
