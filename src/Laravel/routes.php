<?php

declare(strict_types=1);

use Anggit\IndonesiaPayments\Laravel\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

$webhookPath = config("indopay.webhook.path", "/payments/webhook");

Route::post(
    $webhookPath . "/{gateway}",
    [WebhookController::class, "handle"],
)->name("indopay.webhook");
