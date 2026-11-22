<?php

use App\Billing\Gateways\Gateways;
use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Connections\WebhookController;
use App\Http\Controllers\Webhooks\MuxWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Payments (B5.1, B5.2a): Stripe, PayPal and Authorize.net, each signed its own way. Before the
// connections route so the gateway never reaches a tool's driver.
Route::post('webhooks/{gateway}', PaymentWebhookController::class)
    ->whereIn('gateway', Gateways::CHECKOUT)
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');

// Inbound webhooks from connected tools (B3.1, ADR 0013). No session or CSRF:
// each tool's own signature check (in its driver) protects the endpoint.
Route::post('webhooks/{tool}', WebhookController::class)
    ->whereIn('tool', ConnectorRegistry::ids())
    ->middleware('throttle:webhooks')
    ->name('webhooks.receive');

// Mux Video (A5.3): lesson videos and captions ready. Signed with the webhook secret.
Route::post('webhooks/mux', MuxWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.mux');
