<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

/** Status order yang sudah disederhanakan. */
enum OrderState: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    /**
     * Membaca status order. Status YesPOS berupa `"pending"` atau
     * `"<status>_<settlement>"`, misalnya `"berhasil_settled"`.
     */
    public static function fromStatus(string $status): self
    {
        $value = strtolower($status);

        return match (true) {
            str_starts_with($value, 'pending') => self::Pending,
            str_starts_with($value, 'berhasil'), str_starts_with($value, 'success') => self::Paid,
            default => self::Failed,
        };
    }
}
