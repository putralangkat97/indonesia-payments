<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Laravel\Facades;

use Anggit\IndonesiaPayments\Contracts\GatewayInterface;
use Anggit\IndonesiaPayments\Support\PaymentManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static GatewayInterface default()
 * @method static GatewayInterface via(string $name)
 *
 * @see PaymentManager
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentManager::class;
    }
}
