<?php

use App\Http\Controllers\Agents\AgentSetupPageController;
use App\Http\Controllers\Api\Certificates\CertificateController;
use App\Http\Controllers\Api\Providers\ProviderLicenceController;
use App\Http\Controllers\Notifications\NotificationsController;
use App\Http\Controllers\Notifications\TextsController;
use App\Http\Controllers\Settings\ActiveRoleController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\AvatarController;
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
    Route::post('settings/notifications/email/resume', [NotificationsController::class, 'resumeEmail'])->middleware('throttle:5,10,email-resume')->name('notification-settings.resume-email');
    // Texts (B6.2c): the mobile number and the texts switch.
    Route::post('settings/notifications/phone', [TextsController::class, 'sendCode'])->middleware('throttle:5,10,texts-code')->name('texts.phone');
    Route::post('settings/notifications/phone/verify', [TextsController::class, 'verify'])->middleware('throttle:10,10,texts-verify')->name('texts.verify');
    Route::delete('settings/notifications/phone', [TextsController::class, 'remove'])->middleware('throttle:10,10,texts-remove')->name('texts.remove');
    Route::put('settings/notifications/texts', [TextsController::class, 'update'])->middleware('throttle:20,1,texts-switch')->name('texts.update');

    // Privacy switches and "Your consents" (A6.3).
    Route::get('settings/privacy', [ConsentController::class, 'edit'])->name('consents.edit');

    // Avatar consent for instructors (A7.1b).
    Route::get('settings/avatar', [AvatarController::class, 'edit'])->name('avatar.edit');
    Route::post('settings/avatar', [AvatarController::class, 'store'])->middleware('throttle:6,1,avatar-consent')->name('avatar.store');
    Route::delete('settings/avatar', [AvatarController::class, 'destroy'])->middleware('throttle:6,1,avatar-withdraw')->name('avatar.destroy');
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
    // My goal (A11.1a, screen 146): agents change their mode and flags here.
    Route::get('settings/goal', [AgentSetupPageController::class, 'goal'])->name('goal.edit');
    // Certificates for CE taken elsewhere (B10.3b): read by OCR, checked, saved.
    Route::post('settings/certificates/read', [CertificateController::class, 'read'])->middleware('throttle:10,1,certificates-read')->name('certificates.read');
    Route::post('settings/certificates', [CertificateController::class, 'store'])->middleware('throttle:20,1,certificates-store')->name('certificates.store');
    // A provider's professional licence (B10.3).
    Route::post('settings/provider-licences', [ProviderLicenceController::class, 'store'])->middleware('throttle:10,1,provider-licences')->name('provider-licences.store');
    Route::put('settings/current-state', [CurrentStateController::class, 'update'])->middleware('throttle:30,1,current-state-update')->name('current-state.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])
        ->middleware('throttle:30,1,appearance-update')
        ->name('appearance.update');
});
