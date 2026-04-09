<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Laravel\Http\Controllers;

use Anggit\IndonesiaPayments\DTO\WebhookPayload;
use Anggit\IndonesiaPayments\Support\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController
{
    public function __construct(private PaymentManager $manager) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? $values : [$values];
        }

        $payload = WebhookPayload::fromHttp(
            provider: $gateway,
            headers: $headers,
            raw_body: $request->getContent(),
            query: $request->query->all(),
        );

        $result = $this->manager->via($gateway)->handleWebhook($payload);

        return new JsonResponse([
            "success" => true,
            "gateway" => $result->gateway_name,
            "payment_id" => $result->payment_id,
            "status" => $result->status->value,
        ]);
    }
}
