<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\Auth\IdentitySignInController;
use App\Http\Controllers\Api\Auth\PhoneSignInController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

/*
| REOS API v1. Every route here is served under /api/v1 and is used by the
| web app, the mobile app and partners alike (see docs/adr/0003-api-contracts.md).
*/

Route::get('health', HealthController::class)->name('api.health');

Route::get('app-config', AppConfigController::class)
    ->middleware('throttle:60,1')
    ->name('api.app-config');

// Accounts (Sprint 3, A3.1). Sign-in is rate limited per network; phone codes
// also per number (see AppServiceProvider::configureRateLimits).
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('{provider}', IdentitySignInController::class)
        ->whereIn('provider', ['google', 'apple'])
        ->middleware('throttle:sign-in')
        ->name('identity');
    Route::post('phone/start', [PhoneSignInController::class, 'start'])
        ->middleware('throttle:phone-code')
        ->name('phone.start');
    Route::post('phone/verify', [PhoneSignInController::class, 'verify'])
        ->middleware('throttle:sign-in')
        ->name('phone.verify');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.me');
    Route::delete('me', [MeController::class, 'destroy'])->middleware('throttle:sign-in')->name('api.me.destroy');
    Route::get('me/devices', [MeController::class, 'devices'])->name('api.me.devices');
    Route::delete('me/devices/{device}', [MeController::class, 'revokeDevice'])->whereNumber('device')->name('api.me.devices.destroy');
    Route::post('auth/sign-out', [MeController::class, 'signOut'])->name('api.auth.sign-out');
});
