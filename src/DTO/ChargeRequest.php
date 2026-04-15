<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

use Anggit\IndonesiaPayments\Enums\PaymentMethod;

final class ChargeRequest
{
    /**
     * @param array<string,mixed> $customer
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public readonly string $order_id,
        public readonly float $amount,
        public readonly string $currency,
        public readonly PaymentMethod $method,
        public readonly array $customer,
        public readonly array $meta = [],
        public readonly ?string $callback_url = null,
        public readonly ?string $return_url = null,
    ) {}
}
