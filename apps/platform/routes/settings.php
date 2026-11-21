<?php

use App\Http\Controllers\Settings\ActiveRoleController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\IntegrationCheckController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\IntegrationCredentialController;
use App\Http\Controllers\Settings\IntegrationModeController;
use App\Http\Controllers\Settings\PeopleController;
use App\Http\Controllers\Settings\PersonRoleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\ConfirmIdentity;
use App\Http\Middleware\EnsureAdminTwoStep;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(ConfirmIdentity::class)
        ->name('security.edit');
    Route::delete('settings/devices/{device}', [SecurityController::class, 'destroyDevice'])
        ->whereNumber('device')
        ->middleware('throttle:30,1')
        ->name('devices.destroy');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // The role switcher (A3.2): this browser remembers the choice.
    Route::put('settings/active-role', [ActiveRoleController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('active-role.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('appearance.update');
});

// Integration Hub (Sprint 2.4): super admin only, and the password must have
// been confirmed recently, because these pages hold the platform's vendor keys.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:integrations.manage', ConfirmIdentity::class])
    ->prefix('settings/integrations')
    ->name('integrations.')
    ->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::get('{integration}', [IntegrationController::class, 'show'])->name('show');

        Route::middleware('throttle:30,1')->group(function () {
            Route::put('{integration}/keys/{environment}', [IntegrationCredentialController::class, 'update'])->name('keys.update')->whereIn('environment', ['sandbox', 'live']);
            Route::delete('{integration}/keys/{environment}', [IntegrationCredentialController::class, 'destroy'])->name('keys.destroy')->whereIn('environment', ['sandbox', 'live']);
            Route::patch('{integration}/mode', [IntegrationModeController::class, 'update'])->name('mode.update');
        });

        // Each test calls the vendor, so it has its own, lower limit.
        Route::post('{integration}/checks/{environment}', [IntegrationCheckController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('checks.store')
            ->whereIn('environment', ['sandbox', 'live']);
    });

/* @chisel-passkeys */
Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
/* @end-chisel-passkeys */

// People (A3.2): super admin only, with two-step and a recent password
// confirmation, because roles decide what everyone can do. Every change is audited.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:people.manage', ConfirmIdentity::class])
    ->prefix('settings/people')
    ->name('people.')
    ->group(function () {
        Route::get('/', [PeopleController::class, 'index'])->name('index');
        Route::get('{person}', [PeopleController::class, 'show'])->whereNumber('person')->name('show');

        Route::middleware(['throttle:30,1', 'can:manageRoles,person'])->group(function () {
            Route::post('{person}/roles', [PersonRoleController::class, 'store'])->whereNumber('person')->name('roles.store');
            Route::delete('{person}/roles', [PersonRoleController::class, 'destroy'])->whereNumber('person')->name('roles.destroy');
        });
    });
