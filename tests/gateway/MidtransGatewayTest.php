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
use Anggit\IndonesiaPayments\Gateways\MidtransGateway;
use Anggit\IndonesiaPayments\Support\Http\MidtransHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MidtransGatewayTest extends TestCase
{
    private MidtransHttpClient|MockObject $client;
    private MidtransGateway $gateway;
    private string $server_key = 'SB-Mid-server-test123';

    protected function setUp(): void
    {
        $this->client = $this->createMock(MidtransHttpClient::class);
        $this->gateway = new MidtransGateway($this->client);
    }

    public function test_get_name(): void
    {
        $this->assertSame('midtrans', $this->gateway->getName());
    }

    public function test_charge_creates_snap_transaction(): void
    {
        $request = new ChargeRequest(
            order_id: 'ORDER-001',
            amount: 100000,
            currency: 'IDR',
            method: PaymentMethod::QRIS,
            customer: [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '08123456789',
            ],
            return_url: 'https://example.com/finish',
        );

        $this->client
            ->expects($this->once())
            ->method('createSnapTransaction')
            ->with($this->callback(function (array $payload) {
                return (
                    $payload['transaction_details']['order_id'] === 'ORDER-001'
                    && $payload['transaction_details']['gross_amount'] === 100000
                    && $payload['customer_details']['email'] === 'john@example.com'
                    && $payload['callbacks']['finish'] === 'https://example.com/finish'
                );
            }))
            ->willReturn([
                'token' => 'snap_token_123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/snap_token_123',
            ]);

        $response = $this->gateway->charge($request);

        $this->assertInstanceOf(ChargeResponse::class, $response);
        $this->assertSame('midtrans', $response->gateway_name);
        $this->assertSame('ORDER-001', $response->payment_id);
        $this->assertSame(PaymentStatus::PENDING, $response->status);
        $this->assertNotNull($response->redirect_url);
    }

    public function test_get_payment_returns_details(): void
    {
        $this->client
            ->expects($this->once())
            ->method('getTransactionStatus')
            ->with('ORDER-001')
            ->willReturn([
                'order_id' => 'ORDER-001',
                'transaction_status' => 'settlement',
                'gross_amount' => '100000.00',
                'currency' => 'IDR',
            ]);

        $details = $this->gateway->getPayment('ORDER-001');

        $this->assertInstanceOf(PaymentDetails::class, $details);
        $this->assertSame('ORDER-001', $details->payment_id);
        $this->assertSame(PaymentStatus::PAID, $details->status);
        $this->assertSame(100000, $details->amount);
    }

    public function test_refund_returns_refunded_status(): void
    {
        $request = new RefundRequest(payment_id: 'ORDER-001', amount: 50000, reason: 'Customer request');

        $this->client
            ->expects($this->once())
            ->method('createRefund')
            ->with('ORDER-001', $this->callback(function (array $payload) {
                return $payload['amount'] === 50000 && $payload['reason'] === 'Customer request';
            }))
            ->willReturn([
                'status_code' => '200',
                'refund_key' => 'ref_123',
            ]);

        $response = $this->gateway->refund($request);

        $this->assertInstanceOf(RefundResponse::class, $response);
        $this->assertSame(PaymentStatus::REFUNDED, $response->status);
    }

    public function test_refund_pending_status(): void
    {
        $request = new RefundRequest(payment_id: 'ORDER-001', amount: 50000);

        $this->client->method('createRefund')->willReturn(['status_code' => '201']);

        $response = $this->gateway->refund($request);
        $this->assertSame(PaymentStatus::PENDING, $response->status);
    }

    public function test_handle_webhook_with_valid_signature(): void
    {
        $order_id = 'ORDER-001';
        $status_code = '200';
        $gross_amount = '100000.00';
        $signature = hash('sha512', $order_id . $status_code . $gross_amount . $this->server_key);

        $data = [
            'order_id' => $order_id,
            'status_code' => $status_code,
            'gross_amount' => $gross_amount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];

        $payload = new WebhookPayload(provider: 'midtrans', raw_body: json_encode($data), headers: [], json: $data);

        $this->client
            ->expects($this->once())
            ->method('verifySignature')
            ->with($order_id, $status_code, $gross_amount, $signature)
            ->willReturn(true);

        $result = $this->gateway->handleWebhook($payload);

        $this->assertInstanceOf(WebhookResult::class, $result);
        $this->assertSame('midtrans', $result->gateway_name);
        $this->assertSame('ORDER-001', $result->payment_id);
        $this->assertSame(PaymentStatus::PAID, $result->status);
    }

    public function test_handle_webhook_with_invalid_signature_throws(): void
    {
        $data = [
            'order_id' => 'ORDER-001',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'signature_key' => 'invalid_signature',
            'transaction_status' => 'settlement',
        ];

        $payload = new WebhookPayload(provider: 'midtrans', raw_body: json_encode($data), headers: [], json: $data);

        $this->client->method('verifySignature')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Midtrans webhook signature');

        $this->gateway->handleWebhook($payload);
    }

    public function test_handle_webhook_with_missing_fields_throws(): void
    {
        $payload = new WebhookPayload(provider: 'midtrans', raw_body: '{}', headers: [], json: []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required fields');

        $this->gateway->handleWebhook($payload);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('midtransStatusProvider')]
    public function test_map_status(string $midtrans_status, PaymentStatus $expected): void
    {
        $this->client
            ->method('getTransactionStatus')
            ->willReturn([
                'order_id' => 'ORDER-001',
                'transaction_status' => $midtrans_status,
                'gross_amount' => '100000',
                'currency' => 'IDR',
            ]);

        $details = $this->gateway->getPayment('ORDER-001');
        $this->assertSame($expected, $details->status);
    }

    /**
     * @return array<string, array{string, PaymentStatus}>
     */
    public static function midtransStatusProvider(): array
    {
        return [
            'capture' => ['capture', PaymentStatus::PAID],
            'settlement' => ['settlement', PaymentStatus::PAID],
            'pending' => ['pending', PaymentStatus::PENDING],
            'authorize' => ['authorize', PaymentStatus::PENDING],
            'deny' => ['deny', PaymentStatus::FAILED],
            'cancel' => ['cancel', PaymentStatus::FAILED],
            'expire' => ['expire', PaymentStatus::EXPIRED],
            'refund' => ['refund', PaymentStatus::REFUNDED],
            'partial_refund' => ['partial_refund', PaymentStatus::REFUNDED],
        ];
    }
}
