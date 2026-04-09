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
use Anggit\IndonesiaPayments\Support\Http\XenditHttpClient;
use InvalidArgumentException;

class XenditGateway implements GatewayInterface
{
    public function __construct(private XenditHttpClient $client) {}

    public function getName(): string
    {
        return "xendit";
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $external_id = $request->order_id;

        $payload = [
            "external_id" => $external_id,
            "amount" => $request->amount,
            "description" =>
                $request->meta["description"] ?? "Order {$external_id}",
            "payer_email" => $request->customer["email"] ?? null,
        ];

        if ($request->return_url) {
            $payload["success_return_url"] = $request->return_url;
        }

        if (isset($request->meta["failure_redirect_url"])) {
            $payload["failure_return_url"] =
                $request->meta["failure_redirect_url"];
        }

        $invoice = $this->client->createInvoice($payload);

        $status = $this->mapStatus($invoice["status"] ?? null);

        return new ChargeResponse(
            gateway_name: $this->getName(),
            payment_id: $invoice["id"],
            status: $status,
            redirect_url: $invoice["invoice_url"] ?? null,
            raw: $invoice,
        );
    }

    public function getPayment(string $payment_id): PaymentDetails
    {
        $invoice = $this->client->getInvoice($payment_id);

        $status = $this->mapStatus($invoice["status"] ?? null);

        return new PaymentDetails(
            gateway_name: $this->getName(),
            payment_id: $invoice["id"],
            status: $status,
            amount: (int) ($invoice["amount"] ?? 0),
            currency: $invoice["currency"] ?? "IDR",
            raw: $invoice,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            "invoice_id" => $request->payment_id,
            "amount" => $request->amount,
        ];

        if ($request->reason !== null) {
            $payload["reason"] = $request->reason;
        }

        $result = $this->client->createRefund($payload);

        $status = match ($result["status"] ?? null) {
            "SUCCEEDED" => PaymentStatus::REFUNDED,
            "FAILED" => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
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
        $raw_body = $payload->raw_body;
        $headers = $payload->headers;

        $signature =
            $headers["x-callback-signature"][0] ??
            ($headers["X-Signature"][0] ?? null);

        if (
            !is_string($signature) ||
            !$this->client->verifyCallbackSignature($raw_body, $signature)
        ) {
            throw new InvalidArgumentException(
                "Invalid Xendit callback signature",
            );
        }

        $data = $payload->json;

        $status = $this->mapStatus($data["status"] ?? null);

        $payment_id = $data["id"] ?? ($data["external_id"] ?? null);

        return new WebhookResult(
            gateway_name: $this->getName(),
            payment_id: $payment_id,
            status: $status,
            raw: $data,
        );
    }

    private function mapStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            "PAID" => PaymentStatus::PAID,
            "PENDING" => PaymentStatus::PENDING,
            "EXPIRED" => PaymentStatus::EXPIRED,
            "FAILED" => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
