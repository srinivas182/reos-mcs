<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
| REOS API v1. Every route here is served under /api/v1 and is used by the
| web app, the mobile app and partners alike (see docs/adr/0003-api-contracts.md).
*/

Route::get('health', HealthController::class)->name('api.health');

Route::get('app-config', AppConfigController::class)
    ->middleware('throttle:60,1')
    ->name('api.app-config');
