<?php

use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Auth\PhoneSignInController;
use App\Http\Controllers\Auth\SocialSignInController;
use App\Http\Controllers\Connections\ConnectController;
use App\Http\Controllers\Connections\ConnectionsPageController;
use App\Http\Controllers\Connections\OAuthCallbackController;
use App\Http\Controllers\Connections\SimulatorSignInController;
use App\Http\Controllers\Onboarding\GetStartedController;
use App\Http\Controllers\Onboarding\PlanSetupController;
use App\Http\Controllers\Onboarding\PublicSiteController;
use App\Http\Middleware\EnsureOnboarded;
use Illuminate\Support\Facades\Route;

// The public site (web journey screen 1) and each audience's landing page.
Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('for/{audience}', [PublicSiteController::class, 'audience'])
    ->whereIn('audience', PublicSiteController::AUDIENCES)->name('audience');

// Becoming an agent on the web (A3.3c): the phone's path, before an account.
Route::prefix('get-started')->name('get-started')->group(function () {
    Route::get('/', [GetStartedController::class, 'state']);
    Route::post('state', [GetStartedController::class, 'chooseState'])->middleware('throttle:30,1')->name('.state');
    Route::get('waitlist/{state}', [GetStartedController::class, 'waitlist'])->name('.waitlist');
    Route::post('waitlist/{state}', [GetStartedController::class, 'joinWaitlist'])->middleware('throttle:10,1')->name('.waitlist.join');
    Route::get('quiz', [GetStartedController::class, 'quiz'])->name('.quiz');
    Route::post('quiz', [GetStartedController::class, 'answer'])->middleware('throttle:20,1')->name('.quiz.answer');
    Route::get('score', [GetStartedController::class, 'score'])->name('.score');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('setup', [PlanSetupController::class, 'show'])->name('.setup');
        Route::post('setup', [PlanSetupController::class, 'store'])->middleware('throttle:30,1')->name('.setup.store');
        Route::get('plan', [PlanSetupController::class, 'plan'])->name('.plan');
    });
});
Route::get('sign-up/{path}', [GetStartedController::class, 'signUp'])->name('sign-up');

Route::middleware(['auth', 'verified', EnsureOnboarded::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Connections (B3.2c, web journey screen 19): the tools this person may use.
    Route::controller(ConnectionsPageController::class)->prefix('connections')->name('connections.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('tools/{tool}/interest', 'interest')->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1')->name('interest');
        Route::delete('tools/{tool}/interest', 'forget')->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1')->name('forget');
        Route::post('requests', 'request')->middleware('throttle:10,60')->name('request');
        Route::post('{connection}/sync', 'sync')->whereNumber('connection')->middleware('throttle:10,1')->name('sync');
        Route::patch('{connection}', 'update')->whereNumber('connection')->middleware('throttle:30,1')->name('update');
        Route::delete('{connection}', 'destroy')->whereNumber('connection')->middleware('throttle:30,1')->name('destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

// Accounts (A3.1): Google, Apple and phone-code sign-in on the web. The
// callback addresses are the ones Settings › Integrations shows each provider.
// Not guest-only: the phone app signs in through the system browser, which
// may already be signed in to the web app.
Route::get('auth/{provider}/redirect', [SocialSignInController::class, 'redirect'])
    ->whereIn('provider', ['google', 'apple'])->middleware('throttle:sign-in')->name('auth.social.redirect');
Route::get('auth/{provider}/demo', [SocialSignInController::class, 'demo'])
    ->whereIn('provider', ['google', 'apple'])->name('auth.social.demo');

Route::middleware('guest')->group(function () {
    Route::post('auth/phone/start', [PhoneSignInController::class, 'start'])
        ->middleware('throttle:phone-code')->name('auth.phone.start');
    Route::post('auth/phone/verify', [PhoneSignInController::class, 'verify'])
        ->middleware('throttle:sign-in')->name('auth.phone.verify');
});

Route::match(['get', 'post'], 'integrations/google-sign-in/callback', [SocialSignInController::class, 'callback'])
    ->middleware('throttle:sign-in')->name('auth.social.callback.google');
// Apple posts the answer back from its own site, so the callbacks can't carry a
// CSRF token (see bootstrap/app.php); the one-time `state` protects them instead.
Route::match(['get', 'post'], 'integrations/apple-sign-in/callback', [SocialSignInController::class, 'callback'])
    ->middleware('throttle:sign-in')->name('auth.social.callback.apple');

// Connections (B3.1, ADR 0013): a person's own account at a tool. The callback
// is the address Settings › Integrations shows; the single-use `state` says
// who it's for, so it needs no session (the phone signs in in a browser sheet).
Route::get('connections/{tool}/connect', ConnectController::class)
    ->whereIn('tool', ConnectorRegistry::ids())
    ->middleware(['auth', 'verified', 'throttle:sign-in'])->name('connections.connect');
Route::match(['get', 'post'], 'integrations/{tool}/callback', OAuthCallbackController::class)
    ->whereIn('tool', ConnectorRegistry::ids())
    ->middleware(['throttle:sign-in', 'tenancy.platform'])->name('connections.callback');
// The demo-data stand-in for a tool's sign-in page (Simulator mode only).
Route::get('connections/simulator/{tool}', [SimulatorSignInController::class, 'show'])
    ->whereIn('tool', ConnectorRegistry::ids())->middleware('throttle:sign-in')->name('connections.simulator');
Route::post('connections/simulator/{tool}', [SimulatorSignInController::class, 'decide'])
    ->whereIn('tool', ConnectorRegistry::ids())->middleware(['throttle:sign-in', 'tenancy.platform'])->name('connections.simulator.decide');
