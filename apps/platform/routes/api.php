<?php

use App\Http\Controllers\Api\ActiveRoleController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\Auth\ExchangeSignInController;
use App\Http\Controllers\Api\Auth\IdentitySignInController;
use App\Http\Controllers\Api\Auth\PhoneSignInController;
use App\Http\Controllers\Api\Calendar\CalendarController;
use App\Http\Controllers\Api\Catalogue\CatalogueController;
use App\Http\Controllers\Api\Connections\ConnectionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Licences\LicenceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Onboarding\FreeQuizController;
use App\Http\Controllers\Api\Onboarding\OnboardingController;
use App\Http\Controllers\Api\Onboarding\StateController;
use App\Http\Controllers\Api\States\CurrentStateController;
use App\Http\Controllers\Api\Todos\TodoController;
use App\Http\Controllers\Api\Voice\VoiceController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
| REOS API v1. Every route here is served under /api/v1 and is used by the
| web app, the mobile app and partners alike (see docs/adr/0003-api-contracts.md).
*/

// Reads no tenant data and must answer even when the database is struggling.
Route::get('health', HealthController::class)->withoutMiddleware(ResolveTenant::class)->name('api.health');

Route::get('app-config', AppConfigController::class)
    ->middleware('throttle:60,1,api-app-config')
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
Route::middleware('throttle:60,1,api-getting-started')->group(function () {
    Route::get('states', [StateController::class, 'index'])->name('api.states');
    Route::get('states/{state}/free-quiz', [FreeQuizController::class, 'show'])->name('api.states.free-quiz');
});
Route::post('states/{state}/waitlist', [StateController::class, 'waitlist'])
    ->middleware('throttle:10,1,api-states-waitlist')
    ->name('api.states.waitlist');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.me');
    Route::put('me/onboarding', [OnboardingController::class, 'update'])->middleware('throttle:30,1,api-me-onboarding')->name('api.me.onboarding');
    Route::post('me/quiz-attempts', [FreeQuizController::class, 'store'])->middleware('throttle:30,1,api-me-quiz-attempts')->name('api.me.quiz-attempts');
    Route::delete('me', [MeController::class, 'destroy'])->middleware('throttle:sign-in')->name('api.me.destroy');
    Route::put('me/active-role', ActiveRoleController::class)->middleware('throttle:30,1,api-me-active-role')->name('api.me.active-role');
    Route::get('me/devices', [MeController::class, 'devices'])->name('api.me.devices');
    Route::delete('me/devices/{device}', [MeController::class, 'revokeDevice'])->whereNumber('device')->name('api.me.devices.destroy');
    Route::post('auth/sign-out', [MeController::class, 'signOut'])->name('api.auth.sign-out');

    // My licences and the state switcher (A5.1).
    Route::prefix('me/licences')->name('api.me.licences.')->controller(LicenceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:10,1,api-licences-store')->name('store');
        Route::post('check', 'check')->middleware('throttle:20,1,api-licences-check')->name('check');
        Route::patch('{licence}', 'update')->whereNumber('licence')->middleware('throttle:30,1,api-licences-update')->name('update');
        Route::delete('{licence}', 'destroy')->whereNumber('licence')->middleware('throttle:30,1,api-licences-destroy')->name('destroy');
        Route::post('{licence}/restore', 'restore')->whereNumber('licence')->middleware('throttle:30,1,api-licences-restore')->name('restore');
    });
    Route::get('me/states', [CurrentStateController::class, 'show'])->name('api.me.states');
    Route::put('me/states/current', [CurrentStateController::class, 'update'])->middleware('throttle:30,1,api-me-states')->name('api.me.states.update');

    // The Connections library (B3.2, ADR 0014).
    Route::get('catalogue', [CatalogueController::class, 'index'])->name('api.catalogue.index');
    Route::post('catalogue/{tool}/interest', [CatalogueController::class, 'interest'])->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,api-catalogue-interest')->name('api.catalogue.interest');
    Route::delete('catalogue/{tool}/interest', [CatalogueController::class, 'forget'])->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,api-catalogue-forget')->name('api.catalogue.forget');
    Route::post('tool-requests', [CatalogueController::class, 'request'])->middleware('throttle:10,60,api-tool-requests-store')->name('api.tool-requests.store');

    // Connections (B3.1, ADR 0013).
    Route::get('connections', [ConnectionController::class, 'index'])->name('api.connections.index');
    Route::post('connections', [ConnectionController::class, 'store'])->middleware('throttle:sign-in')->name('api.connections.store');
    Route::post('connections/{connection}/sync', [ConnectionController::class, 'sync'])->whereNumber('connection')->middleware('throttle:10,1,api-connections-sync')->name('api.connections.sync');
    Route::patch('connections/{connection}', [ConnectionController::class, 'update'])->whereNumber('connection')->middleware('throttle:30,1,api-connections-update')->name('api.connections.update');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber('connection')->middleware('throttle:30,1,api-connections-destroy')->name('api.connections.destroy');

    // To-dos and voice capture (B4.3, ADR 0018).
    Route::prefix('todos')->name('api.todos.')->controller(TodoController::class)->group(function () {
        Route::get('/', 'index')->middleware('throttle:120,1,api-todos-index')->name('index');
        Route::post('/', 'store')->middleware('throttle:60,1,api-todos-store')->name('store');
        Route::patch('{todo}', 'update')->whereNumber('todo')->middleware('throttle:60,1,api-todos-update')->name('update');
        Route::post('{todo}/done', 'done')->whereNumber('todo')->middleware('throttle:60,1,api-todos-done')->name('done');
        Route::delete('{todo}/done', 'undone')->whereNumber('todo')->middleware('throttle:60,1,api-todos-undone')->name('undone');
        Route::delete('{todo}', 'destroy')->whereNumber('todo')->middleware('throttle:60,1,api-todos-destroy')->name('destroy');
        Route::post('{todo}/restore', 'restore')->whereNumber('todo')->middleware('throttle:60,1,api-todos-restore')->name('restore');
    });
    Route::post('voice/parse', [VoiceController::class, 'parse'])->middleware('throttle:30,1,api-voice-parse')->name('api.voice.parse');

    // Your REOS calendar, two-way with your calendar tools (B4.1, ADR 0017).
    Route::prefix('calendar')->name('api.calendar.')->controller(CalendarController::class)->group(function () {
        Route::get('events', 'index')->middleware('throttle:120,1,api-calendar-index')->name('index');
        Route::post('events', 'store')->middleware('throttle:60,1,api-calendar-store')->name('store');
        Route::put('events/{event}', 'update')->whereNumber('event')->middleware('throttle:60,1,api-calendar-update')->name('update');
        Route::delete('events/{event}', 'destroy')->whereNumber('event')->middleware('throttle:60,1,api-calendar-destroy')->name('destroy');
        Route::post('events/{event}/restore', 'restore')->whereNumber('event')->middleware('throttle:60,1,api-calendar-restore')->name('restore');
        Route::post('events/{event}/retry', 'retry')->whereNumber('event')->middleware('throttle:20,1,api-calendar-retry')->name('retry');
        Route::post('conflicts/{conflict}/undo', 'undo')->whereNumber('conflict')->middleware('throttle:30,1,api-calendar-undo')->name('undo');
    });
});
