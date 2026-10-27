<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Pembeli sebuah order. */
final class Customer
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'name'),
            Cast::string($data, 'email'),
            Cast::string($data, 'phone'),
        );
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'phone' => $this->phone];
    }
}
