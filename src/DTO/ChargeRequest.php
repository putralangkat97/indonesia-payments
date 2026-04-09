<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

use Anggit\IndonesiaPayments\Enums\PaymentMethod;

final readonly class ChargeRequest
{
    /**
     * @param array<string,mixed> $customer
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public string $order_id,
        public int $amount,
        public string $currency,
        public PaymentMethod $method,
        public array $customer,
        public array $meta = [],
        public ?string $callback_url = null,
        public ?string $return_url = null,
    ) {}
}
