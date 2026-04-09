<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\DTO;

/**
 * Representasi generik payload webhook.
 */
final class WebhookPayload
{
    /**
     * @param string $provider
     * @param string $raw_body
     * @param array<string,string[]> $headers
     * @param array<string,mixed> $json
     * @param array<string,mixed> $query
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $raw_body,
        public readonly array $headers,
        public readonly array $json,
        public readonly array $query = [],
    ) {}

    /**
     * Create a WebhookPayload from HTTP data.
     *
     * @param string $provider
     * @param array<string,string[]> $headers
     * @param string $raw_body
     * @param array<string,mixed> $query
     */
    public static function fromHttp(string $provider, array $headers, string $raw_body, array $query = []): self
    {
        /** @var array<string,mixed>|null $json */
        /** @var array<string,mixed> $json */
        $json = json_decode($raw_body, associative: true) ?? [];

        return new self(provider: $provider, raw_body: $raw_body, headers: $headers, json: $json, query: $query);
    }
}
