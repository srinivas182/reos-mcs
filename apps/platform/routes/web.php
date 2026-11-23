<?php

use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\PhoneSignInController;
use App\Http\Controllers\Auth\SocialSignInController;
use App\Http\Controllers\Auth\SsoSignInController;
use App\Http\Controllers\Auth\TestIdentityProviderController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\BrandLogoController;
use App\Http\Controllers\Calendar\CalendarPageController;
use App\Http\Controllers\Connections\ConnectController;
use App\Http\Controllers\Connections\ConnectionsPageController;
use App\Http\Controllers\Connections\OAuthCallbackController;
use App\Http\Controllers\Connections\SimulatorSignInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Learn\LearnController;
use App\Http\Controllers\Learn\LessonMediaController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Messaging\AnnouncementController;
use App\Http\Controllers\Messaging\AttachmentController;
use App\Http\Controllers\Messaging\MessagesController;
use App\Http\Controllers\Notifications\NotificationsController;
use App\Http\Controllers\Onboarding\GetStartedController;
use App\Http\Controllers\Onboarding\PlanSetupController;
use App\Http\Controllers\Onboarding\PublicSiteController;
use App\Http\Controllers\Onboarding\TenantSignupController;
use App\Http\Controllers\Organisation\AgentsController;
use App\Http\Controllers\Organisation\BrandingController;
use App\Http\Controllers\Organisation\DomainController;
use App\Http\Controllers\Organisation\InvitationController;
use App\Http\Controllers\Organisation\SettingsController;
use App\Http\Controllers\Organisation\SsoController;
use App\Http\Controllers\Payouts\EarningsController;
use App\Http\Controllers\Payouts\SalesController;
use App\Http\Controllers\Payouts\SimulatorController as PayoutSimulatorController;
use App\Http\Controllers\School\ApprovalsController as SchoolApprovalsController;
use App\Http\Controllers\School\InstructorsController as SchoolInstructorsController;
use App\Http\Controllers\Sessions\SessionsController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\SupportConsentController;
use App\Http\Controllers\SupportSessionController;
use App\Http\Controllers\WhatsNewController;
use App\Http\Middleware\EnsureAdminTwoStep;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RequireFlag;
use App\Services\Tenancy\TenantSignups;
use Illuminate\Support\Facades\Route;

// The public site (web journey screen 1) and each audience's landing page.
Route::get('/', [PublicSiteController::class, 'home'])->name('home');
// Brokerages and schools sign up and pay on the web (A6.1b, the Sprint 6 gate).
Route::middleware(['auth', 'verified'])->prefix('start/{kind}')->whereIn('kind', array_keys(TenantSignups::TYPES))->name('start.')->group(function () {
    Route::get('/', [TenantSignupController::class, 'show'])->name('show');
    Route::post('/', [TenantSignupController::class, 'store'])->middleware('throttle:10,1,tenant-signup')->name('store');
});
// The public status page (A6.1).
// The terms and the privacy notice people agree to (A6.3).
Route::get('terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('status', StatusController::class)->middleware('throttle:60,1,status')->name('status');
Route::get('for/{audience}', [PublicSiteController::class, 'audience'])
    ->whereIn('audience', PublicSiteController::AUDIENCES)->name('audience');

// Becoming an agent on the web (A3.3c): the phone's path, before an account.
Route::prefix('get-started')->name('get-started')->group(function () {
    Route::get('/', [GetStartedController::class, 'state']);
    Route::post('state', [GetStartedController::class, 'chooseState'])->middleware('throttle:30,1,state')->name('.state');
    Route::get('waitlist/{state}', [GetStartedController::class, 'waitlist'])->name('.waitlist');
    Route::post('waitlist/{state}', [GetStartedController::class, 'joinWaitlist'])->middleware('throttle:10,1,waitlist-join')->name('.waitlist.join');
    Route::get('quiz', [GetStartedController::class, 'quiz'])->name('.quiz');
    Route::post('quiz', [GetStartedController::class, 'answer'])->middleware('throttle:20,1,quiz-answer')->name('.quiz.answer');
    Route::get('score', [GetStartedController::class, 'score'])->name('.score');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('setup', [PlanSetupController::class, 'show'])->name('.setup');
        Route::post('setup', [PlanSetupController::class, 'store'])->middleware('throttle:30,1,setup-store')->name('.setup.store');
        Route::get('plan', [PlanSetupController::class, 'plan'])->name('.plan');
    });
});
Route::get('sign-up/{path}', [GetStartedController::class, 'signUp'])->name('sign-up');

Route::middleware(['auth', 'verified', EnsureOnboarded::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // The calendar (B4.1): appointments made on the agent dashboard (screen 12).
    Route::controller(CalendarPageController::class)->prefix('calendar')->name('calendar.')->group(function () {
        Route::post('events', 'store')->middleware('throttle:60,1,calendar-store')->name('store');
        Route::put('events/{event}', 'update')->whereNumber('event')->middleware('throttle:60,1,calendar-update')->name('update');
        Route::delete('events/{event}', 'destroy')->whereNumber('event')->middleware('throttle:60,1,calendar-destroy')->name('destroy');
        Route::post('events/{event}/restore', 'restore')->whereNumber('event')->middleware('throttle:60,1,calendar-restore')->name('restore');
        Route::post('events/{event}/retry', 'retry')->whereNumber('event')->middleware('throttle:20,1,calendar-retry')->name('retry');
        Route::post('conflicts/{conflict}/undo', 'undo')->whereNumber('conflict')->middleware('throttle:30,1,calendar-undo')->name('undo');
    });

    // Connections (B3.2c, web journey screen 19): the tools this person may use.
    Route::controller(ConnectionsPageController::class)->prefix('connections')->name('connections.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('tools/{tool}/interest', 'interest')->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,interest')->name('interest');
        Route::delete('tools/{tool}/interest', 'forget')->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,forget')->name('forget');
        Route::post('requests', 'request')->middleware('throttle:10,60,request')->name('request');
        Route::post('{connection}/sync', 'sync')->whereNumber('connection')->middleware('throttle:10,1,sync')->name('sync');
        Route::patch('{connection}', 'update')->whereNumber('connection')->middleware('throttle:30,1,update')->name('update');
        Route::delete('{connection}', 'destroy')->whereNumber('connection')->middleware('throttle:30,1,destroy')->name('destroy');
    });

    // Plan and billing (B5.1, screen 29 and each role's equivalent) and checkout on reos.com (screen 3).
    Route::get('settings/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('settings/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:10,1,billing-portal')->name('billing.portal');
    Route::post('settings/billing/codes', [BillingController::class, 'redeem'])->middleware('throttle:10,10,billing-codes')->name('billing.codes');
    Route::controller(CheckoutController::class)->prefix('checkout')->name('checkout.')->group(function () {
        Route::get('{price}', 'show')->whereNumber('price')->name('show');
        Route::post('{price}', 'start')->whereNumber('price')->middleware('throttle:10,1,checkout-start')->name('start');
        Route::post('orders/{order}/simulate', 'simulate')->middleware('throttle:10,1,checkout-simulate')->name('simulate');
        Route::get('orders/{order}/return', 'complete')->middleware('throttle:30,1,checkout-return')->name('complete');
    });

    // Learn (A5.3b): courses for my state, search inside them, a lesson opened at its place.
    Route::middleware('can:learning.study')->controller(LearnController::class)->prefix('learn')->name('learn.')->group(function () {
        Route::get('/', 'index')->middleware('throttle:60,1,learn')->name('index');
        Route::get('lessons/{lesson}', 'lesson')->whereNumber('lesson')->name('lesson');
    });
});

// Support sign-in (A4.1c): the tenant owner answers; support ends its session.
// Platform scope: the owner may be working in another of their roles, so the
// controller checks ownership itself.
Route::middleware(['auth', 'verified', 'tenancy.platform'])->prefix('support-access/{grant}')->whereNumber('grant')->name('support-access.')->group(function () {
    Route::get('/', [SupportConsentController::class, 'show'])->name('show');
    Route::post('approve', [SupportConsentController::class, 'approve'])->middleware('throttle:10,1,approve')->name('approve');
    Route::post('decline', [SupportConsentController::class, 'decline'])->middleware('throttle:10,1,decline')->name('decline');
    Route::post('revoke', [SupportConsentController::class, 'revoke'])->middleware('throttle:10,1,revoke')->name('revoke');
});
Route::delete('support-session', [SupportSessionController::class, 'destroy'])->middleware(['auth', 'verified'])->name('support-session.destroy');

// Branding and domain (A4.2a, screen 57): the organisation managed in the active role.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/branding')->name('branding.')->group(function () {
    Route::get('/', [BrandingController::class, 'show'])->name('show');
    Route::put('/', [BrandingController::class, 'update'])->middleware('throttle:30,1,update')->name('update');
    Route::post('logo', [BrandingController::class, 'logo'])->middleware('throttle:10,1,logo')->name('logo');
    Route::delete('logo', [BrandingController::class, 'removeLogo'])->middleware('throttle:10,1,logo-destroy')->name('logo.destroy');
});
// The tenant's own domain (A4.2b), from screens 41 and 57.
// Agents (A4.3a, broker and team leader screen 32) and invitations (screens 32 and 41).
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->group(function () {
    Route::get('agents', [AgentsController::class, 'index'])->name('agents.index');
    Route::get('agents/export', [AgentsController::class, 'export'])->middleware('throttle:10,1,agents-export')->name('agents.export');
    Route::post('organisation/invites', [InvitationController::class, 'store'])->middleware('throttle:20,1,invites-store')->name('invites.store');
    Route::post('organisation/invites/{invitation}/resend', [InvitationController::class, 'resend'])->whereNumber('invitation')->middleware('throttle:30,1,invites-resend')->name('invites.resend');
    Route::delete('organisation/invites/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation')->middleware('throttle:30,1,invites-destroy')->name('invites.destroy');
    Route::post('organisation/seats/request', [InvitationController::class, 'requestSeats'])->middleware('throttle:5,1,seats-request')->name('seats.request');
});

// Single sign-on settings (A4.2c, screen 57).
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/sso')->name('sso.')->group(function () {
    Route::put('/', [SsoController::class, 'update'])->middleware('throttle:10,1,update')->name('update');
    Route::post('test-provider', [SsoController::class, 'useTestProvider'])->middleware('throttle:10,1,test-provider')->name('test-provider');
    Route::post('test', [SsoController::class, 'test'])->middleware('throttle:10,1,test')->name('test');
    Route::post('enable', [SsoController::class, 'enable'])->middleware('throttle:10,1,enable')->name('enable');
    Route::post('disable', [SsoController::class, 'disable'])->middleware('throttle:10,1,disable')->name('disable');
});

Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/domain')->name('domain.')->group(function () {
    Route::post('/', [DomainController::class, 'store'])->middleware('throttle:10,1,store')->name('store');
    Route::post('verify', [DomainController::class, 'verify'])->middleware('throttle:20,1,verify')->name('verify');
    Route::delete('/', [DomainController::class, 'destroy'])->middleware('throttle:10,1,destroy')->name('destroy');
});
// Brokerage settings (A4.2b, broker screen 41): domain, brand, people and plan.
Route::get('organisation/settings', SettingsController::class)
    ->middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->name('organisation.settings');
// Sales and payouts (B5.2b, school admin screen 53): products, the payout
// breakdown, Stripe payouts or the school's own Stripe account, export.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/sales')->name('sales.')->group(function () {
    Route::get('/', [SalesController::class, 'index'])->name('index');
    Route::get('export', [SalesController::class, 'export'])->middleware('throttle:20,1,sales-export')->name('export');
    Route::post('connect', [SalesController::class, 'connect'])->middleware('throttle:10,1,sales-connect')->name('connect');
    Route::post('own-account', [SalesController::class, 'connectOwn'])->middleware('throttle:10,1,sales-own')->name('connect-own');
    Route::get('own-account', [SalesController::class, 'ownAccount'])->middleware('throttle:10,1,sales-own-back')->name('own-account');
});
// Course catalogue and state approvals (A7.1, school admin screen 50).
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:courses.manage'])->prefix('school/approvals')->name('school.approvals.')->group(function () {
    Route::get('/', [SchoolApprovalsController::class, 'index'])->name('index');
    Route::get('defaults', [SchoolApprovalsController::class, 'defaults'])->middleware('throttle:60,1,approval-defaults')->name('defaults');
    Route::middleware('throttle:60,1,approvals')->group(function () {
        Route::post('/', [SchoolApprovalsController::class, 'store'])->name('store');
        Route::patch('{approval}', [SchoolApprovalsController::class, 'update'])->whereNumber('approval')->name('update');
        Route::put('{approval}/status', [SchoolApprovalsController::class, 'status'])->whereNumber('approval')->name('status');
    });
});
// Instructors (A7.1b, school admin screen 52): courses they teach, avatar consent, Revoke with Undo.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:courses.manage', 'throttle:60,1,instructors'])->prefix('school/instructors')->name('school.instructors.')->group(function () {
    Route::get('/', [SchoolInstructorsController::class, 'index'])->withoutMiddleware('throttle:60,1,instructors')->name('index');
    Route::put('{instructor}/courses', [SchoolInstructorsController::class, 'courses'])->whereNumber('instructor')->name('courses');
    Route::delete('{instructor}', [SchoolInstructorsController::class, 'revoke'])->whereNumber('instructor')->name('revoke');
    Route::post('{instructor}/restore', [SchoolInstructorsController::class, 'restore'])->whereNumber('instructor')->name('restore');
});
// The instructor's avatar sample, through a signed link (A7.1b).
Route::get('avatar-samples/{consent}', [AvatarController::class, 'sample'])->whereUuid('consent')->middleware(['signed', 'throttle:60,1,avatar-sample'])->name('avatar.sample');
// Earnings (B5.2b): instructors and mentors.
// Messages (B6.1): behind the `messages` flag (screen 70) while it rolls out.
Route::middleware(['auth', 'verified', RequireFlag::class.':messages'])->prefix('messages')->name('messages.')->group(function () {
    Route::get('/', [MessagesController::class, 'index'])->name('index');
    Route::post('/', [MessagesController::class, 'store'])->middleware('throttle:20,1,messages-start')->name('store');
    Route::post('{conversation}', [MessagesController::class, 'send'])->whereUuid('conversation')->middleware('throttle:60,1,messages-send')->name('send');
    Route::post('{conversation}/read', [MessagesController::class, 'read'])->whereUuid('conversation')->middleware('throttle:120,1,messages-read')->name('read');
    Route::get('{conversation}/older', [MessagesController::class, 'older'])->whereUuid('conversation')->middleware('throttle:60,1,messages-older')->name('older');
    Route::patch('items/{message}', [MessagesController::class, 'update'])->whereUuid('message')->middleware('throttle:30,1,messages-edit')->name('update');
    Route::delete('items/{message}', [MessagesController::class, 'destroy'])->whereUuid('message')->middleware('throttle:30,1,messages-edit')->name('destroy');
    // Report and block (B6.1b).
    Route::post('{conversation}/report', [MessagesController::class, 'report'])->whereUuid('conversation')->middleware('throttle:10,10,messages-report')->name('report');
    Route::post('blocks', [MessagesController::class, 'block'])->middleware('throttle:20,1,messages-block')->name('block');
    Route::delete('blocks/{person}', [MessagesController::class, 'unblock'])->whereNumber('person')->middleware('throttle:20,1,messages-block')->name('unblock');
});

// A file sent with a message, through its short-lived signed link (B6.1b).
Route::get('messages/attachments/{attachment}', AttachmentController::class)->whereUuid('attachment')
    ->middleware(['signed', 'throttle:120,1,message-attachments'])->name('messages.attachments.show');

// The notification centre (B6.2): the inbox behind the bell.
Route::middleware(['auth', 'verified'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationsController::class, 'index'])->name('index');
    Route::post('read', [NotificationsController::class, 'readAll'])->middleware('throttle:60,1,notifications-read')->name('read-all');
    Route::post('{notification}/read', [NotificationsController::class, 'read'])->whereUuid('notification')->middleware('throttle:120,1,notifications-read')->name('read');
});

// Announcements (B6.1b): admins send; everyone reads theirs on Messages and Today.
Route::middleware(['auth', 'verified', RequireFlag::class.':messages'])->prefix('announcements')->name('announcements.')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index'])->name('index');
    Route::post('/', [AnnouncementController::class, 'store'])->middleware('throttle:10,1,announcements-send')->name('store');
    Route::post('{announcement}/read', [AnnouncementController::class, 'read'])->whereUuid('announcement')->middleware('throttle:120,1,announcements-read')->name('read');
});

// Video sessions (B6.3): behind the `video_rooms` flag while it rolls out.
Route::middleware(['auth', 'verified', RequireFlag::class.':video_rooms'])->prefix('sessions')->name('sessions.')->controller(SessionsController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:20,1,sessions-schedule')->name('store');
    Route::get('{session}', 'show')->whereUuid('session')->name('show');
    Route::post('{session}/join', 'join')->whereUuid('session')->middleware('throttle:30,1,sessions-join')->name('join');
    Route::post('{session}/end', 'end')->whereUuid('session')->middleware('throttle:10,1,sessions-end')->name('end');
    Route::post('{session}/cancel', 'cancel')->whereUuid('session')->middleware('throttle:10,1,sessions-cancel')->name('cancel');
    Route::put('{session}/notes', 'notes')->whereUuid('session')->middleware('throttle:60,1,sessions-notes')->name('notes');
    // The shared whiteboard and quick actions (B6.3b).
    Route::get('{session}/whiteboard', 'whiteboard')->whereUuid('session')->middleware('throttle:60,1,sessions-board')->name('whiteboard');
    Route::post('{session}/whiteboard', 'saveWhiteboard')->whereUuid('session')->middleware('throttle:30,1,sessions-board-save')->name('whiteboard.save');
    Route::get('{session}/share-options', 'shareOptions')->whereUuid('session')->middleware('throttle:30,1,sessions-share')->name('share-options');
    Route::post('{session}/shares', 'share')->whereUuid('session')->middleware('throttle:20,1,sessions-share')->name('shares');
    // Recording only with consent (B6.3c).
    Route::post('{session}/leave', 'leave')->whereUuid('session')->middleware('throttle:30,1,sessions-leave')->name('leave');
    Route::post('{session}/recordings', 'record')->whereUuid('session')->middleware('throttle:10,1,sessions-record')->name('recordings.store');
    Route::post('{session}/recordings/{recording}/answer', 'answerRecording')->whereUuid(['session', 'recording'])->middleware('throttle:30,1,sessions-record-answer')->name('recordings.answer');
    Route::post('{session}/recordings/{recording}/stop', 'stopRecording')->whereUuid(['session', 'recording'])->middleware('throttle:20,1,sessions-record-stop')->name('recordings.stop');
    Route::delete('{session}/recordings/{recording}', 'deleteRecording')->whereUuid(['session', 'recording'])->middleware('throttle:10,1,sessions-record-delete')->name('recordings.destroy');
});

// A recording on the local disk, through its short-lived signed link (B6.3c).
Route::get('sessions/recordings/{recording}/file', [SessionsController::class, 'recordingFile'])->whereUuid('recording')
    ->middleware(['signed', 'throttle:120,1,sessions-recording-file'])->name('sessions.recordings.file');

// The picture of a session's whiteboard, through its short-lived signed link (B6.3b).
Route::get('sessions/{session}/whiteboard.png', [SessionsController::class, 'whiteboardImage'])->whereUuid('session')
    ->middleware(['signed', 'throttle:120,1,sessions-board-image'])->name('sessions.whiteboard.image');

// "What's new" (A6.2): dismissed once, gone on the web and the phone.
Route::post('whats-new/{release}/dismiss', [WhatsNewController::class, 'dismiss'])->whereNumber('release')->middleware(['auth', 'throttle:30,1,whats-new'])->name('whats-new.dismiss');

Route::middleware(['auth', 'verified'])->prefix('earnings')->name('earnings.')->group(function () {
    Route::get('/', [EarningsController::class, 'index'])->name('index');
    Route::post('connect', [EarningsController::class, 'connect'])->middleware('throttle:10,1,earnings-connect')->name('connect');
});
// Stripe's onboarding on the Simulator (never in production).
Route::middleware(['auth', 'verified', 'throttle:20,1,payouts-simulate'])->prefix('payouts/simulate/{account}')->name('payouts.')->group(function () {
    Route::get('/', [PayoutSimulatorController::class, 'show'])->name('simulate');
    Route::post('/', [PayoutSimulatorController::class, 'finish'])->name('simulate.finish');
});
// Lesson files on the local disk (development and demos): a signed,
// short-lived link like object storage's, answering range requests so video seeks.
Route::get('lesson-media/{media}', LessonMediaController::class)->whereNumber('media')
    ->middleware(['signed', 'throttle:120,1,lesson-media'])->name('lesson-media.show');
// A tenant's logo: public (its sign-in page shows it).
Route::get('brand/{tenant}/logo', BrandLogoController::class)->where('tenant', '[a-z0-9-]+')->name('brand.logo');

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

Route::get('integrations/google-sign-in/callback', [SocialSignInController::class, 'callback'])
    ->middleware('throttle:sign-in')->name('auth.social.callback.google');
// Apple posts the answer back from its own site, so the callbacks can't carry a
// CSRF token (see bootstrap/app.php); the one-time `state` protects them instead.
Route::get('integrations/apple-sign-in/callback', [SocialSignInController::class, 'callback'])
    ->middleware('throttle:sign-in')->name('auth.social.callback.apple');

// Accepting an invitation (A4.3a): by link or code, on the tenant's address.
Route::middleware('throttle:sign-in')->group(function () {
    Route::get('invite/{token}', [InviteController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('invite.show');
    Route::post('invite/{token}/accept', [InviteController::class, 'accept'])->where('token', '[A-Za-z0-9]{48}')->middleware('auth')->name('invite.accept');
    Route::post('invite/{token}/register', [InviteController::class, 'register'])->where('token', '[A-Za-z0-9]{48}')->middleware('guest')->name('invite.register');
    Route::get('invite/{token}/sign-in', [InviteController::class, 'signIn'])->where('token', '[A-Za-z0-9]{48}')->name('invite.sign-in');
    Route::get('join', [InviteController::class, 'join'])->name('join');
    Route::post('join', [InviteController::class, 'findCode'])->name('join.find');
});

// Single sign-on with a tenant's identity provider (A4.2c, journey screen 2).
// OIDC answers with a GET; SAML posts from the provider's site, which
// routes/handoff.php turns into a GET here. The one-time state, nonce and
// SAML request ID protect the callback.
Route::get('sso/complete/{ticket}', [SsoSignInController::class, 'complete'])->middleware('throttle:sign-in')->name('sso.complete');
Route::get('sso/{organisation:slug}/start', [SsoSignInController::class, 'start'])->middleware('throttle:sign-in')->name('sso.start');
Route::get('sso/{organisation:slug}/callback', [SsoSignInController::class, 'callback'])->middleware('throttle:sign-in')->name('sso.callback');
Route::get('sso/{organisation:slug}/metadata', [SsoSignInController::class, 'metadata'])->middleware('throttle:60,1,sso-metadata')->name('sso.metadata');
// REOS's test identity provider (outside production; 404 in production).
Route::get('sso-test-idp/authorize', [TestIdentityProviderController::class, 'oidc'])->middleware('throttle:sign-in')->name('sso.test-idp.oidc');
Route::get('sso-test-idp/saml', [TestIdentityProviderController::class, 'saml'])->middleware('throttle:sign-in')->name('sso.test-idp.saml');

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
