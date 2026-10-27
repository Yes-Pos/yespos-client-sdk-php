<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

/** Body `POST /api/client/orders`. */
final class CreateOrderInput
{
    /**
     * @param list<OrderItem> $items
     * @param string|null     $paymentChannel wajib bila metode punya lebih dari satu
     *                                        channel aktif, misalnya bank virtual account
     * @param list<SplitItem> $split
     */
    public function __construct(
        public readonly int $totalPayment,
        public readonly string $paymentMethod,
        public readonly Customer $customer,
        public readonly array $items,
        public readonly ?string $paymentChannel = null,
        public readonly ?string $notifyUrl = null,
        public readonly array $split = [],
        public readonly ?EdcTerminal $edc = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $data = [
            'total_payment' => $this->totalPayment,
            'payment_method' => $this->paymentMethod,
        ] + $this->customer->toArray();

        if ($this->paymentChannel !== null && $this->paymentChannel !== '') {
            $data['payment_channel'] = $this->paymentChannel;
        }
        $data['items'] = array_map(static fn (OrderItem $item): array => $item->toArray(), $this->items);

        if ($this->notifyUrl !== null && $this->notifyUrl !== '') {
            $data['notify_url'] = $this->notifyUrl;
        }
        if ($this->split !== []) {
            $data['split'] = array_map(static fn (SplitItem $item): array => $item->toArray(), $this->split);
        }
        if ($this->edc !== null) {
            $data += $this->edc->toArray();
        }

        return $data;
    }
}
