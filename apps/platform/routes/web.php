<?php

use App\Http\Controllers\Auth\PhoneSignInController;
use App\Http\Controllers\Auth\SocialSignInController;
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
