<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

final class RefundRequest
{
    public function __construct(
        public readonly string $payment_id,
        public readonly int $amount,
        public readonly ?string $reason = null,
    ) {}
}
