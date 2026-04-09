<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Contracts;

use Anggit\IndonesiaPayments\DTO\{
    ChargeRequest,
    ChargeResponse,
    PaymentDetails,
    RefundRequest,
    RefundResponse,
    WebhookPayload,
    WebhookResult,
};

interface GatewayInterface
{
    public function getName(): string;

    public function charge(ChargeRequest $request): ChargeResponse;

    public function getPayment(string $payment_id): PaymentDetails;

    public function refund(RefundRequest $request): RefundResponse;

    public function handleWebhook(WebhookPayload $payload): WebhookResult;
}
