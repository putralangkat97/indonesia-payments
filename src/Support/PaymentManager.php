<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Support;

use Anggit\IndonesiaPayments\Contracts\GatewayInterface;
use InvalidArgumentException;

final class PaymentManager
{
    /**
     * @param array<string,GatewayInterface> $gateways
     */
    public function __construct(
        private array $gateways,
        private string $default_gateway,
    ) {}

    public function default(): GatewayInterface
    {
        return $this->via($this->default_gateway);
    }

    public function via(string $name): GatewayInterface
    {
        if (!array_key_exists($name, $this->gateways)) {
            throw new InvalidArgumentException("Gateway [{$name}] is not registered.");
        }

        return $this->gateways[$name];
    }
}
