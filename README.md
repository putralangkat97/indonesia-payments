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

This package provides seamless Laravel integration with auto-discovery, ServiceProvider, and `Payment` Facade.

### Requirements

- **Laravel** `^9.0 || ^10.0 || ^11.0 || ^12.0`
- **PHP** `^8.1`
- **Composer** v2

### Installation & Setup

#### 1. Install the Package

```bash
composer require anggit/indonesia-payments
```

The package uses Laravel's auto-discovery, so no manual provider registration is needed.

#### 2. Configure Environment Variables

Add these to your `.env` file:

```env
# Default gateway (xendit or midtrans)
INDOPAY_DEFAULT=xendit

# Xendit Configuration
XENDIT_SECRET_KEY=xnd_development_XXXXXXXXXXX
XENDIT_BASE_URL=null

# Midtrans Configuration
MIDTRANS_SERVER_KEY=SB-Mid-server-XXXXXXXXXXX
MIDTRANS_IS_PRODUCTION=false
```

> **Note:** Get your API keys from:
> - **Xendit**: https://dashboard.xendit.co/#/settings/developers#api-keys
> - **Midtrans**: https://dashboard.midtrans.com/settings/v2/configuration

#### 3. Publish Configuration (Optional)

To customize the configuration file:

```bash
php artisan vendor:publish --provider="Anggit\IndonesiaPayments\Laravel\IndoPayServiceProvider" --tag="indopay-config"
```

This creates `config/indopay.php` which you can modify as needed.

### Usage Examples

#### Creating a Payment Charge

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
        // Using the default gateway
        $charge = \Payment::charge(
            new ChargeRequest(
                order_id: 'INV-' . now()->timestamp,
                amount:   200_000,
                currency: 'IDR',
                method:   PaymentMethod::EWALLET,
                customer: [
                    'email' => auth()->user()->email,
                    'name'  => auth()->user()->name,
                    'phone' => auth()->user()->phone ?? null,
                ],
                meta: [
                    'description' => 'Order #12345',
                ],
                return_url: route('checkout.success'),
            )
        );

        // Or specify a gateway explicitly
        $charge = \Payment::via('midtrans')->charge(
            new ChargeRequest(
                order_id: 'INV-' . now()->timestamp,
                amount:   200_000,
                currency: 'IDR',
                method:   PaymentMethod::VIRTUAL_ACCOUNT,
                customer: [
                    'email' => auth()->user()->email,
                    'name'  => auth()->user()->name,
                ],
            )
        );

        if ($charge->redirect_url === null) {
            return redirect()->route('checkout.error')
                ->with('error', 'Failed to create payment');
        }

        // Store payment_id in session/database for tracking
        session(['payment_id' => $charge->payment_id]);

        return redirect()->away($charge->redirect_url);
    }
}
```

#### Checking Payment Status

```php
use Anggit\IndonesiaPayments\Enums\PaymentStatus;

public function checkStatus(string $paymentId)
{
    $details = \Payment::getPayment($paymentId);
    
    return response()->json([
        'status' => $details->status->value,
        'method' => $details->method?->value,
        'amount' => $details->amount,
        'paid_at' => $details->paid_at,
    ]);
}
```

#### Processing a Refund

```php
use Anggit\IndonesiaPayments\DTO\RefundRequest;

public function refund(string $paymentId, float $amount)
{
    $refund = \Payment::via('xendit')->refund(
        new RefundRequest(
            payment_id: $paymentId,
            amount:     $amount,
            reason:     'Customer requested refund',
        )
    );
    
    if ($refund->status === PaymentStatus::REFUNDED) {
        // Update order status in database
        Order::where('payment_id', $paymentId)
            ->update(['status' => 'refunded']);
    }
    
    return response()->json([
        'success' => true,
        'refund_status' => $refund->status->value,
    ]);
}
```

#### Using Dependency Injection

You can also inject the `PaymentManager` instead of using the Facade:

```php
use Anggit\IndonesiaPayments\Support\PaymentManager;

class PaymentService
{
    public function __construct(
        private PaymentManager $manager
    ) {}

    public function createCharge(ChargeRequest $request)
    {
        return $this->manager->via('xendit')->charge($request);
    }
}
```

### Webhook Handling

#### Built-in Webhook Route

The package automatically registers:

```
POST /payments/webhook/{gateway}
```

Examples:
- `POST https://yourdomain.com/payments/webhook/xendit`
- `POST https://yourdomain.com/payments/webhook/midtrans`

Register these URLs in your payment gateway dashboard:
- **Xendit**: Dashboard → Settings → Webhooks
- **Midtrans**: Dashboard → Settings → Configuration → Webhooks

#### Custom Webhook Handler

For more control over webhook processing:

```php
<?php

namespace App\Http\Controllers;

use Anggit\IndonesiaPayments\DTO\WebhookPayload;
use Anggit\IndonesiaPayments\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function xendit(Request $request): Response
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

        // Process payment status update
        $this->handlePaymentStatus($result->payment_id, $result->status);

        return response()->json(['ok' => true]);
    }

    public function midtrans(Request $request): Response
    {
        $payload = WebhookPayload::fromHttp(
            provider: 'midtrans',
            headers:  $request->headers->all(),
            raw_body: $request->getContent(),
            query:    $request->query->all(),
        );

        $result = \Payment::via('midtrans')->handleWebhook($payload);

        $this->handlePaymentStatus($result->payment_id, $result->status);

        return response()->json(['ok' => true]);
    }

    private function handlePaymentStatus(string $paymentId, PaymentStatus $status): void
    {
        $order = Order::where('payment_id', $paymentId)->first();

        if (!$order) {
            Log::warning("Order not found for payment: {$paymentId}");
            return;
        }

        match ($status) {
            PaymentStatus::PAID    => $order->markAsPaid(),
            PaymentStatus::EXPIRED => $order->markAsExpired(),
            PaymentStatus::FAILED  => $order->markAsFailed(),
            PaymentStatus::REFUNDED => $order->markAsRefunded(),
            default                => null,
        };

        Log::info("Payment {$paymentId} status updated: {$status->value}");
    }
}
```

Register routes in `routes/api.php`:

```php
use App\Http\Controllers\PaymentWebhookController;

Route::post('/webhook/xendit', [PaymentWebhookController::class, 'xendit'])
    ->name('webhook.xendit');
    
Route::post('/webhook/midtrans', [PaymentWebhookController::class, 'midtrans'])
    ->name('webhook.midtrans');
```

### Supported Features by Gateway

| Feature | Xendit | Midtrans |
|---------|--------|----------|
| Create Charge | ✅ | ✅ |
| Check Status | ✅ | ✅ |
| Refund | ✅ | ✅ |
| Webhook Handler | ✅ | ✅ |
| Signature Verification | ✅ | ✅ |
| Payment Link | ✅ | ✅ (Snap) |
| Virtual Account | ✅ | ✅ |
| E-Wallet | ✅ | ✅ |
| QRIS | ✅ | ✅ |
| Credit Card | ✅ | ✅ |

### Troubleshooting

**Issue: "Invalid signature" in webhooks**
- Ensure your secret/server key is correct
- Check that webhook URL is publicly accessible (use ngrok for local development)
- Verify the webhook is registered in the payment gateway dashboard

**Issue: "Gateway not found" error**
- Check `INDOPAY_DEFAULT` in `.env`
- Ensure gateway configuration is present in `config/indopay.php`

**Issue: Redirect URL is null**
- Verify payment method is supported by the gateway
- Check API credentials are valid
- Review gateway API response for errors

### Testing in Laravel

Create a test command for quick verification:

```bash
php artisan make:command TestPaymentGateway
```

```php
<?php

namespace App\Console\Commands;

use Anggit\IndonesiaPayments\DTO\ChargeRequest;
use Anggit\IndonesiaPayments\Enums\PaymentMethod;
use Illuminate\Console\Command;

class TestPaymentGateway extends Command
{
    protected $signature = 'payment:test {gateway=xendit}';
    protected $description = 'Test payment gateway connection';

    public function handle()
    {
        $gateway = $this->argument('gateway');
        
        $this->info("Testing {$gateway} gateway...");
        
        try {
            $charge = \Payment::via($gateway)->charge(
                new ChargeRequest(
                    order_id: 'TEST-' . time(),
                    amount:   10000,
                    currency: 'IDR',
                    method:   PaymentMethod::EWALLET,
                    customer: [
                        'email' => 'test@example.com',
                        'name'  => 'Test User',
                    ],
                )
            );
            
            $this->info("✅ Success! Payment ID: {$charge->payment_id}");
            $this->line("Redirect URL: {$charge->redirect_url}");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed: {$e->getMessage()}");
        }
    }
}
```

Run with:

```bash
php artisan payment:test xendit
php artisan payment:test midtrans
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
  Gateway/            # XenditGatewayTest, MidtransGatewayTest
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

- [x] Midtrans driver.
- [x] Xendit driver.
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
