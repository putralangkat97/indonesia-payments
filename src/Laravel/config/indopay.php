<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Default Gateway
     |--------------------------------------------------------------------------
     |
     | The default payment gateway to use when none is specified.
     |
     */
    'default' => env('INDOPAY_DEFAULT', 'xendit'),

    /*
     |--------------------------------------------------------------------------
     | Payment Gateways
     |--------------------------------------------------------------------------
     |
     | Configure each payment gateway with its credentials and options.
     |
     */
    'gateways' => [
        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY', ''),
            'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
            'base_url' => env('XENDIT_BASE_URL'),
        ],

        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY', ''),
            'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Webhook
     |--------------------------------------------------------------------------
     |
     | Webhook route configuration.
     |
     */
    'webhook' => [
        'path' => env('INDOPAY_WEBHOOK_PATH', '/payments/webhook'),
    ],
];
