<?php

use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\Content\ContentApprovalsController;
use App\Http\Controllers\Admin\Content\ContentLibraryController;
use App\Http\Controllers\Admin\Content\CourseController;
use App\Http\Controllers\Admin\Content\CourseFlagController;
use App\Http\Controllers\Admin\Content\CourseModuleController;
use App\Http\Controllers\Admin\Content\LessonController;
use App\Http\Controllers\Admin\Content\LessonFileController;
use App\Http\Controllers\Admin\DemandController;
use App\Http\Controllers\Admin\IntegrationCheckController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\IntegrationCredentialController;
use App\Http\Controllers\Admin\IntegrationModeController;
use App\Http\Controllers\Admin\LicenceReviewController;
use App\Http\Controllers\Admin\PeopleController;
use App\Http\Controllers\Admin\PersonRoleController;
use App\Http\Controllers\Admin\StateController;
use App\Http\Controllers\Admin\StateRuleController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\TenantSupportController;
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

        Route::middleware('throttle:30,1,admin-integrations')->group(function () {
            Route::put('{integration}/keys/{environment}', [IntegrationCredentialController::class, 'update'])->name('keys.update')->whereIn('environment', ['sandbox', 'live']);
            Route::delete('{integration}/keys/{environment}', [IntegrationCredentialController::class, 'destroy'])->name('keys.destroy')->whereIn('environment', ['sandbox', 'live']);
            Route::patch('{integration}/mode', [IntegrationModeController::class, 'update'])->name('mode.update');
            Route::put('{integration}/catalogue', [CatalogueController::class, 'update'])->name('catalogue.update');
            Route::put('{integration}/pause', [CatalogueController::class, 'pause'])->name('pause');
        });

        // Each test calls the vendor, so it has its own, lower limit.
        Route::post('{integration}/checks/{environment}', [IntegrationCheckController::class, 'store'])
            ->middleware('throttle:10,1,admin-integration-checks')
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

        Route::middleware(['throttle:30,1,admin-people-roles', 'can:manageRoles,person'])->group(function () {
            Route::post('{person}/roles', [PersonRoleController::class, 'store'])->whereNumber('person')->name('roles.store');
            Route::delete('{person}/roles', [PersonRoleController::class, 'destroy'])->whereNumber('person')->name('roles.destroy');
        });

        // Licence reviews (A5.1): no licence counts without a regulator match or this approval.
        Route::middleware('throttle:60,1,admin-licence-reviews')->group(function () {
            Route::post('licences/{licence}/approve', [LicenceReviewController::class, 'approve'])->whereNumber('licence')->name('licences.approve');
            Route::post('licences/{licence}/reject', [LicenceReviewController::class, 'reject'])->whereNumber('licence')->name('licences.reject');
        });
    });

// States (A3.3, web journey screen 61): launch status and waitlists.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:states.manage'])
    ->prefix('admin/states')
    ->name('states.')
    ->group(function () {
        Route::get('/', [StateController::class, 'index'])->name('index');
        Route::patch('{state}', [StateController::class, 'update'])->middleware('throttle:30,1,admin-states-update')->name('update');
        Route::put('{state}/details', [StateController::class, 'prepare'])->middleware('throttle:30,1,admin-states-prepare')->name('prepare');
        Route::put('{state}/checklist', [StateController::class, 'checklist'])->middleware('throttle:60,1,admin-states-checklist')->name('checklist');

        // State rules (A5.2, screen 62): drafts, publish, versions.
        Route::get('{state}/rules', [StateRuleController::class, 'show'])->name('rules.show');
        Route::put('{state}/rules/{key}', [StateRuleController::class, 'draft'])->where('key', '[a-z_]+\.[a-z_]+')->middleware('throttle:60,1,admin-state-rules-draft')->name('rules.draft');
        Route::delete('{state}/rules/drafts/{rule}', [StateRuleController::class, 'discard'])->whereNumber('rule')->middleware('throttle:60,1,admin-state-rules-discard')->name('rules.discard');
        Route::post('{state}/rules/publish', [StateRuleController::class, 'publish'])->middleware('throttle:20,1,admin-state-rules-publish')->name('rules.publish');
    });

// Tenants (A4.1b, web journey screens 59 and 60): every organisation. Super
// admin only, with two-step and a recent password confirmation; every change
// is audited.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:tenants.manage', ConfirmIdentity::class])
    ->prefix('admin/tenants')
    ->name('tenants.')
    ->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::get('export', [TenantController::class, 'export'])->middleware('throttle:10,1,admin-tenants-export')->name('export');
        Route::post('/', [TenantController::class, 'store'])->middleware('throttle:30,1,admin-tenants-store')->name('store');
        Route::get('{tenant}', [TenantController::class, 'show'])->whereNumber('tenant')->name('show');
        Route::get('{tenant}/{tab}', [TenantController::class, 'show'])->whereNumber('tenant')->whereIn('tab', TenantController::TABS)->name('tab');
        Route::patch('{tenant}', [TenantController::class, 'update'])->whereNumber('tenant')->middleware('throttle:30,1,admin-tenants-update')->name('update');
        Route::put('{tenant}/status', [TenantController::class, 'status'])->whereNumber('tenant')->middleware('throttle:30,1,admin-tenants-status')->name('status');
        // Support sign-in with the owner's consent (A4.1c).
        Route::post('{tenant}/support', [TenantSupportController::class, 'store'])->whereNumber('tenant')->middleware('throttle:10,1,admin-tenants-support')->name('support.store');
        Route::post('{tenant}/support/start', [TenantSupportController::class, 'start'])->whereNumber('tenant')->middleware('throttle:10,1,admin-tenants-support-start')->name('support.start');
    });

// Compliance and audit (A4.3b, web journey screen 71): privacy requests, the
// platform rules and the audit log. It holds personal data, so it asks for
// two-step and a recent password confirmation.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:audit.view', ConfirmIdentity::class])
    ->prefix('admin/compliance')
    ->name('compliance.')
    ->group(function () {
        Route::get('/', [ComplianceController::class, 'index'])->name('index');
        Route::get('audit.csv', [ComplianceController::class, 'export'])->middleware('throttle:10,1,compliance-export')->name('export');
        Route::patch('privacy/{privacyRequest}', [ComplianceController::class, 'privacy'])->whereNumber('privacyRequest')->middleware('throttle:30,1,compliance-privacy')->name('privacy.update');
    });

// Content library (A5.3; reached from screen 63, Content and approvals):
// REOS courses, lessons and their files, and the knowledge base built from
// them. Super admin only, with two-step; every change is audited.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:content.manage'])
    ->prefix('admin/content')
    ->name('content.')
    ->group(function () {
        Route::get('/', [ContentApprovalsController::class, 'index'])->name('index');
        Route::get('library', [ContentLibraryController::class, 'index'])->name('library');

        Route::middleware('throttle:60,1,admin-content')->group(function () {
            Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
            Route::patch('courses/{course}', [CourseController::class, 'update'])->whereNumber('course')->name('courses.update');
            Route::put('courses/{course}/status', [CourseController::class, 'status'])->whereNumber('course')->name('courses.status');
            Route::delete('courses/{course}', [CourseController::class, 'destroy'])->whereNumber('course')->name('courses.destroy');
            Route::post('courses/{course}/restore', [CourseController::class, 'restore'])->whereNumber('course')->name('courses.restore');
            // Legal flags (A5.3b): clear a line that's fine in context; Undo puts it back.
            Route::post('courses/{course}/flags/{flag}', [CourseFlagController::class, 'clear'])->whereNumber('course')->where('flag', '[a-f0-9]{64}:[a-z_]+')->name('flags.clear');
            Route::delete('courses/{course}/flags/{flag}', [CourseFlagController::class, 'restore'])->whereNumber('course')->where('flag', '[a-f0-9]{64}:[a-z_]+')->name('flags.restore');

            Route::post('courses/{course}/modules', [CourseModuleController::class, 'store'])->whereNumber('course')->name('modules.store');
            Route::patch('modules/{module}', [CourseModuleController::class, 'update'])->whereNumber('module')->name('modules.update');
            Route::delete('modules/{module}', [CourseModuleController::class, 'destroy'])->whereNumber('module')->name('modules.destroy');

            Route::post('courses/{course}/lessons', [LessonController::class, 'store'])->whereNumber('course')->name('lessons.store');
            Route::patch('lessons/{lesson}', [LessonController::class, 'update'])->whereNumber('lesson')->name('lessons.update');
            Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->whereNumber('lesson')->name('lessons.destroy');
            Route::post('lessons/{lesson}/restore', [LessonController::class, 'restore'])->whereNumber('lesson')->name('lessons.restore');
            Route::post('lessons/{lesson}/reindex', [LessonController::class, 'reindex'])->whereNumber('lesson')->name('lessons.reindex');

            Route::delete('files/{media}', [LessonFileController::class, 'destroy'])->whereNumber('media')->name('files.destroy');
        });

        // Uploads go straight to storage with a signed link; these start and finish one.
        Route::middleware('throttle:120,1,admin-content-files')->group(function () {
            Route::post('lessons/{lesson}/files', [LessonFileController::class, 'store'])->whereNumber('lesson')->name('files.store');
            Route::post('files/{media}/complete', [LessonFileController::class, 'complete'])->whereNumber('media')->name('files.complete');
        });

        Route::get('courses/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('courses.show');
        Route::get('lessons/{lesson}', [LessonController::class, 'show'])->whereNumber('lesson')->name('lessons.show');
    });
