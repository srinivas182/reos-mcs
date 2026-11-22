<?php

use App\Billing\Gateways\Gateways;
use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Connections\WebhookController;
use App\Http\Controllers\Webhooks\ConnectWebhookController;
use App\Http\Controllers\Webhooks\LiveKitWebhookController;
use App\Http\Controllers\Webhooks\MuxWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\SesWebhookController;
use App\Http\Controllers\Webhooks\TwilioSmsWebhookController;
use Illuminate\Support\Facades\Route;

// Payments (B5.1, B5.2a): Stripe, PayPal and Authorize.net, each signed its own way. Before the
// connections route so the gateway never reaches a tool's driver.
Route::post('webhooks/{gateway}', PaymentWebhookController::class)
    ->whereIn('gateway', Gateways::CHECKOUT)
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');

// Payouts (B5.2b): Stripe Connect account changes, signed with the Connect endpoint's secret.
Route::post('webhooks/stripe-connect', ConnectWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.stripe-connect');

// Texts to REOS's number (B6.2c): STOP, START and HELP. Signed with the Twilio auth token.
Route::post('webhooks/twilio-sms', TwilioSmsWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.twilio-sms');

// Amazon SES bounces and complaints through SNS (B6.2d). Signed by SNS.
Route::post('webhooks/ses', SesWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.ses');

// LiveKit (B6.3c): who is in each room, and finished recordings. Signed with the API secret.
Route::post('webhooks/livekit', LiveKitWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.livekit');

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
