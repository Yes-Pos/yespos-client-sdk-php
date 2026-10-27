<?php

declare(strict_types=1);

namespace Yespos\Client\Http;

/** Jawaban mentah dari sebuah [Transport]. */
final class TransportResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
