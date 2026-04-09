<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Contracts;

use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\DTO\ChargeResponse;
use Anggit\IndonesiaPayments\DTO\PaymentDetails;
use Anggit\IndonesiaPayments\DTO\RefundRequest;
use Anggit\IndonesiaPayments\DTO\RefundResponse;
use Anggit\IndonesiaPayments\DTO\WebhookPayload;
use Anggit\IndonesiaPayments\DTO\WebhookResult;

interface GatewayInterface
{
    public function getName(): string;

    public function charge(ChargeRequest $request): ChargeResponse;

    public function getPayment(string $payment_id): PaymentDetails;

    public function refund(RefundRequest $request): RefundResponse;

    public function handleWebhook(WebhookPayload $payload): WebhookResult;
}
