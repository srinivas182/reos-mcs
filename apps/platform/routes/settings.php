<?php

use App\Http\Controllers\Settings\ActiveRoleController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\ConfirmIdentity;
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
