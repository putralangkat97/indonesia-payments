<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Support;

use Anggit\IndonesiaPayments\Contracts\GatewayInterface;
use Anggit\IndonesiaPayments\Gateways\MidtransGateway;
use Anggit\IndonesiaPayments\Gateways\XenditGateway;
use Anggit\IndonesiaPayments\Support\Http\MidtransHttpClient;
use Anggit\IndonesiaPayments\Support\Http\XenditHttpClient;
use GuzzleHttp\Client as GuzzleClient;
use InvalidArgumentException;

final class GatewayFactory
{
    /**
     * @param array<string,mixed> $config
     */
    public function make(string $name, array $config): GatewayInterface
    {
        return match ($name) {
            'xendit' => $this->makeXendit($config),
            'midtrans' => $this->makeMidtrans($config),
            default => throw new InvalidArgumentException("Gateway [{$name}] is not valid."),
        };
    }

    /**
     * @param array<string,array<string,mixed>> $gateways_config
     * @return array<string,GatewayInterface>
     */
    public function makeAll(array $gateways_config): array
    {
        $instances = [];

        foreach ($gateways_config as $name => $config) {
            $instances[$name] = $this->make($name, $config);
        }

        return $instances;
    }

    /**
     * @param array<string,mixed> $config
     */
    private function makeXendit(array $config): GatewayInterface
    {
        /** @var string|null $secret_key */
        $secret_key = $config['secret_key'] ?? null;

        if (!is_string($secret_key) || $secret_key === '') {
            throw new InvalidArgumentException('Xendit secret_key is required.');
        }

        $http = new GuzzleClient();
        /** @var string|null $base_url */
        $base_url = $config['base_url'] ?? null;
        /** @var string|null $webhook_token */
        $webhook_token = $config['webhook_token'] ?? null;
        $client = new XenditHttpClient(
            http: $http,
            secret_key: $secret_key,
            base_url: $base_url,
            webhook_token: $webhook_token,
        );

        return new XenditGateway($client);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function makeMidtrans(array $config): GatewayInterface
    {
        /** @var string|null $server_key */
        $server_key = $config['server_key'] ?? null;

        if (!is_string($server_key) || $server_key === '') {
            throw new InvalidArgumentException('Midtrans server_key is required.');
        }

        $http = new GuzzleClient();
        $client = new MidtransHttpClient(
            http: $http,
            server_key: $server_key,
            is_production: (bool) ($config['is_production'] ?? false),
        );

        return new MidtransGateway($client);
    }
}
