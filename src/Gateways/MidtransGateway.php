<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Gateways;

use Anggit\IndonesiaPayments\Contracts\GatewayInterface;
use Anggit\IndonesiaPayments\DTO\{
    ChargeRequest,
    ChargeResponse,
    PaymentDetails,
    RefundRequest,
    RefundResponse,
    WebhookPayload,
    WebhookResult,
};
use Anggit\IndonesiaPayments\Enums\PaymentStatus;
use Anggit\IndonesiaPayments\Support\Http\MidtransHttpClient;
use InvalidArgumentException;

class MidtransGateway implements GatewayInterface
{
    public function __construct(private MidtransHttpClient $client) {}

    public function getName(): string
    {
        return "midtrans";
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $payload = [
            "transaction_details" => [
                "order_id" => $request->order_id,
                "gross_amount" => $request->amount,
            ],
            "customer_details" => [
                "first_name" => $request->customer["name"] ?? null,
                "email" => $request->customer["email"] ?? null,
                "phone" => $request->customer["phone"] ?? null,
            ],
        ];

        if ($request->callback_url || $request->return_url) {
            $payload["callbacks"] = array_filter([
                "finish" => $request->return_url,
                "notification" => $request->callback_url,
            ]);
        }

        $snap = $this->client->createSnapTransaction($payload);

        return new ChargeResponse(
            gateway_name: $this->getName(),
            payment_id: $request->order_id,
            status: PaymentStatus::PENDING,
            redirect_url: $snap["redirect_url"] ?? null,
            raw: $snap,
        );
    }

    public function getPayment(string $payment_id): PaymentDetails
    {
        $transaction = $this->client->getTransactionStatus($payment_id);

        $status = $this->mapStatus($transaction["transaction_status"] ?? null);

        return new PaymentDetails(
            gateway_name: $this->getName(),
            payment_id: $transaction["order_id"] ?? $payment_id,
            status: $status,
            amount: (int) ($transaction["gross_amount"] ?? 0),
            currency: $transaction["currency"] ?? "IDR",
            raw: $transaction,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            "amount" => $request->amount,
        ];

        if ($request->reason !== null) {
            $payload["reason"] = $request->reason;
        }

        $result = $this->client->createRefund($request->payment_id, $payload);

        $status = match ($result["status_code"] ?? null) {
            "200" => PaymentStatus::REFUNDED,
            "201" => PaymentStatus::PENDING,
            default => PaymentStatus::FAILED,
        };

        return new RefundResponse(
            gateway_name: $this->getName(),
            payment_id: $request->payment_id,
            status: $status,
            raw: $result,
        );
    }

    public function handleWebhook(WebhookPayload $payload): WebhookResult
    {
        $data = $payload->json;

        $order_id = $data["order_id"] ?? null;
        $status_code = $data["status_code"] ?? null;
        $gross_amount = $data["gross_amount"] ?? null;
        $signature = $data["signature_key"] ?? null;

        if (
            !is_string($order_id) ||
            !is_string($status_code) ||
            !is_string($gross_amount) ||
            !is_string($signature)
        ) {
            throw new InvalidArgumentException(
                "Invalid Midtrans webhook payload: missing required fields",
            );
        }

        if (
            !$this->client->verifySignature(
                $order_id,
                $status_code,
                $gross_amount,
                $signature,
            )
        ) {
            throw new InvalidArgumentException(
                "Invalid Midtrans webhook signature",
            );
        }

        $status = $this->mapStatus($data["transaction_status"] ?? null);

        return new WebhookResult(
            gateway_name: $this->getName(),
            payment_id: $order_id,
            status: $status,
            raw: $data,
        );
    }

    private function mapStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            "capture", "settlement" => PaymentStatus::PAID,
            "pending", "authorize" => PaymentStatus::PENDING,
            "deny", "cancel" => PaymentStatus::FAILED,
            "expire" => PaymentStatus::EXPIRED,
            "refund", "partial_refund" => PaymentStatus::REFUNDED,
            default => PaymentStatus::PENDING,
        };
    }
}
