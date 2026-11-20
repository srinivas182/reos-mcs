<?php

use App\Http\Controllers\Auth\PhoneSignInController;
use App\Http\Controllers\Auth\SocialSignInController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

// Accounts (A3.1): Google, Apple and phone-code sign-in on the web. The
// callback addresses are the ones Settings › Integrations shows each provider.
Route::middleware('guest')->group(function () {
    Route::get('auth/{provider}/redirect', [SocialSignInController::class, 'redirect'])
        ->whereIn('provider', ['google', 'apple'])->middleware('throttle:sign-in')->name('auth.social.redirect');
    Route::get('auth/{provider}/demo', [SocialSignInController::class, 'demo'])
        ->whereIn('provider', ['google', 'apple'])->name('auth.social.demo');
    Route::post('auth/phone/start', [PhoneSignInController::class, 'start'])
        ->middleware('throttle:phone-code')->name('auth.phone.start');
    Route::post('auth/phone/verify', [PhoneSignInController::class, 'verify'])
        ->middleware('throttle:sign-in')->name('auth.phone.verify');
});

Route::match(['get', 'post'], 'integrations/google-sign-in/callback', [SocialSignInController::class, 'callback'])
    ->middleware(['guest', 'throttle:sign-in'])->name('auth.social.callback.google');
// Apple posts the answer back from its own site, so the callbacks can't carry a
// CSRF token (see bootstrap/app.php); the one-time `state` protects them instead.
Route::match(['get', 'post'], 'integrations/apple-sign-in/callback', [SocialSignInController::class, 'callback'])
    ->middleware(['guest', 'throttle:sign-in'])->name('auth.social.callback.apple');
