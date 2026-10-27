<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

use Yespos\Client\Internal\Cast;

/** Pembagian pembayaran ke beberapa penerima. */
final class SplitItem
{
    public function __construct(
        public readonly string $va,
        public readonly int $amount,
        public readonly ?string $status = null,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data, 'va'),
            Cast::amount($data, 'amount'),
            Cast::nullableString($data, 'status'),
        );
    }

    /** @return array<string,string|int> */
    public function toArray(): array
    {
        $data = ['va' => $this->va, 'amount' => $this->amount];
        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        return $data;
    }
}
