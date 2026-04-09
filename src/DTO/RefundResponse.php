<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

use Anggit\IndonesiaPayments\Enums\PaymentStatus;

final class RefundResponse
{
    /**
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public readonly string $gateway_name,
        public readonly string $payment_id,
        public readonly PaymentStatus $status,
        public readonly array $raw = [],
    ) {}
}
