<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CrossSitePostHandoff;
use Illuminate\Support\Facades\Route;

/*
 * Sign-in answers identity providers post from their own sites (Apple,
 * SAML). Loaded without the web group on purpose: no session, so the
 * person's session cookie is never replaced (see CrossSitePostHandoff).
 * The one-time state checked on the GET protects them instead of a CSRF
 * token.
 */
Route::middleware('throttle:sign-in')->group(function () {
    // Apple's form_post; Google's demo sign-in page posts too (outside production).
    Route::post('integrations/apple-sign-in/callback', CrossSitePostHandoff::class)->name('auth.social.callback.apple.post');
    Route::post('integrations/google-sign-in/callback', CrossSitePostHandoff::class)->name('auth.social.callback.google.post');
    Route::post('sso/{organisation}/callback', CrossSitePostHandoff::class)->where('organisation', '[a-z0-9-]+')->name('sso.callback.post');
});
