<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

use Anggit\IndonesiaPayments\Enums\PaymentStatus;

final readonly class ChargeResponse
{
    /**
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public string $gateway_name,
        public string $payment_id,
        public PaymentStatus $status,
        public ?string $redirect_url = null,
        public array $raw = [],
    ) {}
}
