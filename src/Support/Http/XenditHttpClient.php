<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Support\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class XenditHttpClient
{
    private string $base_url;

    public function __construct(
        private ClientInterface $http,
        private string $secret_key,
        ?string $base_url = null,
    ) {
        $this->base_url = rtrim($base_url ?? 'https://api.xendit.co', '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInvoice(array $payload): array
    {
        return $this->request('POST', '/v2/invoices', $payload);
    }

    /**
     * @param string $invoice_id
     * @return array<string, mixed>
     */
    public function getInvoice(string $invoice_id): array
    {
        return $this->request('GET', "/v2/invoices/{$invoice_id}");
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createRefund(array $payload): array
    {
        return $this->request('POST', '/refunds', $payload);
    }

    public function verifyCallbackSignature(string $raw_body, string $header_signature): bool
    {
        $computed = hash_hmac('sha256', $raw_body, $this->secret_key);

        return hash_equals(strtolower($computed), strtolower($header_signature));
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, ?array $json = null): array
    {
        $options = [
            'auth' => [$this->secret_key, ''],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if ($json !== null) {
            $options['json'] = $json;
        }

        try {
            $response = $this->http->request($method, $this->base_url . $uri, $options);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Xendit API request failed: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();

        /** @var array<string, mixed>|null $data */
        $data = json_decode($body, associative: true);

        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response from Xendit');
        }

        return $data;
    }
}
