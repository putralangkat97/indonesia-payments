<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Support\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class MidtransHttpClient
{
    private string $base_url;

    public function __construct(
        private ClientInterface $http,
        private string $server_key,
        bool $is_production = false,
    ) {
        $this->base_url = $is_production
            ? "https://app.midtrans.com"
            : "https://app.sandbox.midtrans.com";
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSnapTransaction(array $payload): array
    {
        return $this->request("POST", "/snap/v1/transactions", $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransactionStatus(string $order_id): array
    {
        return $this->request(
            "GET",
            "/v2/{$order_id}/status",
            base_url: $this->getApiBaseUrl(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createRefund(string $order_id, array $payload): array
    {
        return $this->request(
            "POST",
            "/v2/{$order_id}/refund",
            $payload,
            base_url: $this->getApiBaseUrl(),
        );
    }

    public function verifySignature(
        string $order_id,
        string $status_code,
        string $gross_amount,
        string $signature,
    ): bool {
        $input = $order_id . $status_code . $gross_amount . $this->server_key;
        $computed = hash("sha512", $input);

        return hash_equals($computed, $signature);
    }

    private function getApiBaseUrl(): string
    {
        return str_contains($this->base_url, "sandbox")
            ? "https://api.sandbox.midtrans.com"
            : "https://api.midtrans.com";
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $uri,
        ?array $json = null,
        ?string $base_url = null,
    ): array {
        $auth_header = base64_encode($this->server_key . ":");

        $options = [
            "headers" => [
                "Content-Type" => "application/json",
                "Accept" => "application/json",
                "Authorization" => "Basic {$auth_header}",
            ],
        ];

        if ($json !== null) {
            $options["json"] = $json;
        }

        $url = ($base_url ?? $this->base_url) . $uri;

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                "Midtrans API request failed: " . $e->getMessage(),
                0,
                $e,
            );
        }

        $body = (string) $response->getBody();

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, associative: true);

        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON response from Midtrans");
        }

        return $data;
    }
}
