<?php

declare(strict_types=1);

namespace Yespos\Client\Internal;

/**
 * Pembaca nilai dari array JSON yang bentuknya tidak selalu seragam.
 *
 * @internal
 */
final class Cast
{
    /**
     * Nominal rupiah. API kadang mengirim angka bulat, angka desimal, atau
     * string; semuanya diterima dan dibulatkan ke rupiah terdekat.
     *
     * @param array<string,mixed> $data
     */
    public static function amount(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) round((float) $value);
    }

    /** @param array<string,mixed> $data */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return $value === null || $value === '' ? $default : (int) round((float) $value);
    }

    /** @param array<string,mixed> $data */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? null;

        return $value === null || $value === '' ? $default : (float) $value;
    }

    /** @param array<string,mixed> $data */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @param array<string,mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /** @param array<string,mixed> $data */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return $value === null ? $default : (bool) $value;
    }

    /**
     * Daftar sub-array; `null` dan nilai bukan daftar dianggap kosong.
     *
     * @param  array<string,mixed>        $data
     * @return list<array<string,mixed>>
     */
    public static function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * Daftar string, misalnya `required_fields` atau `categories`.
     *
     * @param  array<string,mixed> $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
            array_filter($value, 'is_scalar'),
        ));
    }
}
