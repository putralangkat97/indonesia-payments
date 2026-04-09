# Indonesia Payments

Unified PHP SDK for **Indonesian payment gateways**, with initial focus on **Xendit** and **Midtrans**, and seamless **Laravel** integration.

This library provides a consistent abstraction for:

- Creating payment charges/invoices.
- Checking payment status.
- Processing refunds.
- Handling webhook callbacks.
- (Future) Adding other drivers like DOKU, Duitku, etc.

The current implementations are **Xendit** and **Midtrans** via direct HTTP clients to their REST APIs (without depending on official SDKs). This keeps the package more stable and easier to follow PHP and framework updates.

---

## Features

- Core **framework-agnostic** (pure PHP, PSR-4).
- Consistent `GatewayInterface` abstraction for all providers.
- `XenditGateway` implementation:
  - Create invoice (Payment Link API).
  - Get invoice status.
  - Create refunds.
  - Webhook handler + signature verification (`X-Callback-Signature`).
- `MidtransGateway` implementation:
  - Create Snap transaction.
  - Get transaction status.
  - Create refunds.
  - Webhook handler + SHA-512 signature verification.
- Clean DTOs & Enums:
  - `ChargeRequest`, `ChargeResponse`, `PaymentDetails`, `RefundRequest`, `RefundResponse`, `WebhookPayload`, `WebhookResult`.
  - `PaymentMethod` (`VIRTUAL_ACCOUNT`, `EWALLET`, `CARD`, `QRIS`).
  - `PaymentStatus` (`PENDING`, `PAID`, `FAILED`, `EXPIRED`, `REFUNDED`).
- `PaymentManager` + `GatewayFactory` for gateway selection.
- Laravel integration (ServiceProvider + `Payment` Facade).
- **Mago** linter/formatter configured out of the box.

---

## Installation

```bash
composer require anggit/indonesia-payments
```

Requirements:

- PHP `^8.1` (8.1, 8.2, 8.3, 8.4, 8.5).
- Composer v2.

This package uses `guzzlehttp/guzzle` for HTTP communication with the payment gateway APIs.

---

## Architecture

Overview of the core components:

- `GatewayInterface` -- contract for all payment gateways:
  - `charge()`, `getPayment()`, `refund()`, `handleWebhook()`.
- `XenditGateway` -- Xendit implementation.
- `MidtransGateway` -- Midtrans Snap implementation.
- `PaymentManager` -- select gateway (`default()` / `via('xendit')`).
- `GatewayFactory` -- build gateway instances from configuration.
- `WebhookPayload` & `WebhookResult` -- normalized webhook data.

Main namespace structure:

- `Anggit\IndonesiaPayments\Contracts`
- `Anggit\IndonesiaPayments\DTO`
- `Anggit\IndonesiaPayments\Enums`
- `Anggit\IndonesiaPayments\Gateways`
- `Anggit\IndonesiaPayments\Support`
- `Anggit\IndonesiaPayments\Laravel\...`

---

## Configuration

### Xendit

You need a **Secret Key** from the Xendit dashboard (Development or Production).

### Midtrans

You need a **Server Key** from the Midtrans dashboard (Sandbox or Production).

### Pure PHP Configuration

```php
$config = [
    'default' => 'xendit',
    'gateways' => [
        'xendit' => [
            'secret_key' => 'xnd_development_XXXXXXXXXXX',
            'base_url'   => null, // null = https://api.xendit.co
        ],
        'midtrans' => [
            'server_key'    => 'SB-Mid-server-XXXXXXXXXXX',
            'is_production' => false,
        ],
    ],
];
```

### Laravel Configuration (`config/indopay.php`)

```php
return [
    'default' => env('INDOPAY_DEFAULT', 'xendit'),

    'gateways' => [
        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'base_url'   => env('XENDIT_BASE_URL'),
        ],

        'midtrans' => [
            'server_key'    => env('MIDTRANS_SERVER_KEY'),
            'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        ],
    ],
];
```

In your `.env`:

```env
XENDIT_SECRET_KEY=xnd_development_XXXXXXXXXXX
MIDTRANS_SERVER_KEY=SB-Mid-server-XXXXXXXXXXX
INDOPAY_DEFAULT=xendit
```

---

## Usage -- Pure PHP

Minimal example using `PaymentManager` and `GatewayFactory` directly.

```php
<?php

use Anggit\IndonesiaPayments\Support\GatewayFactory;
use Anggit\IndonesiaPayments\Support\PaymentManager;
use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\Enums\PaymentMethod;

require __DIR__ . '/vendor/autoload.php';

$config = [
    'default' => 'xendit',
    'gateways' => [
        'xendit' => [
            'secret_key' => 'xnd_development_XXXXXXXXXXX',
            'base_url'   => null,
        ],
    ],
];

$factory  = new GatewayFactory();
$gateways = $factory->makeAll($config['gateways']);
$manager  = new PaymentManager($gateways, $config['default']);

$charge = $manager->via('xendit')->charge(
    new ChargeRequest(
        order_id: 'INV-' . time(),
        amount:   200_000,
        currency: 'IDR',
        method:   PaymentMethod::EWALLET,
        customer: [
            'email' => 'user@example.com',
            'name'  => 'Example User',
        ],
    )
);

echo 'Invoice ID: ' . $charge->payment_id . PHP_EOL;
echo 'Redirect URL: ' . $charge->redirect_url . PHP_EOL;
echo 'Status: ' . $charge->status->value . PHP_EOL;
```

After this, redirect the user to `$charge->redirect_url`.

---

## Usage -- Laravel

This package defines a ServiceProvider and Facade for Laravel:

- The provider auto-registers via `extra.laravel.providers`.
- The `Payment` Facade alias is available.

### 1. Install in Laravel

```bash
composer require anggit/indonesia-payments
```

Publish config (optional):

```bash
php artisan vendor:publish --provider="Anggit\IndonesiaPayments\Laravel\IndoPayServiceProvider" --tag="indopay-config"
```

### 2. Create a charge from a controller

```php
<?php

namespace App\Http\Controllers;

use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\Enums\PaymentMethod;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function create(): RedirectResponse
    {
        $charge = \Payment::via('xendit')->charge(
            new ChargeRequest(
                order_id: 'INV-' . now()->timestamp,
                amount:   200_000,
                currency: 'IDR',
                method:   PaymentMethod::EWALLET,
                customer: [
                    'email' => auth()->user()->email,
                    'name'  => auth()->user()->name,
                ],
                meta: [
                    'description' => 'Example order',
                ],
                return_url: route('checkout.success'),
            )
        );

        if ($charge->redirect_url === null) {
            return redirect()->route('checkout.error');
        }

        return redirect()->away($charge->redirect_url);
    }
}
```

---

## Handling Webhooks in Laravel

### Built-in Webhook Route

The package registers a webhook route automatically:

```
POST /payments/webhook/{gateway}
```

For example, `POST /payments/webhook/xendit` or `POST /payments/webhook/midtrans`.

Register this URL in your Xendit/Midtrans dashboard.

### Custom Webhook Controller

If you need more control, create your own controller:

```php
// routes/api.php
use App\Http\Controllers\XenditWebhookController;

Route::post('/webhook/xendit', XenditWebhookController::class)
    ->name('webhook.xendit');
```

```php
<?php

namespace App\Http\Controllers;

use Anggit\IndonesiaPayments\DTO\WebhookPayload;
use Anggit\IndonesiaPayments\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XenditWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? $values : [$values];
        }

        $payload = WebhookPayload::fromHttp(
            provider: 'xendit',
            headers:  $headers,
            raw_body: $request->getContent(),
            query:    $request->query->all(),
        );

        $result = \Payment::via('xendit')->handleWebhook($payload);

        $order = \App\Models\Order::where('external_id', $result->payment_id)->first();

        if ($order) {
            match ($result->status) {
                PaymentStatus::PAID    => $order->markAsPaid(),
                PaymentStatus::EXPIRED => $order->markAsExpired(),
                PaymentStatus::FAILED  => $order->markAsFailed(),
                default                => null,
            };
        }

        // Xendit only needs HTTP 200 OK as acknowledgement.
        return response()->json(['ok' => true]);
    }
}
```

---

## Payment Status & Refunds

### Get Payment Status

```php
$details = $manager->via('xendit')->getPayment('inv_123');
// Returns PaymentDetails with normalized status (PENDING/PAID/EXPIRED/FAILED)
```

### Create a Refund

```php
use Anggit\IndonesiaPayments\DTO\RefundRequest;

$refund = $manager->via('xendit')->refund(
    new RefundRequest(
        payment_id: 'inv_123',
        amount:     50_000,
        reason:     'Customer request',
    )
);

echo $refund->status->value; // "refunded", "pending", or "failed"
```

Both Xendit and Midtrans gateways support refunds.

---

## Development

### Requirements

- PHP >= 8.1
- Composer v2
- Xendit account (for testing Xendit driver)
- Midtrans account (for testing Midtrans driver)
- PHPUnit (dev dependency set to `^10 || ^11`)

### Getting Started

Clone the repo:

```bash
git clone https://github.com/putralangkat97/indonesia-payments.git
cd indonesia-payments
composer install
```

### Available Scripts

| Command | Description |
|---|---|
| `composer test` | Run PHPUnit tests |
| `composer lint` | Lint with Mago |
| `composer format` | Auto-format with Mago |
| `composer analyze` | Static analysis with Mago |
| `composer ci` | Full pipeline: format + lint + test |

Run tests:

```bash
composer test
```

Or directly:

```bash
./vendor/bin/phpunit
```

### Directory Structure

```text
src/
  Contracts/          # GatewayInterface
  DTO/                # ChargeRequest, ChargeResponse, PaymentDetails,
                      # RefundRequest, RefundResponse, WebhookPayload, WebhookResult
  Enums/              # PaymentMethod, PaymentStatus
  Exceptions/         # GatewayException, NotSupportedException
  Gateways/           # XenditGateway, MidtransGateway
  Support/
    Http/             # XenditHttpClient, MidtransHttpClient
    GatewayFactory.php
    PaymentManager.php
  Laravel/
    config/           # indopay.php
    Facades/          # Payment.php
    Http/Controllers/ # WebhookController.php
    IndoPayServiceProvider.php
    routes.php
tests/
  gateway/            # XenditGatewayTest, MidtransGatewayTest
```

### Code Conventions

- Strict types: `declare(strict_types=1);` in every file.
- `final readonly` for DTOs.
- Enums for payment status/method instead of magic strings.
- **snake_case** for all variables.
- PascalCase namespaces following PSR-4.
- Mago formatter/linter enforced via `composer ci`.

### Manual Playground

Create a `playground.php` file at the project root:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Anggit\IndonesiaPayments\Support\GatewayFactory;
use Anggit\IndonesiaPayments\Support\PaymentManager;
use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\Enums\PaymentMethod;

$config = [
    'default' => 'xendit',
    'gateways' => [
        'xendit' => [
            'secret_key' => 'xnd_development_XXXXXXXXXXX',
            'base_url'   => null,
        ],
    ],
];

$factory  = new GatewayFactory();
$gateways = $factory->makeAll($config['gateways']);
$manager  = new PaymentManager($gateways, $config['default']);

$charge = $manager->via('xendit')->charge(
    new ChargeRequest(
        order_id: 'INV-' . time(),
        amount:   100_000,
        currency: 'IDR',
        method:   PaymentMethod::EWALLET,
        customer: [
            'email' => 'user@example.com',
            'name'  => 'Example User',
        ],
    )
);

var_dump($charge);
```

Then run:

```bash
php playground.php
```

---

## Adding a New Driver

The pattern for adding a new driver (e.g. **DOKU**) is:

1. Add an **HTTP client** in `src/Support/Http/DokuHttpClient.php`
2. Add a **Gateway** in `src/Gateways/DokuGateway.php` implementing `GatewayInterface`
3. Wire it in `GatewayFactory::make()` with a new match case
4. Add configuration in `src/Laravel/config/indopay.php`
5. Add tests in `tests/gateway/DokuGatewayTest.php`

See the existing `XenditGateway` and `MidtransGateway` implementations as reference.

---

## Roadmap

- [ ] DOKU driver.
- [ ] Duitku driver.
- [ ] More specific payment method support: VA, e-wallet, QRIS per provider.
- [ ] Comprehensive error handling & logging documentation.

---

## Contributing

Issues, PRs, and feedback are welcome.

---

## License

MIT. Free to use in personal and commercial projects.
