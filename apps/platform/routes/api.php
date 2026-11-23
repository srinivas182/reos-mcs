<?php

use App\Http\Controllers\Api\ActiveRoleController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\Ask\AskController;
use App\Http\Controllers\Api\Auth\ExchangeSignInController;
use App\Http\Controllers\Api\Auth\IdentitySignInController;
use App\Http\Controllers\Api\Auth\PhoneSignInController;
use App\Http\Controllers\Api\Billing\EarningsController;
use App\Http\Controllers\Api\Billing\EntitlementController;
use App\Http\Controllers\Api\Billing\MembershipController;
use App\Http\Controllers\Api\Bookings\BookingController;
use App\Http\Controllers\Api\Calendar\CalendarController;
use App\Http\Controllers\Api\Catalogue\CatalogueController;
use App\Http\Controllers\Api\Classes\ClassesController;
use App\Http\Controllers\Api\Connections\ConnectionController;
use App\Http\Controllers\Api\Crm\ContactController;
use App\Http\Controllers\Api\Crm\ContactWorkController;
use App\Http\Controllers\Api\Deals\DealController;
use App\Http\Controllers\Api\Deals\SignatureController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Instructor\QuestionController as InstructorQuestionController;
use App\Http\Controllers\Api\Learn\LearnController;
use App\Http\Controllers\Api\Learn\OfflineController;
use App\Http\Controllers\Api\Learn\PlayerController;
use App\Http\Controllers\Api\Learn\QuestionReportController;
use App\Http\Controllers\Api\Licence\LicenceJourneyController;
use App\Http\Controllers\Api\Licence\PassesAndOffersController;
use App\Http\Controllers\Api\Licence\SwitchController;
use App\Http\Controllers\Api\Licences\LicenceController;
use App\Http\Controllers\Api\Listings\CmaController;
use App\Http\Controllers\Api\Listings\ListingPostController;
use App\Http\Controllers\Api\Listings\ListingsController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\Messaging\AnnouncementController as ApiAnnouncementController;
use App\Http\Controllers\Api\Messaging\MessagesController as ApiMessagesController;
use App\Http\Controllers\Api\Notifications\NotificationsController as ApiNotificationsController;
use App\Http\Controllers\Api\Notifications\PushDeviceController;
use App\Http\Controllers\Api\Notifications\TextsController as ApiTextsController;
use App\Http\Controllers\Api\Onboarding\FreeQuizController;
use App\Http\Controllers\Api\Onboarding\OnboardingController;
use App\Http\Controllers\Api\Onboarding\StateController;
use App\Http\Controllers\Api\Practice\ChallengeController;
use App\Http\Controllers\Api\Practice\CoachController;
use App\Http\Controllers\Api\Practice\FlashcardController;
use App\Http\Controllers\Api\Practice\MathLabController;
use App\Http\Controllers\Api\Practice\PracticeController;
use App\Http\Controllers\Api\Practice\ReadinessController;
use App\Http\Controllers\Api\Practice\ReportController;
use App\Http\Controllers\Api\Practice\SpeedRoundController;
use App\Http\Controllers\Api\Practice\StudyGroupController;
use App\Http\Controllers\Api\Practice\TopicPlanController;
use App\Http\Controllers\Api\Privacy\ConsentController;
use App\Http\Controllers\Api\Releases\WhatsNewController;
use App\Http\Controllers\Api\Sessions\SessionsController as ApiSessionsController;
use App\Http\Controllers\Api\States\CurrentStateController;
use App\Http\Controllers\Api\Todos\TodoController;
use App\Http\Controllers\Api\Voice\VoiceController;
use App\Http\Middleware\RequireFlag;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
| REOS API v1. Every route here is served under /api/v1 and is used by the
| web app, the mobile app and partners alike (see docs/adr/0003-api-contracts.md).
*/

// Reads no tenant data and must answer even when the database is struggling.
Route::get('health', HealthController::class)->withoutMiddleware(ResolveTenant::class)->name('api.health');

Route::get('app-config', AppConfigController::class)
    ->middleware('throttle:60,1,api-app-config')
    ->name('api.app-config');

// Accounts (Sprint 3, A3.1). Sign-in is rate limited per network; phone codes
// also per number (see AppServiceProvider::configureRateLimits).
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('{provider}', IdentitySignInController::class)
        ->whereIn('provider', ['google', 'apple'])
        ->middleware('throttle:sign-in')
        ->name('identity');
    Route::post('exchange', ExchangeSignInController::class)
        ->middleware('throttle:sign-in')
        ->name('exchange');
    Route::post('phone/start', [PhoneSignInController::class, 'start'])
        ->middleware('throttle:phone-code')
        ->name('phone.start');
    Route::post('phone/verify', [PhoneSignInController::class, 'verify'])
        ->middleware('throttle:sign-in')
        ->name('phone.verify');
});

// Getting started (A3.3): public, before an account exists.
Route::middleware('throttle:60,1,api-getting-started')->group(function () {
    Route::get('states', [StateController::class, 'index'])->name('api.states');
    Route::get('states/{state}/free-quiz', [FreeQuizController::class, 'show'])->name('api.states.free-quiz');
});
Route::post('states/{state}/waitlist', [StateController::class, 'waitlist'])
    ->middleware('throttle:10,1,api-states-waitlist')
    ->name('api.states.waitlist');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.me');
    Route::put('me/onboarding', [OnboardingController::class, 'update'])->middleware('throttle:30,1,api-me-onboarding')->name('api.me.onboarding');
    Route::post('me/quiz-attempts', [FreeQuizController::class, 'store'])->middleware('throttle:30,1,api-me-quiz-attempts')->name('api.me.quiz-attempts');
    Route::delete('me', [MeController::class, 'destroy'])->middleware('throttle:sign-in')->name('api.me.destroy');
    Route::put('me/active-role', ActiveRoleController::class)->middleware('throttle:30,1,api-me-active-role')->name('api.me.active-role');
    Route::get('me/devices', [MeController::class, 'devices'])->name('api.me.devices');
    Route::delete('me/devices/{device}', [MeController::class, 'revokeDevice'])->whereNumber('device')->name('api.me.devices.destroy');
    Route::post('auth/sign-out', [MeController::class, 'signOut'])->name('api.auth.sign-out');

    // My licences and the state switcher (A5.1).
    Route::prefix('me/licences')->name('api.me.licences.')->controller(LicenceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:10,1,api-licences-store')->name('store');
        Route::post('check', 'check')->middleware('throttle:20,1,api-licences-check')->name('check');
        Route::get('{licence}/ce', 'ce')->whereNumber('licence')->name('ce');
        Route::patch('{licence}', 'update')->whereNumber('licence')->middleware('throttle:30,1,api-licences-update')->name('update');
        Route::delete('{licence}', 'destroy')->whereNumber('licence')->middleware('throttle:30,1,api-licences-destroy')->name('destroy');
        Route::post('{licence}/restore', 'restore')->whereNumber('licence')->middleware('throttle:30,1,api-licences-restore')->name('restore');
    });
    Route::get('me/states', [CurrentStateController::class, 'show'])->name('api.me.states');

    // What I may use (B5.1): the app unlocks from this within seconds of a purchase.
    Route::get('me/entitlements', [EntitlementController::class, 'index'])->name('api.me.entitlements');
    // The Membership screen (B5.1b, candidate screen 6): no prices in the app.
    Route::get('me/membership', [MembershipController::class, 'show'])->name('api.me.membership');
    Route::post('me/membership/email-link', [MembershipController::class, 'emailLink'])->middleware('throttle:3,10,api-membership-email')->name('api.me.membership.email-link');
    Route::post('me/codes', [MembershipController::class, 'redeem'])->middleware('throttle:10,10,api-codes')->name('api.me.codes');
    // Earnings for instructors and mentors (B5.2b).
    Route::get('me/earnings', EarningsController::class)->name('api.me.earnings');
    // Messages (B6.1), behind the `messages` flag.
    Route::middleware(RequireFlag::class.':messages')->prefix('messages')->name('api.messages.')->controller(ApiMessagesController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('contacts', 'contacts')->name('contacts');
        Route::post('/', 'store')->middleware('throttle:20,1,api-messages-start')->name('store');
        Route::get('{conversation}', 'show')->whereUuid('conversation')->name('show');
        Route::post('{conversation}', 'send')->whereUuid('conversation')->middleware('throttle:60,1,api-messages-send')->name('send');
        Route::post('{conversation}/read', 'read')->whereUuid('conversation')->middleware('throttle:120,1,api-messages-read')->name('read');
        Route::patch('items/{message}', 'update')->whereUuid('message')->middleware('throttle:30,1,api-messages-edit')->name('update');
        Route::delete('items/{message}', 'destroy')->whereUuid('message')->middleware('throttle:30,1,api-messages-edit')->name('destroy');
        // Report and block (B6.1b).
        Route::post('{conversation}/report', 'report')->whereUuid('conversation')->middleware('throttle:10,10,api-messages-report')->name('report');
        Route::post('blocks', 'block')->middleware('throttle:20,1,api-messages-block')->name('block');
        Route::delete('blocks/{person}', 'unblock')->whereNumber('person')->middleware('throttle:20,1,api-messages-block')->name('unblock');
    });
    // Announcements on Today (B6.1b).
    Route::middleware(RequireFlag::class.':messages')->prefix('announcements')->name('api.announcements.')->controller(ApiAnnouncementController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{announcement}/read', 'read')->whereUuid('announcement')->middleware('throttle:120,1,api-announcements-read')->name('read');
    });
    // Booking 1-on-1 sessions (A10.2a), with the video rooms they run in.
    Route::middleware(RequireFlag::class.':video_rooms')->prefix('bookings')->name('api.bookings.')->controller(BookingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:20,1,api-bookings-store')->name('store');
        Route::get('instructors', 'instructors')->name('instructors');
        Route::get('instructors/{user}', 'instructor')->whereNumber('user')->name('instructor');
        Route::get('offer', 'offer')->name('offer');
        Route::put('offer', 'saveOffer')->middleware('throttle:20,1,api-bookings-offer')->name('offer.save');
        Route::post('{booking}/cancel', 'cancel')->whereUuid('booking')->middleware('throttle:10,1,api-bookings-cancel')->name('cancel');
        Route::post('{booking}/rating', 'rate')->whereUuid('booking')->middleware('throttle:10,1,api-bookings-rate')->name('rating');
        Route::get('{booking}/tasks', 'suggestedTasks')->whereUuid('booking')->name('tasks');
        Route::post('{booking}/tasks', 'sendTasks')->whereUuid('booking')->middleware('throttle:10,1,api-bookings-tasks')->name('tasks.send');
    });
    // Live classes (A10.2b): scheduling, registration and the class tools, behind `video_rooms`.
    Route::middleware(RequireFlag::class.':video_rooms')->prefix('classes')->name('api.classes.')->controller(ClassesController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:20,1,api-classes-schedule')->name('store');
        Route::post('{session}/registration', 'register')->whereUuid('session')->middleware('throttle:20,1,api-classes-register')->name('register');
        Route::delete('{session}/registration', 'unregister')->whereUuid('session')->middleware('throttle:20,1,api-classes-register')->name('unregister');
        Route::get('{session}/live', 'live')->whereUuid('session')->middleware('throttle:120,1,api-classes-live')->name('live');
        Route::post('{session}/hand', 'hand')->whereUuid('session')->middleware('throttle:30,1,api-classes-hand')->name('hand');
        Route::post('{session}/stage', 'stage')->whereUuid('session')->middleware('throttle:60,1,api-classes-stage')->name('stage');
        Route::post('{session}/polls', 'poll')->whereUuid('session')->middleware('throttle:20,1,api-classes-poll')->name('polls');
        Route::post('{session}/polls/{poll}/close', 'closePoll')->whereUuid('session')->whereNumber('poll')->middleware('throttle:20,1,api-classes-poll')->name('polls.close');
        Route::post('{session}/polls/{poll}/votes', 'vote')->whereUuid('session')->whereNumber('poll')->middleware('throttle:30,1,api-classes-vote')->name('polls.vote');
        Route::post('{session}/questions', 'ask')->whereUuid('session')->middleware('throttle:10,1,api-classes-ask')->name('questions');
        Route::post('{session}/questions/{question}/like', 'like')->whereUuid('session')->whereNumber('question')->middleware('throttle:60,1,api-classes-like')->name('questions.like');
        Route::post('{session}/questions/{question}/answered', 'answered')->whereUuid('session')->whereNumber('question')->middleware('throttle:60,1,api-classes-answered')->name('questions.answered');
        Route::post('{session}/messages', 'chat')->whereUuid('session')->middleware('throttle:30,1,api-classes-chat')->name('messages');
        Route::post('{session}/checks', 'check')->whereUuid('session')->middleware('throttle:10,1,api-classes-check')->name('checks');
        Route::post('{session}/checks/{check}/answer', 'answerCheck')->whereUuid('session')->whereNumber('check')->middleware('throttle:30,1,api-classes-check-answer')->name('checks.answer');
        Route::get('{session}/report', 'report')->whereUuid('session')->name('report');
        Route::post('{session}/report/decisions', 'decide')->whereUuid('session')->middleware('throttle:60,1,api-classes-report')->name('report.decide');
        Route::post('{session}/report/send', 'sendReport')->whereUuid('session')->middleware('throttle:10,1,api-classes-report-send')->name('report.send');
    });
    // Video sessions (B6.3), behind the `video_rooms` flag.
    Route::middleware(RequireFlag::class.':video_rooms')->prefix('sessions')->name('api.sessions.')->controller(ApiSessionsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:20,1,api-sessions-schedule')->name('store');
        Route::get('{session}', 'show')->whereUuid('session')->name('show');
        Route::post('{session}/join', 'join')->whereUuid('session')->middleware('throttle:30,1,api-sessions-join')->name('join');
        Route::post('{session}/end', 'end')->whereUuid('session')->middleware('throttle:10,1,api-sessions-end')->name('end');
        Route::post('{session}/cancel', 'cancel')->whereUuid('session')->middleware('throttle:10,1,api-sessions-cancel')->name('cancel');
        Route::put('{session}/notes', 'notes')->whereUuid('session')->middleware('throttle:60,1,api-sessions-notes')->name('notes');
        // Quick actions (B6.3b); the whiteboard is drawn on the web, the phone shows its picture.
        Route::get('{session}/share-options', 'shareOptions')->whereUuid('session')->middleware('throttle:30,1,api-sessions-share')->name('share-options');
        Route::post('{session}/shares', 'share')->whereUuid('session')->middleware('throttle:20,1,api-sessions-share')->name('shares');
        // Recording only with consent (B6.3c).
        Route::post('{session}/leave', 'leave')->whereUuid('session')->middleware('throttle:30,1,api-sessions-leave')->name('leave');
        Route::post('{session}/recordings', 'record')->whereUuid('session')->middleware('throttle:10,1,api-sessions-record')->name('recordings.store');
        Route::post('{session}/recordings/{recording}/answer', 'answerRecording')->whereUuid(['session', 'recording'])->middleware('throttle:30,1,api-sessions-record-answer')->name('recordings.answer');
        Route::post('{session}/recordings/{recording}/stop', 'stopRecording')->whereUuid(['session', 'recording'])->middleware('throttle:20,1,api-sessions-record-stop')->name('recordings.stop');
        Route::delete('{session}/recordings/{recording}', 'deleteRecording')->whereUuid(['session', 'recording'])->middleware('throttle:10,1,api-sessions-record-delete')->name('recordings.destroy');
    });
    // The notification centre (B6.2): the inbox and my settings.
    Route::get('notifications', [ApiNotificationsController::class, 'index'])->name('api.notifications.index');
    Route::post('notifications/read', [ApiNotificationsController::class, 'readAll'])->middleware('throttle:60,1,api-notifications-read')->name('api.notifications.read-all');
    Route::post('notifications/{notification}/read', [ApiNotificationsController::class, 'read'])->whereUuid('notification')->middleware('throttle:120,1,api-notifications-read')->name('api.notifications.read');
    Route::get('me/notification-settings', [ApiNotificationsController::class, 'settings'])->name('api.me.notification-settings');
    Route::put('me/notification-settings', [ApiNotificationsController::class, 'update'])->middleware('throttle:30,1,api-notification-settings')->name('api.me.notification-settings.update');
    // Push to this phone (B6.2b).
    Route::post('me/push-devices', [PushDeviceController::class, 'store'])->middleware('throttle:20,1,api-push-devices')->name('api.me.push-devices.store');
    Route::post('me/email/resume', [ApiNotificationsController::class, 'resumeEmail'])->middleware('throttle:5,10,api-email-resume')->name('api.me.email.resume');
    // Texts (B6.2c).
    Route::post('me/phone', [ApiTextsController::class, 'sendCode'])->middleware('throttle:5,10,api-texts-code')->name('api.me.texts.phone');
    Route::post('me/phone/verify', [ApiTextsController::class, 'verify'])->middleware('throttle:10,10,api-texts-verify')->name('api.me.texts.verify');
    Route::put('me/texts', [ApiTextsController::class, 'update'])->middleware('throttle:20,1,api-texts-switch')->name('api.me.texts.update');
    Route::delete('me/push-devices/current', [PushDeviceController::class, 'destroy'])->middleware('throttle:20,1,api-push-devices')->name('api.me.push-devices.destroy');
    // Privacy switches and consents (A6.3, Me › Privacy).
    Route::get('me/consents', [ConsentController::class, 'index'])->name('api.me.consents');
    Route::put('me/consents/{purpose}', [ConsentController::class, 'update'])->middleware('throttle:30,1,api-consents')->name('api.me.consents.update');
    // What's new (A6.2): the latest release note for my role, shown once on Today.
    Route::get('me/whats-new', [WhatsNewController::class, 'show'])->name('api.me.whats-new');
    Route::post('me/whats-new/{release}/dismiss', [WhatsNewController::class, 'dismiss'])->whereNumber('release')->middleware('throttle:30,1,api-whats-new')->name('api.me.whats-new.dismiss');

    // Learn (A5.3b): my courses, search inside them, and a lesson to open.
    Route::middleware('can:learning.study')->prefix('learn')->name('api.learn.')->controller(LearnController::class)->group(function () {
        Route::get('courses', 'courses')->name('courses');
        Route::get('search', 'search')->middleware('throttle:60,1,api-learn-search')->name('search');
        Route::get('lessons/{lesson}', 'lesson')->whereNumber('lesson')->name('lesson');
    });
    // The player's seat time and check-ins (A8.2).
    // Learners' own progress, not admin actions: the controller checks learning.study.
    Route::prefix('learn')->name('api.learn.')->controller(PlayerController::class)->group(function () {
        Route::post('lessons/{lesson}/heartbeat', 'heartbeat')->whereNumber('lesson')->middleware('throttle:12,1,api-learn-heartbeat')->name('heartbeat');
        Route::post('lessons/{lesson}/checkins', 'checkin')->whereNumber('lesson')->middleware('throttle:30,1,api-learn-checkin')->name('checkin');
        Route::post('lessons/{lesson}/ask', 'ask')->whereNumber('lesson')->middleware('throttle:20,1,api-learn-ask')->name('ask');
    });
    // The licence journey (A10.3a): the learner's own, not admin actions (studying is checked in the controller).
    Route::prefix('licence-journey')->name('api.licence.')->controller(LicenceJourneyController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('steps/{code}', 'step')->where('code', '[a-z0-9_]+')->middleware('throttle:60,1,api-licence-step')->name('step');
        Route::put('exam', 'exam')->middleware('throttle:20,1,api-licence-exam')->name('exam');
        Route::put('kit/{item}', 'kit')->where('item', '[a-z_]+')->middleware('throttle:60,1,api-licence-kit')->name('kit');
        Route::post('results', 'result')->middleware('throttle:10,1,api-licence-result')->name('result');
    });
    // Switching to agent (A10.3c): the licence check, the switch, and a new agent's mentors.
    Route::prefix('licence-journey/licence')->name('api.licence.licence.')->controller(SwitchController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('check', 'check')->middleware('throttle:10,1,api-licence-check')->name('check');
        Route::post('switch', 'switch')->middleware('throttle:5,1,api-licence-switch')->name('switch');
    });
    Route::get('mentors', [SwitchController::class, 'mentors'])->name('api.mentors');
    // My listings (B10.1): MLS listings through the agent's confirmed member records.
    Route::prefix('listings')->name('api.listings.')->controller(ListingsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('members', 'confirm')->middleware('throttle:20,1,api-listings-members')->name('members.confirm');
        Route::post('members/decline', 'decline')->middleware('throttle:20,1,api-listings-members')->name('members.decline');
        Route::post('members/unlink', 'unlink')->middleware('throttle:20,1,api-listings-members')->name('members.unlink');
    });
    // A post about one of my listings (B10.2), within the MLS's licence terms.
    Route::get('listings/{listing}/post', [ListingPostController::class, 'show'])->whereUuid('listing')->name('api.listings.post.show');
    Route::put('listings/{listing}/post', [ListingPostController::class, 'update'])->whereUuid('listing')->middleware('throttle:30,1,api-listing-post')->name('api.listings.post.update');
    // CMAs for my listings and my public page (B10.2b).
    Route::get('listings/{listing}/cma', [CmaController::class, 'show'])->whereUuid('listing')->middleware('throttle:30,1,api-listing-cma')->name('api.listings.cma');
    Route::get('listings/page', [CmaController::class, 'page'])->name('api.listings.page');
    Route::put('listings/page', [CmaController::class, 'updatePage'])->middleware('throttle:20,1,api-listing-page')->name('api.listings.page.update');
    // After the exam (A10.3b): share the news, passes and congrats, and broker offers.
    Route::prefix('licence-journey')->name('api.licence.')->controller(PassesAndOffersController::class)->group(function () {
        Route::post('news', 'share')->middleware('throttle:5,1,api-licence-news')->name('news');
        Route::post('news/skip', 'skip')->middleware('throttle:10,1,api-licence-news')->name('news.skip');
        Route::get('passes', 'passes')->name('passes');
        Route::post('passes/{pass}/congrats', 'congrats')->whereNumber('pass')->middleware('throttle:20,1,api-licence-congrats')->name('passes.congrats');
        Route::get('offers', 'offers')->name('offers');
        Route::put('offers/opt-in', 'optIn')->middleware('throttle:20,1,api-licence-offers')->name('offers.opt-in');
        Route::post('offers/{offer}/accept', 'accept')->whereUuid('offer')->middleware('throttle:20,1,api-licence-offers')->name('offers.accept');
        Route::post('offers/{offer}/decline', 'decline')->whereUuid('offer')->middleware('throttle:20,1,api-licence-offers')->name('offers.decline');
    });
    // Candidate practice and the study plan (A8.3): the learner's own, not admin actions.
    Route::controller(PracticeController::class)->name('api.practice.')->group(function () {
        Route::get('me/study', 'today')->name('today');
        Route::get('practice/tests', 'index')->name('index');
        Route::post('practice/tests', 'store')->middleware('throttle:30,1,api-practice-start')->name('store');
        Route::get('practice/attempts/{attempt}', 'show')->whereNumber('attempt')->name('show');
        Route::put('practice/attempts/{attempt}/answers/{question}', 'answer')->whereNumber(['attempt', 'question'])->middleware('throttle:240,1,api-practice-answer')->name('answer');
        Route::post('practice/attempts/{attempt}/submit', 'submit')->whereNumber('attempt')->middleware('throttle:30,1,api-practice-submit')->name('submit');
        Route::get('practice/attempts/{attempt}/results', 'results')->whereNumber('attempt')->name('results');
        Route::post('practice/attempts/{attempt}/pause', 'pause')->whereNumber('attempt')->middleware('throttle:60,1,api-practice-pause')->name('pause');
        Route::post('practice/attempts/{attempt}/finish', 'finish')->whereNumber('attempt')->middleware('throttle:30,1,api-practice-finish')->name('finish');
        Route::get('practice/attempts/{attempt}/similar/{question}', 'similar')->whereNumber(['attempt', 'question'])->middleware('throttle:60,1,api-practice-similar')->name('similar');
        Route::get('practice/attempts/{attempt}/why/{question}', 'why')->whereNumber(['attempt', 'question'])->middleware('throttle:60,1,api-practice-why')->name('why');
    });
    // Readiness map and What you're good at (A9.2).
    Route::get('practice/readiness', [ReadinessController::class, 'show'])->name('api.practice.readiness');
    // The practice hub's ranking, the smart drill's Hint, and Fix a weak topic (A9.2b).
    Route::get('practice/hub', [CoachController::class, 'hub'])->name('api.practice.hub');
    Route::post('practice/attempts/{attempt}/questions/{question}/hint', [CoachController::class, 'hint'])->whereNumber(['attempt', 'question'])->middleware('throttle:120,1,api-practice-hint')->name('api.practice.hint');
    Route::post('practice/topic-plans', [TopicPlanController::class, 'store'])->middleware('throttle:30,1,api-practice-plans')->name('api.practice.plans.store');
    Route::get('practice/topic-plans/{plan}', [TopicPlanController::class, 'show'])->whereNumber('plan')->name('api.practice.plans.show');
    Route::post('practice/topic-plans/{plan}/continue', [TopicPlanController::class, 'continue'])->whereNumber('plan')->middleware('throttle:60,1,api-practice-plans')->name('api.practice.plans.continue');
    // Math lab (A9.2c).
    Route::get('practice/math', [MathLabController::class, 'index'])->name('api.practice.math.index');
    Route::post('practice/math/problems', [MathLabController::class, 'store'])->middleware('throttle:120,1,api-practice-math')->name('api.practice.math.store');
    Route::post('practice/math/check', [MathLabController::class, 'check'])->middleware('throttle:240,1,api-practice-math')->name('api.practice.math.check');
    // Challenges (A9.3b).
    Route::get('practice/challenges', [ChallengeController::class, 'index'])->name('api.practice.challenges.index');
    Route::post('practice/challenges', [ChallengeController::class, 'store'])->middleware('throttle:20,1,api-practice-challenges')->name('api.practice.challenges.store');
    Route::get('practice/challenges/{challenge}', [ChallengeController::class, 'show'])->whereUuid('challenge')->name('api.practice.challenges.show');
    Route::post('practice/challenges/{challenge}/accept', [ChallengeController::class, 'accept'])->whereUuid('challenge')->middleware('throttle:30,1,api-practice-challenges')->name('api.practice.challenges.accept');
    // Study groups (A9.3c).
    Route::get('practice/groups', [StudyGroupController::class, 'index'])->name('api.practice.groups.index');
    Route::put('practice/groups/opt-in', [StudyGroupController::class, 'optIn'])->middleware('throttle:20,1,api-practice-groups')->name('api.practice.groups.opt-in');
    Route::post('practice/groups', [StudyGroupController::class, 'store'])->middleware('throttle:10,1,api-practice-groups')->name('api.practice.groups.store');
    Route::get('practice/groups/{group}', [StudyGroupController::class, 'show'])->whereUuid('group')->name('api.practice.groups.show');
    Route::post('practice/groups/{group}/join', [StudyGroupController::class, 'join'])->whereUuid('group')->middleware('throttle:20,1,api-practice-groups')->name('api.practice.groups.join');
    Route::post('practice/groups/{group}/leave', [StudyGroupController::class, 'leave'])->whereUuid('group')->middleware('throttle:20,1,api-practice-groups')->name('api.practice.groups.leave');
    Route::post('practice/groups/{group}/challenge', [StudyGroupController::class, 'challenge'])->whereUuid('group')->middleware('throttle:20,1,api-practice-challenges')->name('api.practice.groups.challenge');
    // The weekly progress report and sharing it (A9.2e).
    Route::get('practice/reports/weekly', [ReportController::class, 'show'])->name('api.practice.reports.show');
    Route::post('practice/reports/weekly/share', [ReportController::class, 'share'])->middleware('throttle:20,1,api-practice-reports')->name('api.practice.reports.share');
    // Ask, the study mate (A10.1): conversations, answers from the person's courses first.
    Route::get('ask/threads', [AskController::class, 'index'])->name('api.ask.index');
    Route::get('ask/threads/{thread}', [AskController::class, 'show'])->whereUuid('thread')->name('api.ask.show');
    Route::post('ask', [AskController::class, 'store'])->middleware('throttle:20,1,api-ask')->name('api.ask.store');
    Route::get('ask/package', [AskController::class, 'package'])->name('api.ask.package');
    Route::post('ask/threads/{thread}/instructor', [AskController::class, 'instructor'])->whereUuid('thread')->middleware('throttle:10,1,api-ask-instructor')->name('api.ask.instructor');
    // Student questions for a course's instructors (A10.1b): scoped to the courses they teach.
    Route::get('instructor/questions', [InstructorQuestionController::class, 'index'])->name('api.instructor.questions.index');
    Route::get('instructor/questions/{question}', [InstructorQuestionController::class, 'show'])->whereUuid('question')->name('api.instructor.questions.show');
    Route::post('instructor/questions/{question}/answer', [InstructorQuestionController::class, 'answer'])->whereUuid('question')->middleware('throttle:60,1,api-instructor-answer')->name('api.instructor.questions.answer');
    // Flashcards and the speed round (A9.2d).
    Route::get('practice/flashcards', [FlashcardController::class, 'index'])->name('api.practice.flashcards.index');
    Route::post('practice/flashcards', [FlashcardController::class, 'store'])->middleware('throttle:60,1,api-practice-flashcards')->name('api.practice.flashcards.store');
    Route::post('practice/flashcards/reviews', [FlashcardController::class, 'reviews'])->middleware('throttle:120,1,api-practice-flashcards')->name('api.practice.flashcards.reviews');
    Route::post('practice/speed', [SpeedRoundController::class, 'store'])->middleware('throttle:30,1,api-practice-speed')->name('api.practice.speed.store');
    Route::post('practice/speed/{round}/finish', [SpeedRoundController::class, 'finish'])->whereNumber('round')->middleware('throttle:30,1,api-practice-speed')->name('api.practice.speed.finish');
    // Lessons saved on the phone (A8.2b): the learner's own downloads, not admin actions.
    Route::prefix('learn')->name('api.learn.downloads.')->controller(OfflineController::class)->group(function () {
        Route::get('downloads', 'index')->name('index');
        Route::post('lessons/{lesson}/downloads', 'store')->whereNumber('lesson')->middleware('throttle:20,1,api-learn-download')->name('store');
        Route::post('downloads/{grant}/renew', 'renew')->whereNumber('grant')->middleware('throttle:60,1,api-learn-download-renew')->name('renew');
        Route::delete('downloads/{grant}', 'destroy')->whereNumber('grant')->middleware('throttle:60,1,api-learn-download-remove')->name('destroy');
        Route::post('downloads/{grant}/seat-time', 'seatTime')->whereNumber('grant')->middleware('throttle:30,1,api-learn-offline-seat')->name('seat-time');
    });
    Route::put('me/states/current', [CurrentStateController::class, 'update'])->middleware('throttle:30,1,api-me-states')->name('api.me.states.update');

    // The Connections library (B3.2, ADR 0014).
    Route::get('catalogue', [CatalogueController::class, 'index'])->name('api.catalogue.index');
    Route::post('catalogue/{tool}/interest', [CatalogueController::class, 'interest'])->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,api-catalogue-interest')->name('api.catalogue.interest');
    Route::delete('catalogue/{tool}/interest', [CatalogueController::class, 'forget'])->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,api-catalogue-forget')->name('api.catalogue.forget');
    Route::post('tool-requests', [CatalogueController::class, 'request'])->middleware('throttle:10,60,api-tool-requests-store')->name('api.tool-requests.store');

    // Connections (B3.1, ADR 0013).
    // Report a question (A8.1).
    Route::post('questions/{question}/report', [QuestionReportController::class, 'store'])->whereNumber('question')->middleware('throttle:20,1,api-question-report')->name('api.questions.report');
    // Deals (B9.1, ADR 0037): the agent's own.
    Route::get('deals', [DealController::class, 'index'])->name('api.deals.index');
    Route::get('deals/pipeline', [DealController::class, 'pipeline'])->name('api.deals.pipeline');
    Route::get('deals/alerts', [DealController::class, 'alerts'])->name('api.deals.alerts');
    Route::get('deals/transactions', [DealController::class, 'transactions'])->name('api.deals.transactions');
    Route::post('deals', [DealController::class, 'store'])->middleware('throttle:30,1,api-deals')->name('api.deals.store');
    Route::get('deals/{deal}', [DealController::class, 'show'])->whereUuid('deal')->name('api.deals.show');
    Route::put('deals/{deal}', [DealController::class, 'update'])->whereUuid('deal')->middleware('throttle:60,1,api-deals')->name('api.deals.update');
    Route::put('deals/{deal}/stage', [DealController::class, 'stage'])->whereUuid('deal')->middleware('throttle:60,1,api-deals')->name('api.deals.stage');
    Route::delete('deals/{deal}', [DealController::class, 'destroy'])->whereUuid('deal')->middleware('throttle:30,1,api-deals')->name('api.deals.destroy');
    Route::post('deals/{deal}/files', [SignatureController::class, 'upload'])->whereUuid('deal')->middleware('throttle:30,1,api-deal-files')->name('api.deals.files.store');
    Route::post('deals/{deal}/signatures', [SignatureController::class, 'store'])->whereUuid('deal')->middleware('throttle:20,1,api-deal-signatures')->name('api.deals.signatures.store');
    Route::post('deals/signatures/{signature}/sender', [SignatureController::class, 'sender'])->whereUuid('signature')->middleware('throttle:20,1,api-deal-signatures')->name('api.deals.signatures.sender');
    // Contacts (B7.1, ADR 0027): one person, all sources.
    Route::get('contacts', [ContactController::class, 'index'])->name('api.contacts.index');
    Route::get('contacts/activity', [ContactController::class, 'activity'])->name('api.contacts.activity');
    Route::post('contacts/phone', [ContactController::class, 'phone'])->middleware('throttle:20,1,api-contacts-phone')->name('api.contacts.phone');
    Route::delete('contacts/phone', [ContactController::class, 'forgetPhone'])->middleware('throttle:10,1,api-contacts-phone-forget')->name('api.contacts.phone.forget');
    Route::get('contacts/{contact}', [ContactController::class, 'show'])->whereUuid('contact')->name('api.contacts.show');
    Route::patch('contacts/{contact}', [ContactController::class, 'update'])->whereUuid('contact')->middleware('throttle:60,1,api-contacts-update')->name('api.contacts.update');
    Route::post('contacts/{contact}/notes', [ContactController::class, 'note'])->whereUuid('contact')->middleware('throttle:60,1,api-contacts-notes')->name('api.contacts.notes.store');
    Route::post('contacts/{contact}/merge', [ContactController::class, 'merge'])->whereUuid('contact')->middleware('throttle:30,1,api-contacts-merge')->name('api.contacts.merge');
    Route::post('contacts/{contact}/split', [ContactController::class, 'split'])->whereUuid('contact')->middleware('throttle:30,1,api-contacts-merge')->name('api.contacts.split');
    // CRM hub (B8.3, ADR 0034): AI next step, tasks to the CRM, calls and texts logged.
    Route::post('contacts', [ContactWorkController::class, 'store'])->middleware('throttle:30,1,api-contacts-store')->name('api.contacts.store');
    Route::get('contacts/crms', [ContactWorkController::class, 'crms'])->name('api.contacts.crms');
    Route::put('contacts/crms/default', [ContactWorkController::class, 'defaultCrm'])->middleware('throttle:30,1,api-contacts-default')->name('api.contacts.crms.default');
    Route::get('contacts/today', [ContactWorkController::class, 'today'])->middleware('throttle:30,1,api-contacts-today')->name('api.contacts.today');
    Route::post('contacts/{contact}/insight', [ContactWorkController::class, 'insight'])->whereUuid('contact')->middleware('throttle:30,1,api-contacts-insight')->name('api.contacts.insight');
    Route::post('contacts/{contact}/tasks', [ContactWorkController::class, 'task'])->whereUuid('contact')->middleware('throttle:60,1,api-contacts-tasks')->name('api.contacts.tasks.store');
    Route::post('contacts/{contact}/touches', [ContactWorkController::class, 'touch'])->whereUuid('contact')->middleware('throttle:60,1,api-contacts-touches')->name('api.contacts.touches.store');
    Route::post('contacts/suggestions/{suggestion}/dismiss', [ContactController::class, 'dismiss'])->whereNumber('suggestion')->middleware('throttle:60,1,api-contacts-dismiss')->name('api.contacts.suggestions.dismiss');
    Route::get('connections', [ConnectionController::class, 'index'])->name('api.connections.index');
    Route::post('connections', [ConnectionController::class, 'store'])->middleware('throttle:sign-in')->name('api.connections.store');
    Route::post('connections/{connection}/sync', [ConnectionController::class, 'sync'])->whereNumber('connection')->middleware('throttle:10,1,api-connections-sync')->name('api.connections.sync');
    Route::patch('connections/{connection}', [ConnectionController::class, 'update'])->whereNumber('connection')->middleware('throttle:30,1,api-connections-update')->name('api.connections.update');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->whereNumber('connection')->middleware('throttle:30,1,api-connections-destroy')->name('api.connections.destroy');

    // To-dos and voice capture (B4.3, ADR 0018).
    Route::prefix('todos')->name('api.todos.')->controller(TodoController::class)->group(function () {
        Route::get('/', 'index')->middleware('throttle:120,1,api-todos-index')->name('index');
        Route::post('/', 'store')->middleware('throttle:60,1,api-todos-store')->name('store');
        Route::patch('{todo}', 'update')->whereNumber('todo')->middleware('throttle:60,1,api-todos-update')->name('update');
        Route::post('{todo}/done', 'done')->whereNumber('todo')->middleware('throttle:60,1,api-todos-done')->name('done');
        Route::delete('{todo}/done', 'undone')->whereNumber('todo')->middleware('throttle:60,1,api-todos-undone')->name('undone');
        Route::delete('{todo}', 'destroy')->whereNumber('todo')->middleware('throttle:60,1,api-todos-destroy')->name('destroy');
        Route::post('{todo}/restore', 'restore')->whereNumber('todo')->middleware('throttle:60,1,api-todos-restore')->name('restore');
    });
    Route::post('voice/parse', [VoiceController::class, 'parse'])->middleware('throttle:30,1,api-voice-parse')->name('api.voice.parse');

    // Your REOS calendar, two-way with your calendar tools (B4.1, ADR 0017).
    Route::prefix('calendar')->name('api.calendar.')->controller(CalendarController::class)->group(function () {
        Route::get('events', 'index')->middleware('throttle:120,1,api-calendar-index')->name('index');
        Route::post('events', 'store')->middleware('throttle:60,1,api-calendar-store')->name('store');
        Route::put('events/{event}', 'update')->whereNumber('event')->middleware('throttle:60,1,api-calendar-update')->name('update');
        Route::delete('events/{event}', 'destroy')->whereNumber('event')->middleware('throttle:60,1,api-calendar-destroy')->name('destroy');
        Route::post('events/{event}/restore', 'restore')->whereNumber('event')->middleware('throttle:60,1,api-calendar-restore')->name('restore');
        Route::post('events/{event}/retry', 'retry')->whereNumber('event')->middleware('throttle:20,1,api-calendar-retry')->name('retry');
        Route::post('conflicts/{conflict}/undo', 'undo')->whereNumber('conflict')->middleware('throttle:30,1,api-calendar-undo')->name('undo');
    });
});
