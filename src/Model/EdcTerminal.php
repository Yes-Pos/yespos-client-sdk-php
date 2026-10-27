<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

/** Terminal EDC yang menagih sebuah order. */
final class EdcTerminal
{
    public function __construct(
        public readonly string $appCode,
        public readonly string $terminalCode,
        public readonly string $merchantCode,
    ) {
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return [
            'app_code' => $this->appCode,
            'terminal_code' => $this->terminalCode,
            'merchant_code' => $this->merchantCode,
        ];
    }
}
