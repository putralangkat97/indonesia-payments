<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

final readonly class RefundRequest
{
    public function __construct(
        public string $payment_id,
        public int $amount,
        public ?string $reason = null,
    ) {}
}
