<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Outlet milik pemilik client (`GET /api/client/outlets`). */
final class Outlet
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $address = null,
        public readonly ?string $contact = null,
        public readonly ?string $type = null,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'uuid'),
            Cast::string($data, 'name'),
            Cast::nullableString($data, 'address'),
            Cast::nullableString($data, 'contact'),
            Cast::nullableString($data, 'type'),
        );
    }
}
