<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Gateways;

use Anggit\IndonesiaPayments\Contracts\GatewayInterface;
use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\DTO\ChargeResponse;
use Anggit\IndonesiaPayments\DTO\PaymentDetails;
use Anggit\IndonesiaPayments\DTO\RefundRequest;
use Anggit\IndonesiaPayments\DTO\RefundResponse;
use Anggit\IndonesiaPayments\DTO\WebhookPayload;
use Anggit\IndonesiaPayments\DTO\WebhookResult;
use Anggit\IndonesiaPayments\Enums\PaymentStatus;
use Anggit\IndonesiaPayments\Support\Http\XenditHttpClient;
use InvalidArgumentException;

class XenditGateway implements GatewayInterface
{
    public function __construct(
        private XenditHttpClient $client,
    ) {}

    public function getName(): string
    {
        return 'xendit';
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $external_id = $request->order_id;

        $payload = [
            'external_id' => $external_id,
            'amount' => $request->amount,
            'description' => $request->meta['description'] ?? "Order {$external_id}",
            'payer_email' => $request->customer['email'] ?? null,
        ];

        if ($request->return_url) {
            $payload['success_return_url'] = $request->return_url;
        }

        if (array_key_exists('failure_redirect_url', $request->meta)) {
            $payload['failure_return_url'] = $request->meta['failure_redirect_url'];
        }

        $invoice = $this->client->createInvoice($payload);

        /** @var string|null $invoice_status */
        $invoice_status = $invoice['status'] ?? null;
        $status = $this->mapStatus($invoice_status);

        /** @var string $invoice_id */
        $invoice_id = $invoice['id'];

        /** @var string|null $invoice_url */
        $invoice_url = $invoice['invoice_url'] ?? null;

        return new ChargeResponse(
            gateway_name: $this->getName(),
            payment_id: $invoice_id,
            status: $status,
            redirect_url: $invoice_url,
            raw: $invoice,
        );
    }

    public function getPayment(string $payment_id): PaymentDetails
    {
        $invoice = $this->client->getInvoice($payment_id);

        /** @var string|null $invoice_status */
        $invoice_status = $invoice['status'] ?? null;
        $status = $this->mapStatus($invoice_status);

        /** @var string $invoice_id */
        $invoice_id = $invoice['id'];

        /** @var string $currency */
        $currency = $invoice['currency'] ?? 'IDR';

        return new PaymentDetails(
            gateway_name: $this->getName(),
            payment_id: $invoice_id,
            status: $status,
            amount: (int) ($invoice['amount'] ?? 0),
            currency: $currency,
            raw: $invoice,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $payload = [
            'invoice_id' => $request->payment_id,
            'amount' => $request->amount,
        ];

        if ($request->reason !== null) {
            $payload['reason'] = $request->reason;
        }

        $result = $this->client->createRefund($payload);

        $status = match ($result['status'] ?? null) {
            'SUCCEEDED' => PaymentStatus::REFUNDED,
            'FAILED' => PaymentStatus::FAILED,
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

        $signature = $headers['x-callback-signature'][0] ?? $headers['X-Signature'][0] ?? null;

        if (!is_string($signature) || !$this->client->verifyCallbackSignature($raw_body, $signature)) {
            throw new InvalidArgumentException('Invalid Xendit callback signature');
        }

        $data = $payload->json;

        /** @var string|null $data_status */
        $data_status = $data['status'] ?? null;
        $status = $this->mapStatus($data_status);

        /** @var string $payment_id */
        $payment_id = $data['id'] ?? $data['external_id'] ?? '';

        return new WebhookResult(gateway_name: $this->getName(), payment_id: $payment_id, status: $status, raw: $data);
    }

    private function mapStatus(?string $status): PaymentStatus
    {
        return match ($status) {
            'PAID' => PaymentStatus::PAID,
            'PENDING' => PaymentStatus::PENDING,
            'EXPIRED' => PaymentStatus::EXPIRED,
            'FAILED' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
