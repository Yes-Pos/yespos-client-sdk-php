<?php

declare(strict_types=1);

namespace Yespos\Client\Model;

/** Body `POST /api/client/orders/:uuid/payment-method`. */
final class ChangeOrderPaymentInput
{
    /**
     * @param Customer|null $customer hanya untuk order yang belum punya pembeli;
     *                                ia lalu ikut disimpan bersama order
     */
    public function __construct(
        public readonly string $paymentMethod,
        public readonly ?string $paymentChannel = null,
        public readonly ?Customer $customer = null,
        public readonly ?EdcTerminal $edc = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $data = ['payment_method' => $this->paymentMethod];

        if ($this->paymentChannel !== null && $this->paymentChannel !== '') {
            $data['payment_channel'] = $this->paymentChannel;
        }
        if ($this->customer !== null) {
            $data += $this->customer->toArray();
        }
        if ($this->edc !== null) {
            $data += $this->edc->toArray();
        }

        return $data;
    }
}
