<?php

use App\Http\Controllers\Notifications\NotificationsController;
use App\Http\Controllers\Settings\ActiveRoleController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ConsentController;
use App\Http\Controllers\Settings\CurrentStateController;
use App\Http\Controllers\Settings\LicenceController;
use App\Http\Controllers\Settings\PrivacyController;
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
        ->middleware('throttle:30,1,devices-destroy')
        ->name('devices.destroy');

    // Privacy requests (A4.3b, TDPSA): see or correct my data.
    Route::post('settings/privacy', [PrivacyController::class, 'store'])
        ->middleware('throttle:5,60,privacy-store')
        ->name('privacy.store');
    Route::get('settings/privacy/{privacyRequest}/download', [PrivacyController::class, 'download'])
        ->whereNumber('privacyRequest')
        ->middleware(['signed', 'throttle:20,60,privacy-download'])
        ->name('privacy.download');

    // Notifications by kind and channel, quiet hours and digests (B6.2).
    Route::get('settings/notifications', [NotificationsController::class, 'settings'])->name('notification-settings.edit');
    Route::put('settings/notifications', [NotificationsController::class, 'update'])->middleware('throttle:30,1,notification-settings')->name('notification-settings.update');

    // Privacy switches and "Your consents" (A6.3).
    Route::get('settings/privacy', [ConsentController::class, 'edit'])->name('consents.edit');
    Route::put('settings/privacy/consents/{purpose}', [ConsentController::class, 'update'])
        ->middleware('throttle:30,1,consents-update')
        ->name('consents.update');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1,user-password-update')
        ->name('user-password.update');

    // The role switcher (A3.2): this browser remembers the choice.
    Route::put('settings/active-role', [ActiveRoleController::class, 'update'])
        ->middleware('throttle:30,1,active-role-update')
        ->name('active-role.update');

    // Licences and the state switcher (A5.1).
    Route::post('settings/licences', [LicenceController::class, 'store'])->middleware('throttle:10,1,licences-store')->name('licences.store');
    Route::patch('settings/licences/{licence}', [LicenceController::class, 'update'])->whereNumber('licence')->middleware('throttle:30,1,licences-update')->name('licences.update');
    Route::delete('settings/licences/{licence}', [LicenceController::class, 'destroy'])->whereNumber('licence')->middleware('throttle:30,1,licences-destroy')->name('licences.destroy');
    Route::post('settings/licences/{licence}/restore', [LicenceController::class, 'restore'])->whereNumber('licence')->middleware('throttle:30,1,licences-restore')->name('licences.restore');
    Route::put('settings/current-state', [CurrentStateController::class, 'update'])->middleware('throttle:30,1,current-state-update')->name('current-state.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])
        ->middleware('throttle:30,1,appearance-update')
        ->name('appearance.update');
});
