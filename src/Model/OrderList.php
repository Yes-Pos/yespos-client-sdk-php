<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Satu halaman order. */
final class OrderList
{
    /** @param list<Order> $data */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            array_map(Order::fromArray(...), Cast::rows($payload, 'data')),
            Cast::int($payload, 'total'),
            Cast::int($payload, 'page'),
            Cast::int($payload, 'limit'),
        );
    }
}
