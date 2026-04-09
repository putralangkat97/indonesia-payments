<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

use Anggit\IndonesiaPayments\Enums\PaymentStatus;

final class PaymentDetails
{
    /**
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public readonly string $gateway_name,
        public readonly string $payment_id,
        public readonly PaymentStatus $status,
        public readonly int $amount,
        public readonly string $currency,
        public readonly array $raw = [],
    ) {}
}
