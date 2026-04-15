<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Tests\Gateway;

use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\DTO\ChargeResponse;
use Anggit\IndonesiaPayments\DTO\PaymentDetails;
use Anggit\IndonesiaPayments\DTO\RefundRequest;
use Anggit\IndonesiaPayments\DTO\RefundResponse;
use Anggit\IndonesiaPayments\DTO\WebhookPayload;
use Anggit\IndonesiaPayments\DTO\WebhookResult;
use Anggit\IndonesiaPayments\Enums\PaymentMethod;
use Anggit\IndonesiaPayments\Enums\PaymentStatus;
use Anggit\IndonesiaPayments\Gateways\XenditGateway;
use Anggit\IndonesiaPayments\Support\Http\XenditHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class XenditGatewayTest extends TestCase
{
    private XenditHttpClient|MockObject $client;
    private XenditGateway $gateway;
    private string $webhook_token = "xnd_test_webhook_verification_token";

    protected function setUp(): void
    {
        $this->client = $this->createMock(XenditHttpClient::class);
        $this->gateway = new XenditGateway($this->client);
    }

    public function test_get_name(): void
    {
        $this->assertSame("xendit", $this->gateway->getName());
    }

    public function test_charge_creates_invoice_and_returns_response(): void
    {
        $request = new ChargeRequest(
            order_id: "ORDER-001",
            amount: (float) 100000,
            currency: "IDR",
            method: PaymentMethod::QRIS,
            customer: ["email" => "user@example.com"],
            meta: ["description" => "Test payment"],
            return_url: "https://example.com/success",
        );

        $this->client
            ->expects($this->once())
            ->method("createInvoice")
            ->with(
                $this->callback(function (array $payload) {
                    return $payload["external_id"] === "ORDER-001" &&
                        $payload["amount"] === 100000 &&
                        $payload["payer_email"] === "user@example.com" &&
                        $payload["description"] === "Test payment" &&
                        $payload["success_return_url"] ===
                            "https://example.com/success";
                }),
            )
            ->willReturn([
                "id" => "inv_123",
                "status" => "PENDING",
                "invoice_url" => "https://checkout.xendit.co/inv_123",
            ]);

        $response = $this->gateway->charge($request);

        $this->assertInstanceOf(ChargeResponse::class, $response);
        $this->assertSame("xendit", $response->gateway_name);
        $this->assertSame("inv_123", $response->payment_id);
        $this->assertSame(PaymentStatus::PENDING, $response->status);
        $this->assertSame(
            "https://checkout.xendit.co/inv_123",
            $response->redirect_url,
        );
    }

    public function test_get_payment_returns_details(): void
    {
        $this->client
            ->expects($this->once())
            ->method("getInvoice")
            ->with("inv_123")
            ->willReturn([
                "id" => "inv_123",
                "status" => "PAID",
                "amount" => 100000,
                "currency" => "IDR",
            ]);

        $details = $this->gateway->getPayment("inv_123");

        $this->assertInstanceOf(PaymentDetails::class, $details);
        $this->assertSame("inv_123", $details->payment_id);
        $this->assertSame(PaymentStatus::PAID, $details->status);
        $this->assertSame(100000, $details->amount);
        $this->assertSame("IDR", $details->currency);
    }

    public function test_refund_creates_refund_and_returns_response(): void
    {
        $request = new RefundRequest(
            payment_id: "inv_123",
            amount: 50000,
            reason: "Customer request",
        );

        $this->client
            ->expects($this->once())
            ->method("createRefund")
            ->with(
                $this->callback(function (array $payload) {
                    return $payload["invoice_id"] === "inv_123" &&
                        $payload["amount"] === 50000 &&
                        $payload["reason"] === "Customer request";
                }),
            )
            ->willReturn([
                "id" => "ref_456",
                "status" => "SUCCEEDED",
            ]);

        $response = $this->gateway->refund($request);

        $this->assertInstanceOf(RefundResponse::class, $response);
        $this->assertSame(PaymentStatus::REFUNDED, $response->status);
        $this->assertSame("inv_123", $response->payment_id);
    }

    public function test_refund_pending_status(): void
    {
        $request = new RefundRequest(payment_id: "inv_123", amount: 50000);

        $this->client
            ->method("createRefund")
            ->willReturn(["id" => "ref_456", "status" => "PENDING"]);

        $response = $this->gateway->refund($request);
        $this->assertSame(PaymentStatus::PENDING, $response->status);
    }

    public function test_handle_webhook_with_valid_token(): void
    {
        $body = json_encode([
            "id" => "inv_123",
            "external_id" => "ORDER-001",
            "status" => "PAID",
        ]);

        $payload = new WebhookPayload(
            provider: "xendit",
            raw_body: $body,
            headers: ["x-callback-token" => [$this->webhook_token]],
            json: json_decode($body, true),
        );

        $this->client
            ->expects($this->once())
            ->method("verifyCallbackToken")
            ->with($this->webhook_token)
            ->willReturn(true);

        $result = $this->gateway->handleWebhook($payload);

        $this->assertInstanceOf(WebhookResult::class, $result);
        $this->assertSame("xendit", $result->gateway_name);
        $this->assertSame("inv_123", $result->payment_id);
        $this->assertSame(PaymentStatus::PAID, $result->status);
    }

    public function test_handle_webhook_with_invalid_token_throws(): void
    {
        $body = json_encode(["id" => "inv_123", "status" => "PAID"]);

        $payload = new WebhookPayload(
            provider: "xendit",
            raw_body: $body,
            headers: ["x-callback-token" => ["wrong-token"]],
            json: json_decode($body, true),
        );

        $this->client->method("verifyCallbackToken")->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Xendit callback token");

        $this->gateway->handleWebhook($payload);
    }

    public function test_handle_webhook_without_token_throws(): void
    {
        $payload = new WebhookPayload(
            provider: "xendit",
            raw_body: "{}",
            headers: [],
            json: [],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->gateway->handleWebhook($payload);
    }

    public function test_settled_status_maps_to_paid(): void
    {
        $this->client->method("getInvoice")->willReturn([
            "id" => "inv_123",
            "status" => "SETTLED",
            "amount" => 100000,
            "currency" => "IDR",
        ]);

        $details = $this->gateway->getPayment("inv_123");
        $this->assertSame(PaymentStatus::PAID, $details->status);
    }

    public function test_refund_cancelled_status_maps_to_failed(): void
    {
        $request = new RefundRequest(payment_id: "inv_123", amount: 50000);

        $this->client
            ->method("createRefund")
            ->willReturn(["id" => "ref_456", "status" => "CANCELLED"]);

        $response = $this->gateway->refund($request);
        $this->assertSame(PaymentStatus::FAILED, $response->status);
    }

    public function test_map_status_defaults_to_pending(): void
    {
        $this->client->method("getInvoice")->willReturn([
            "id" => "inv_123",
            "status" => "UNKNOWN_STATUS",
            "amount" => 100000,
            "currency" => "IDR",
        ]);

        $details = $this->gateway->getPayment("inv_123");
        $this->assertSame(PaymentStatus::PENDING, $details->status);
    }

    public function test_charge_with_failure_redirect_url(): void
    {
        $request = new ChargeRequest(
            order_id: "ORDER-002",
            amount: (float) 50000,
            currency: "IDR",
            method: PaymentMethod::EWALLET,
            customer: ["email" => "user@example.com"],
            meta: ["failure_redirect_url" => "https://example.com/failed"],
        );

        $this->client
            ->expects($this->once())
            ->method("createInvoice")
            ->with(
                $this->callback(function (array $payload) {
                    return $payload["failure_return_url"] ===
                        "https://example.com/failed";
                }),
            )
            ->willReturn([
                "id" => "inv_456",
                "status" => "PENDING",
                "invoice_url" => "https://checkout.xendit.co/inv_456",
            ]);

        $this->gateway->charge($request);
    }
}
