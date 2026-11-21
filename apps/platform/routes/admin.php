<?php

use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\DemandController;
use App\Http\Controllers\Admin\IntegrationCheckController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\IntegrationCredentialController;
use App\Http\Controllers\Admin\IntegrationModeController;
use App\Http\Controllers\Admin\PeopleController;
use App\Http\Controllers\Admin\PersonRoleController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Middleware\ConfirmIdentity;
use App\Http\Middleware\EnsureAdminTwoStep;
use Illuminate\Support\Facades\Route;

/*
| REOS super admin back office (web journey screens 58–71): its own menu in
| the sidebar, not part of personal Settings. Each area checks its permission.
*/

// Integration Hub (Sprint 2.4): super admin only, and the password must have
// been confirmed recently, because these pages hold the platform's vendor keys.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:integrations.manage', ConfirmIdentity::class])
    ->prefix('admin/integrations')
    ->name('integrations.')
    ->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        // Catalogue (B3.2, screens 64–65): demand, then each tool's tabs.
        Route::get('demand', DemandController::class)->name('demand');
        Route::get('{integration}', [IntegrationController::class, 'show'])->name('show');
        Route::get('{integration}/{tab}', [IntegrationController::class, 'show'])->whereIn('tab', ['credentials', 'health', 'changelog'])->name('tab');

        Route::middleware('throttle:30,1')->group(function () {
            Route::put('{integration}/keys/{environment}', [IntegrationCredentialController::class, 'update'])->name('keys.update')->whereIn('environment', ['sandbox', 'live']);
            Route::delete('{integration}/keys/{environment}', [IntegrationCredentialController::class, 'destroy'])->name('keys.destroy')->whereIn('environment', ['sandbox', 'live']);
            Route::patch('{integration}/mode', [IntegrationModeController::class, 'update'])->name('mode.update');
            Route::put('{integration}/catalogue', [CatalogueController::class, 'update'])->name('catalogue.update');
            Route::put('{integration}/pause', [CatalogueController::class, 'pause'])->name('pause');
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
    ->prefix('admin/people')
    ->name('people.')
    ->group(function () {
        Route::get('/', [PeopleController::class, 'index'])->name('index');
        Route::get('{person}', [PeopleController::class, 'show'])->whereNumber('person')->name('show');

        Route::middleware(['throttle:30,1', 'can:manageRoles,person'])->group(function () {
            Route::post('{person}/roles', [PersonRoleController::class, 'store'])->whereNumber('person')->name('roles.store');
            Route::delete('{person}/roles', [PersonRoleController::class, 'destroy'])->whereNumber('person')->name('roles.destroy');
        });
    });

// States (A3.3, web journey screen 61): launch status and waitlists.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:states.manage'])
    ->prefix('admin/states')
    ->name('states.')
    ->group(function () {
        Route::get('/', [StateController::class, 'index'])->name('index');
        Route::patch('{state}', [StateController::class, 'update'])->middleware('throttle:30,1')->name('update');
    });

// Tenants (A4.1b, web journey screens 59 and 60): every organisation. Super
// admin only, with two-step and a recent password confirmation; every change
// is audited.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:tenants.manage', ConfirmIdentity::class])
    ->prefix('admin/tenants')
    ->name('tenants.')
    ->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::get('export', [TenantController::class, 'export'])->middleware('throttle:10,1')->name('export');
        Route::post('/', [TenantController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('{tenant}', [TenantController::class, 'show'])->whereNumber('tenant')->name('show');
        Route::get('{tenant}/{tab}', [TenantController::class, 'show'])->whereNumber('tenant')->whereIn('tab', TenantController::TABS)->name('tab');
        Route::patch('{tenant}', [TenantController::class, 'update'])->whereNumber('tenant')->middleware('throttle:30,1')->name('update');
        Route::put('{tenant}/status', [TenantController::class, 'status'])->whereNumber('tenant')->middleware('throttle:30,1')->name('status');
    });
