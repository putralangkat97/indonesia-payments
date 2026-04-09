<?php

declare(strict_types=1);

use Anggit\IndonesiaPayments\Laravel\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

$webhook_path = config('indopay.webhook.path', '/payments/webhook');

Route::post($webhook_path . '/{gateway}', [WebhookController::class, 'handle'])->name('indopay.webhook');
