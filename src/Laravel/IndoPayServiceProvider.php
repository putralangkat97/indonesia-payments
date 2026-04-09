<?php

declare(strict_types=1);

namespace Anggit\IndonesiaPayments\Laravel;

use Anggit\IndonesiaPayments\Support\GatewayFactory;
use Anggit\IndonesiaPayments\Support\PaymentManager;
use Illuminate\Support\ServiceProvider;

class IndoPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/indopay.php', 'indopay');

        $this->app->singleton(PaymentManager::class, function ($app) {
            /** @var array<string,mixed> $config */
            $config = $app['config']['indopay'];

            $factory = new GatewayFactory();

            /** @var array<string,array<string,mixed>> $gateways_config */
            $gateways_config = $config['gateways'] ?? [];

            $gateways = $factory->makeAll($gateways_config);
            $default = $config['default'] ?? 'xendit';

            return new PaymentManager($gateways, $default);
        });

        $this->app->alias(PaymentManager::class, 'indopay');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/indopay.php' => config_path('indopay.php'),
            ], 'indopay-config');
        }

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
