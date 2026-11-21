<?php

use App\Http\Controllers\Api\ActiveRoleController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\Auth\ExchangeSignInController;
use App\Http\Controllers\Api\Auth\IdentitySignInController;
use App\Http\Controllers\Api\Auth\PhoneSignInController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Onboarding\FreeQuizController;
use App\Http\Controllers\Api\Onboarding\OnboardingController;
use App\Http\Controllers\Api\Onboarding\StateController;
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
    Route::post('exchange', ExchangeSignInController::class)
        ->middleware('throttle:sign-in')
        ->name('exchange');
    Route::post('phone/start', [PhoneSignInController::class, 'start'])
        ->middleware('throttle:phone-code')
        ->name('phone.start');
    Route::post('phone/verify', [PhoneSignInController::class, 'verify'])
        ->middleware('throttle:sign-in')
        ->name('phone.verify');
});

// Getting started (A3.3): public, before an account exists.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('states', [StateController::class, 'index'])->name('api.states');
    Route::get('states/{state}/free-quiz', [FreeQuizController::class, 'show'])->name('api.states.free-quiz');
});
Route::post('states/{state}/waitlist', [StateController::class, 'waitlist'])
    ->middleware('throttle:10,1')
    ->name('api.states.waitlist');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.me');
    Route::put('me/onboarding', [OnboardingController::class, 'update'])->middleware('throttle:30,1')->name('api.me.onboarding');
    Route::post('me/quiz-attempts', [FreeQuizController::class, 'store'])->middleware('throttle:30,1')->name('api.me.quiz-attempts');
    Route::delete('me', [MeController::class, 'destroy'])->middleware('throttle:sign-in')->name('api.me.destroy');
    Route::put('me/active-role', ActiveRoleController::class)->middleware('throttle:30,1')->name('api.me.active-role');
    Route::get('me/devices', [MeController::class, 'devices'])->name('api.me.devices');
    Route::delete('me/devices/{device}', [MeController::class, 'revokeDevice'])->whereNumber('device')->name('api.me.devices.destroy');
    Route::post('auth/sign-out', [MeController::class, 'signOut'])->name('api.auth.sign-out');
});
