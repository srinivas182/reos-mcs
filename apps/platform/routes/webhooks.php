<?php

use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Connections\WebhookController;
use App\Http\Controllers\Webhooks\MuxWebhookController;
use Illuminate\Support\Facades\Route;

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
