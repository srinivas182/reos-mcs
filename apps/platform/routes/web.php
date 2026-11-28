<?php

use App\Connections\ConnectorRegistry;
use App\Http\Controllers\Agents\AgentSetupPageController;
use App\Http\Controllers\Api\Agents\BriefController;
use App\Http\Controllers\Api\Agents\ModeController;
use App\Http\Controllers\Api\Agents\TodayController;
use App\Http\Controllers\Api\Ask\AskController;
use App\Http\Controllers\Api\Bookings\BookingController;
use App\Http\Controllers\Api\Brokerage\BrokerVerificationController;
use App\Http\Controllers\Api\Brokerage\PoliciesController;
use App\Http\Controllers\Api\Brokerage\RosterController;
use App\Http\Controllers\Api\Brokerage\SeatsController;
use App\Http\Controllers\Api\Brokerage\StaffController;
use App\Http\Controllers\Api\Ce\CeWalletController;
use App\Http\Controllers\Api\Classes\ClassesController;
use App\Http\Controllers\Api\Compliance\ComplianceController;
use App\Http\Controllers\Api\Contracts\ContractReviewController;
use App\Http\Controllers\Api\Deals\ClientPortalController;
use App\Http\Controllers\Api\Deals\DealController;
use App\Http\Controllers\Api\Deals\SignatureController;
use App\Http\Controllers\Api\Instructor\QuestionController as InstructorQuestionController;
use App\Http\Controllers\Api\Leads\FarmController;
use App\Http\Controllers\Api\Leads\OpenHouseController;
use App\Http\Controllers\Api\Leads\ReferralController;
use App\Http\Controllers\Api\Learn\PlayerController;
use App\Http\Controllers\Api\Learn\QuestionReportController;
use App\Http\Controllers\Api\Licence\LicenceJourneyController;
use App\Http\Controllers\Api\Licence\PassesAndOffersController;
use App\Http\Controllers\Api\Licence\SwitchController;
use App\Http\Controllers\Api\Listings\CmaController;
use App\Http\Controllers\Api\Listings\ListingPostController;
use App\Http\Controllers\Api\Listings\ListingsController;
use App\Http\Controllers\Api\Mentoring\MentorWorkspaceController;
use App\Http\Controllers\Api\Practice\ChallengeController;
use App\Http\Controllers\Api\Practice\CoachController;
use App\Http\Controllers\Api\Practice\FlashcardController;
use App\Http\Controllers\Api\Practice\MathLabController;
use App\Http\Controllers\Api\Practice\PracticeController as ApiPracticeController;
use App\Http\Controllers\Api\Practice\ReportController;
use App\Http\Controllers\Api\Practice\SpeedRoundController;
use App\Http\Controllers\Api\Practice\StudyGroupController;
use App\Http\Controllers\Api\Social\ContentCalendarController;
use App\Http\Controllers\Api\Social\SocialController;
use App\Http\Controllers\Api\Studio\CandidateStudioController;
use App\Http\Controllers\Api\Studio\StudioController;
use App\Http\Controllers\Api\Studio\VideoController;
use App\Http\Controllers\Api\Todos\TodoController;
use App\Http\Controllers\Api\VirtualBroker\VirtualBrokerController;
use App\Http\Controllers\Ask\AskPageController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\PhoneSignInController;
use App\Http\Controllers\Auth\SocialSignInController;
use App\Http\Controllers\Auth\SsoSignInController;
use App\Http\Controllers\Auth\TestIdentityProviderController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Bookings\BookingsPageController;
use App\Http\Controllers\BrandLogoController;
use App\Http\Controllers\Builder\CourseBuilderController;
use App\Http\Controllers\Builder\LessonReviewController;
use App\Http\Controllers\Builder\QuestionBankController;
use App\Http\Controllers\Builder\StarterBankController;
use App\Http\Controllers\Calendar\CalendarPageController;
use App\Http\Controllers\Ce\CeWalletPageController;
use App\Http\Controllers\Classes\ClassesPageController;
use App\Http\Controllers\Compliance\ComplianceLogController;
use App\Http\Controllers\Connections\ConnectController;
use App\Http\Controllers\Connections\ConnectionsPageController;
use App\Http\Controllers\Connections\DocuSignSimulatorController;
use App\Http\Controllers\Connections\OAuthCallbackController;
use App\Http\Controllers\Connections\SimulatorSignInController;
use App\Http\Controllers\Contracts\ContractsPageController;
use App\Http\Controllers\Crm\ContactImportsController;
use App\Http\Controllers\Crm\ContactsPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Deals\DealsPageController;
use App\Http\Controllers\Deals\SignaturePageController;
use App\Http\Controllers\Feedback\AgentFeedbackPageController;
use App\Http\Controllers\Instructor\QuestionsPageController;
use App\Http\Controllers\Leads\FarmPageController;
use App\Http\Controllers\Leads\FarmUnsubscribeController;
use App\Http\Controllers\Leads\JoinController;
use App\Http\Controllers\Leads\OpenHousePageController;
use App\Http\Controllers\Leads\PublicLeadsController;
use App\Http\Controllers\Leads\ReferralPageController;
use App\Http\Controllers\Learn\LearnController;
use App\Http\Controllers\Learn\LessonMediaController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Licence\LicenceJourneyPageController;
use App\Http\Controllers\Licence\PassesAndOffersPageController;
use App\Http\Controllers\Listings\AgentPublicPageController;
use App\Http\Controllers\Listings\ListingsPageController;
use App\Http\Controllers\Listings\SitemapController;
use App\Http\Controllers\Mentoring\ChallengesController;
use App\Http\Controllers\Mentoring\MentorProgramController;
use App\Http\Controllers\Mentoring\MentorWorkspacePageController;
use App\Http\Controllers\Mentors\MentorsPageController;
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
use App\Http\Controllers\Organisation\BrokerageIntegrationsController;
use App\Http\Controllers\Organisation\DomainController;
use App\Http\Controllers\Organisation\InvitationController;
use App\Http\Controllers\Organisation\PoliciesPageController;
use App\Http\Controllers\Organisation\SettingsController;
use App\Http\Controllers\Organisation\SsoController;
use App\Http\Controllers\Payouts\EarningsController;
use App\Http\Controllers\Payouts\SalesController;
use App\Http\Controllers\Payouts\SimulatorController as PayoutSimulatorController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Practice\ChallengePageController;
use App\Http\Controllers\Practice\PracticePageController;
use App\Http\Controllers\Practice\SharedReportController;
use App\Http\Controllers\Practice\StudyPlanController;
use App\Http\Controllers\Practice\TopicPlanPageController;
use App\Http\Controllers\Recruiting\RecruitingController;
use App\Http\Controllers\Rewards\RewardsPageController;
use App\Http\Controllers\RolePlay\RolePlayPageController;
use App\Http\Controllers\School\ApprovalsController as SchoolApprovalsController;
use App\Http\Controllers\School\CeReportsController;
use App\Http\Controllers\School\InstructorsController as SchoolInstructorsController;
use App\Http\Controllers\School\StudentsController as SchoolStudentsController;
use App\Http\Controllers\Sessions\SessionsController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Social\StudioCalendarPageController;
use App\Http\Controllers\Social\StudioPageController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\Studio\AdvertisingController;
use App\Http\Controllers\Studio\CandidateStudioPageController;
use App\Http\Controllers\SupportConsentController;
use App\Http\Controllers\SupportSessionController;
use App\Http\Controllers\VideoPractice\VideoPracticePageController;
use App\Http\Controllers\VirtualBroker\BrokerVirtualBrokerController;
use App\Http\Controllers\VirtualBroker\VirtualBrokerPageController;
use App\Http\Controllers\WhatsNewController;
use App\Http\Middleware\EnsureAdminTwoStep;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RequireFlag;
use App\Services\Tenancy\TenantSignups;
use Illuminate\Support\Facades\Route;

// A social post's media, for the channels to fetch (signed links, B11).
Route::get('social/media/{path}', [SocialController::class, 'file'])->where('path', 'social/[0-9]+/(studio/)?[A-Za-z0-9\-]+\.[a-z0-9]{2,5}')->middleware('throttle:120,1,social-media')->name('social.media');

// The public site (web journey screen 1) and each audience's landing page.
Route::get('/', [PublicSiteController::class, 'home'])->name('home');
// Brokerages and schools sign up and pay on the web (A6.1b, the Sprint 6 gate).
// Checking a brokerage rule's words against TREC as they're typed (A15.2): changes nothing.
Route::post('organisation/policies/check', [PoliciesController::class, 'check'])->middleware(['auth', 'verified', 'throttle:60,1,policy-check'])->name('policies.check');
// The designated broker's TREC check comes first (A15.1a, broker journey screen 2).
Route::middleware(['auth', 'verified', 'throttle:20,1,broker-verify'])->prefix('start/brokerage/verify')->name('start.verify.')->group(function () {
    Route::post('lookup', [BrokerVerificationController::class, 'lookup'])->name('lookup');
    Route::post('confirm', [BrokerVerificationController::class, 'confirm'])->name('confirm');
});
Route::middleware(['auth', 'verified'])->prefix('start/{kind}')->whereIn('kind', array_keys(TenantSignups::TYPES))->name('start.')->group(function () {
    Route::get('/', [TenantSignupController::class, 'show'])->name('show');
    Route::post('/', [TenantSignupController::class, 'store'])->middleware('throttle:10,1,tenant-signup')->name('store');
});
// The public status page (A6.1).
// The terms and the privacy notice people agree to (A6.3).
// A weekly progress report shared with a study buddy (A9.2e): first name only, for 14 days.
// A study group's invite link (A9.3c): open to anyone; sign in to join.
Route::get('groups/{group}', [ChallengePageController::class, 'group'])->whereUuid('group')->middleware('throttle:60,1,groups')->name('groups.show');
// A challenge's share link (A9.3b): open to anyone; sign in to accept.
Route::get('challenges/{challenge}', [ChallengePageController::class, 'show'])->whereUuid('challenge')->middleware('throttle:60,1,challenges')->name('challenges.show');
// An agent's public page (B10.2b): their MLS listings with each MLS's rules applied.
Route::get('agent/{handle}', [AgentPublicPageController::class, 'show'])->where('handle', '(?!(?:setup|mode|plan|brief)$)[a-z0-9-]{1,80}')->middleware('throttle:60,1,agent-pages')->name('agent-pages.show');
// The digital card (B14.1): the card page, the contact file and the QR code.
Route::middleware('throttle:60,1,agent-pages')->group(function () {
    Route::get('agent/{handle}/card', [AgentPublicPageController::class, 'card'])->where('handle', '[a-z0-9-]{1,80}')->name('agent-pages.card');
    Route::get('agent/{handle}/contact.vcf', [AgentPublicPageController::class, 'vcard'])->where('handle', '[a-z0-9-]{1,80}')->name('agent-pages.vcard');
    Route::get('agent/{handle}/qr.svg', [AgentPublicPageController::class, 'qr'])->where('handle', '[a-z0-9-]{1,80}')->name('agent-pages.qr');
});
// The client portal (B14.2): one private link per client, no account.
Route::middleware('throttle:60,1,portal')->group(function () {
    Route::get('portal/{token}', [PortalController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('portal.show');
    Route::post('portal/{token}/sign/{signature}', [PortalController::class, 'sign'])->where('token', '[A-Za-z0-9]{48}')->whereUuid('signature')->middleware('throttle:10,1,portal-sign')->name('portal.sign');
    Route::post('portal/{token}/review', [PortalController::class, 'review'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:5,1,portal-review')->name('portal.review');
});
// DocuSign on demo data: the client's signing view (only reachable through its signed link).
Route::middleware(['signed', 'throttle:30,1,portal-simulator'])->group(function () {
    Route::get('portal-simulator/sign', [PortalController::class, 'simulator'])->name('portal.simulator.sign');
    Route::post('portal-simulator/sign', [PortalController::class, 'simulatorSign'])->name('portal.simulator.finish');
});
// Lead capture without an account (B14.3a): open house sign-in and the question form on the agent's page.
Route::middleware('throttle:60,1,open-house')->group(function () {
    Route::get('open-house/{code}', [PublicLeadsController::class, 'openHouse'])->where('code', '[A-Za-z2-9]{8}')->name('open-house.show');
    Route::get('open-house/{code}/qr.svg', [PublicLeadsController::class, 'qr'])->where('code', '[A-Za-z2-9]{8}')->name('open-house.qr');
    Route::post('open-house/{code}', [PublicLeadsController::class, 'signIn'])->where('code', '[A-Za-z2-9]{8}')->middleware('throttle:20,1,open-house-sign-in')->name('open-house.sign-in');
});
Route::post('agent/{handle}/contact', [PublicLeadsController::class, 'contact'])->where('handle', '[a-z0-9-]{1,80}')->middleware('throttle:5,1,agent-page-lead')->name('agent-pages.contact');
// Market updates (B14.3c): the signed unsubscribe link in every email.
Route::middleware(['signed', 'throttle:30,1,farm-unsubscribe'])->group(function () {
    Route::get('farm/unsubscribe', [FarmUnsubscribeController::class, 'show'])->name('farm.unsubscribe');
    Route::post('farm/unsubscribe', [FarmUnsubscribeController::class, 'store'])->name('farm.unsubscribe.store');
});
// Invite and earn (B14.3b): the invite link remembers who invited a new person.
Route::get('join/{code}', [JoinController::class, 'show'])->where('code', '[A-Za-z2-9]{8}')->middleware('throttle:30,1,join')->name('join.show');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->middleware('throttle:30,1,sitemap')->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->middleware('throttle:30,1,sitemap')->name('robots');
Route::get('reports/{share}', [SharedReportController::class, 'show'])->whereUuid('share')->middleware('throttle:60,1,shared-reports')->name('reports.shared');
Route::get('terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('status', StatusController::class)->middleware('throttle:60,1,status')->name('status');
// Packages and prices for candidates, with "Have a code?" (A8.3b).
Route::get('packages/{code?}', [PublicSiteController::class, 'packages'])->where('code', '[A-Za-z]{2}')->middleware('throttle:60,1,packages')->name('packages');
Route::post('packages/code', [PublicSiteController::class, 'code'])->middleware('throttle:10,10,packages-code')->name('packages.code');
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

    // Deals board (B9.1, agent web screen 14): the agent's own deals.
    Route::get('deals', [DealsPageController::class, 'index'])->name('deals.index');
    Route::get('connections/transactions', [DealsPageController::class, 'transactions'])->name('connections.transactions');
    // The agent's welcome, goal and flags (A11.1a, screens 4–6); the mode calls are the API's.
    Route::get('agent/setup', [AgentSetupPageController::class, 'setup'])->name('agent.setup');
    Route::put('agent/mode', [ModeController::class, 'goal'])->middleware('throttle:30,1,agent-mode')->name('agent.mode.goal');
    Route::put('agent/mode/flags', [ModeController::class, 'flags'])->middleware('throttle:30,1,agent-mode')->name('agent.mode.flags');
    Route::post('agent/mode/snooze', [ModeController::class, 'snooze'])->middleware('throttle:10,1,agent-mode')->name('agent.mode.snooze');
    // The 90-day plan and weekly scorecard (A11.1b, screens 10 and 110).
    Route::get('agent/plan', [AgentSetupPageController::class, 'plan'])->name('agent.plan');
    Route::put('agent/plan/tasks/{code}', [TodayController::class, 'tick'])->where('code', '[a-z_]+')->middleware('throttle:60,1,agent-plan')->name('agent.plan.tick');
    // The virtual broker (A12.1, agent web screen 16): ask, see sources and status; the same JSON actions as the API.
    Route::get('virtual-broker', VirtualBrokerPageController::class)->name('virtual-broker.index');
    Route::prefix('virtual-broker')->name('virtual-broker.')->controller(VirtualBrokerController::class)->group(function () {
        Route::post('questions', 'store')->middleware('throttle:20,1,virtual-broker-ask')->name('questions.store');
        Route::post('questions/{question}/escalate', 'escalate')->whereUuid('question')->middleware('throttle:20,1,virtual-broker-escalate')->name('questions.escalate');
    });
    // Contract review (A12.2, agent web screen 15): reviews, one result with the contract, and the same JSON actions as the API.
    Route::get('contracts', [ContractsPageController::class, 'index'])->name('contracts.index');
    Route::get('contracts/{review}', [ContractsPageController::class, 'show'])->whereUuid('review')->name('contracts.show');
    Route::get('contracts/{review}/file', [ContractsPageController::class, 'file'])->whereUuid('review')->middleware('throttle:60,1,contracts-file')->name('contracts.file');
    Route::prefix('contracts')->name('contracts.')->controller(ContractReviewController::class)->group(function () {
        Route::post('/', 'store')->middleware('throttle:10,1,contracts-review')->name('store');
        Route::post('{review}/deadlines', 'deadlines')->whereUuid('review')->middleware('throttle:20,1,contracts-actions')->name('deadlines');
        Route::post('{review}/broker', 'broker')->whereUuid('review')->middleware('throttle:20,1,contracts-actions')->name('broker');
        Route::post('{review}/questions', 'ask')->whereUuid('review')->middleware('throttle:30,1,contracts-ask')->name('questions');
    });
    // Content studio (B11, web screen 17): compose, check, publish or schedule, and results.
    Route::get('studio', StudioPageController::class)->name('studio.index');
    // The content calendar (B12.3): week and month; move, reuse, plan.
    Route::get('studio/calendar', StudioCalendarPageController::class)->name('studio.calendar');
    Route::prefix('studio/posts')->name('studio.posts.')->controller(ContentCalendarController::class)->group(function () {
        Route::post('{post}/move', 'move')->whereUuid('post')->middleware('throttle:60,1,studio-calendar')->name('move');
        Route::post('{post}/reuse', 'reuse')->whereUuid('post')->middleware('throttle:30,1,studio-calendar')->name('reuse');
    });
    Route::prefix('studio')->name('studio.')->controller(SocialController::class)->group(function () {
        Route::post('accounts/{connection}/refresh', 'refresh')->whereNumber('connection')->middleware('throttle:10,1,studio-refresh')->name('accounts.refresh');
        Route::post('posts/check', 'check')->middleware('throttle:60,1,studio-check')->name('posts.check');
        Route::post('posts/fix', 'fix')->middleware('throttle:60,1,studio-check')->name('posts.fix');
        Route::post('posts', 'store')->middleware('throttle:30,1,studio-posts')->name('posts.store');
        Route::put('posts/{post}', 'update')->whereUuid('post')->middleware('throttle:30,1,studio-posts')->name('posts.update');
        Route::delete('posts/{post}', 'destroy')->whereUuid('post')->middleware('throttle:30,1,studio-posts')->name('posts.destroy');
        Route::post('posts/{post}/restore', 'restore')->whereUuid('post')->middleware('throttle:30,1,studio-posts')->name('posts.restore');
        Route::post('posts/{post}/repost', 'repost')->whereUuid('post')->middleware('throttle:30,1,studio-posts')->name('posts.repost');
        Route::post('media', 'media')->middleware('throttle:20,1,studio-media')->name('media');
    });
    // Make content (B12.1, screens 165 and 166): the same JSON actions as the API.
    Route::prefix('studio/videos')->name('studio.videos.')->controller(VideoController::class)->group(function () {
        Route::post('/', 'store')->middleware('throttle:6,1,studio-video')->name('store');
        Route::get('{video}', 'show')->whereUuid('video')->name('show');
    });
    Route::prefix('studio/generations')->name('studio.generations.')->controller(StudioController::class)->group(function () {
        Route::post('/', 'make')->middleware('throttle:10,1,studio-make')->name('make');
        Route::post('{generation}/captions', 'retone')->whereUuid('generation')->middleware('throttle:30,1,studio-retone')->name('retone');
    });
    // The CE wallet and renewal checklist (A11.2, screens 103, 105, 106).
    Route::get('ce', CeWalletPageController::class)->name('ce.index');
    Route::get('ce/completions/{completion}/certificate', [CeWalletPageController::class, 'certificate'])->whereNumber('completion')->name('ce.certificate');
    Route::put('ce/licences/{licence}/renewal', [CeWalletController::class, 'renewal'])->whereNumber('licence')->middleware('throttle:20,1,ce-renewal')->name('ce.renewal');
    // The morning brief: transcript, read aloud, settings (A11.1c, screen 184).
    Route::get('agent/brief', [AgentSetupPageController::class, 'brief'])->name('agent.brief');
    Route::put('agent/brief/settings', [BriefController::class, 'update'])->middleware('throttle:20,1,agent-brief')->name('agent.brief.settings');
    // Today's to-dos done from the dashboard, and Undo (A11.1b).
    Route::post('today/todos/{todo}/done', [TodoController::class, 'done'])->whereNumber('todo')->middleware('throttle:60,1,today-todos')->name('today.todos.done');
    Route::delete('today/todos/{todo}/done', [TodoController::class, 'undone'])->whereNumber('todo')->middleware('throttle:60,1,today-todos')->name('today.todos.undone');
    Route::post('agent/plan/promise', [TodayController::class, 'promise'])->middleware('throttle:10,1,agent-plan')->name('agent.plan.promise');
    // My listings (B10.1): beside deals (under Deals in the sidebar); MLS member records confirmed by the agent.
    Route::get('deals/listings', [ListingsPageController::class, 'index'])->name('deals.listings.index');
    Route::post('deals/listings/members', [ListingsController::class, 'confirm'])->middleware('throttle:20,1,listings-members')->name('deals.listings.members.confirm');
    Route::post('deals/listings/members/decline', [ListingsController::class, 'decline'])->middleware('throttle:20,1,listings-members')->name('deals.listings.members.decline');
    Route::post('deals/listings/members/unlink', [ListingsController::class, 'unlink'])->middleware('throttle:20,1,listings-members')->name('deals.listings.members.unlink');
    Route::get('deals/listings/{listing}/post', [ListingPostController::class, 'show'])->whereUuid('listing')->name('deals.listings.post.show');
    Route::put('deals/listings/{listing}/post', [ListingPostController::class, 'update'])->whereUuid('listing')->middleware('throttle:30,1,listing-post')->name('deals.listings.post.update');
    Route::get('deals/listings/{listing}/cma', [ListingsPageController::class, 'cma'])->whereUuid('listing')->middleware('throttle:30,1,listing-cma')->name('deals.listings.cma');
    Route::put('deals/listings/page', [CmaController::class, 'updatePage'])->middleware('throttle:20,1,listing-page')->name('deals.listings.page.update');
    Route::post('deals', [DealController::class, 'store'])->middleware('throttle:30,1,deals')->name('deals.store');
    Route::put('deals/{deal}', [DealController::class, 'update'])->whereUuid('deal')->middleware('throttle:60,1,deals')->name('deals.update');
    // Open houses (B14.3a): sign-in into the CRM; the page, and the same API for its actions.
    Route::get('open-houses', [OpenHousePageController::class, 'index'])->name('open-houses.index');
    Route::post('open-houses', [OpenHouseController::class, 'store'])->middleware('throttle:20,1,open-houses')->name('open-houses.store');
    Route::get('open-houses/{openHouse}/visits', [OpenHouseController::class, 'visits'])->whereUuid('openHouse')->name('open-houses.visits');
    // Referrals and invites (B14.3b): refer a client to another agent; the page, and the same API for its actions.
    Route::get('referrals', [ReferralPageController::class, 'index'])->name('referrals.index');
    Route::post('referrals', [ReferralController::class, 'store'])->middleware('throttle:20,1,referrals')->name('referrals.store');
    Route::middleware('throttle:30,1,referral-answers')->group(function () {
        Route::post('referrals/{referral}/accept', [ReferralController::class, 'accept'])->whereUuid('referral')->name('referrals.accept');
        Route::post('referrals/{referral}/decline', [ReferralController::class, 'decline'])->whereUuid('referral')->name('referrals.decline');
        Route::post('referrals/{referral}/close', [ReferralController::class, 'close'])->whereUuid('referral')->name('referrals.close');
    });
    // Farming market updates (B14.3c): the page, and the same API for its actions.
    Route::get('farming', [FarmPageController::class, 'index'])->name('farming.index');
    Route::post('farming', [FarmController::class, 'store'])->middleware('throttle:20,1,farming')->name('farming.store');
    Route::delete('farming/{area}', [FarmController::class, 'destroy'])->whereUuid('area')->name('farming.destroy');
    Route::get('farming/{area}/draft', [FarmController::class, 'draft'])->whereUuid('area')->middleware('throttle:30,1,farming-draft')->name('farming.draft');
    Route::post('farming/{area}/send', [FarmController::class, 'send'])->whereUuid('area')->middleware('throttle:5,1,farming-send')->name('farming.send');
    // The client portal (B14.2): the deal panel shares the deal with a client.
    Route::get('deals/{deal}/portals', [ClientPortalController::class, 'index'])->whereUuid('deal')->name('deals.portals.index');
    Route::post('deals/{deal}/portals', [ClientPortalController::class, 'store'])->whereUuid('deal')->middleware('throttle:30,1,portals')->name('deals.portals.store');
    Route::post('deals/portals/{portal}/send', [ClientPortalController::class, 'send'])->whereUuid('portal')->middleware('throttle:10,1,portals-send')->name('deals.portals.send');
    Route::delete('deals/portals/{portal}', [ClientPortalController::class, 'destroy'])->whereUuid('portal')->middleware('throttle:30,1,portals')->name('deals.portals.destroy');
    Route::put('deals/{deal}/stage', [DealController::class, 'stage'])->whereUuid('deal')->middleware('throttle:60,1,deals')->name('deals.stage');
    Route::delete('deals/{deal}', [DealController::class, 'destroy'])->whereUuid('deal')->middleware('throttle:30,1,deals')->name('deals.destroy');
    // Signatures from a deal (B9.3): files, envelopes, and back from DocuSign's sender view.
    Route::post('deals/{deal}/files', [SignatureController::class, 'upload'])->whereUuid('deal')->middleware('throttle:30,1,deal-files')->name('deals.files.store');
    Route::post('deals/{deal}/signatures', [SignatureController::class, 'store'])->whereUuid('deal')->middleware('throttle:20,1,deal-signatures')->name('deals.signatures.store');
    Route::post('deals/signatures/{signature}/sender', [SignatureController::class, 'sender'])->whereUuid('signature')->middleware('throttle:20,1,deal-signatures')->name('deals.signatures.sender');
    Route::get('deals/signatures/{signature}/returned', [SignaturePageController::class, 'returned'])->whereUuid('signature')->name('deals.signatures.returned');

    // Contacts, the CRM hub (B8.3, agent web screen 13): the agent's own contacts only.
    Route::controller(ContactsPageController::class)->prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'add')->middleware('throttle:30,1,contacts-add')->name('store');
        Route::put('default-crm', 'defaultCrm')->middleware('throttle:30,1,contacts-default')->name('default-crm');
        Route::post('{contact}/notes', 'note')->whereUuid('contact')->middleware('throttle:60,1,contacts-notes')->name('notes.store');
        Route::post('{contact}/tasks', 'task')->whereUuid('contact')->middleware('throttle:60,1,contacts-tasks')->name('tasks.store');
        Route::post('{contact}/insight', 'insight')->whereUuid('contact')->middleware('throttle:30,1,contacts-insight')->name('insight');
        Route::post('{contact}/touches', 'touch')->whereUuid('contact')->middleware('throttle:60,1,contacts-touches')->name('touches.store');
    });

    // CSV import, the last resort (B8.3b).
    Route::controller(ContactImportsController::class)->prefix('contacts/import')->name('contacts.import.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'upload')->middleware('throttle:10,1,contacts-import-upload')->name('upload');
        Route::put('{import}', 'preview')->whereUuid('import')->middleware('throttle:30,1,contacts-import-preview')->name('preview');
        Route::post('{import}', 'import')->whereUuid('import')->middleware('throttle:10,1,contacts-import-run')->name('run');
        Route::delete('{import}', 'undo')->whereUuid('import')->middleware('throttle:10,1,contacts-import-undo')->name('undo');
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

    // Your mentors (A10.3c, f04): the new agent's own brokerage's mentors.
    Route::get('mentors', [MentorsPageController::class, 'mentors'])->name('mentors.index');
    // The licence journey (A10.3a, screens 16, 15, 14 and 19): the learner's own pages and JSON actions (studying is checked in the controller).
    Route::prefix('licence-journey')->name('licence.')->group(function () {
        Route::get('/', [LicenceJourneyPageController::class, 'show'])->name('show');
        Route::get('exam-day', [LicenceJourneyPageController::class, 'examDay'])->name('exam-day');
        Route::get('result', [LicenceJourneyPageController::class, 'result'])->name('result');
        Route::controller(LicenceJourneyController::class)->group(function () {
            Route::put('steps/{code}', 'step')->where('code', '[a-z0-9_]+')->middleware('throttle:60,1,licence-step')->name('step');
            Route::put('exam', 'exam')->middleware('throttle:20,1,licence-exam')->name('exam');
            Route::put('kit/{item}', 'kit')->where('item', '[a-z_]+')->middleware('throttle:60,1,licence-kit')->name('kit');
            Route::post('results', 'result')->middleware('throttle:10,1,licence-result')->name('results');
        });
        // Switching to agent (A10.3c, f03 and 20).
        Route::get('licence', [MentorsPageController::class, 'licence'])->name('licence');
        Route::post('licence/check', [SwitchController::class, 'check'])->middleware('throttle:10,1,licence-check')->name('licence.check');
        Route::post('licence/switch', [SwitchController::class, 'switch'])->middleware('throttle:5,1,licence-switch')->name('licence.switch');
        // After the exam (A10.3b): passes (f06) and broker offers (17), and the same JSON actions as the API.
        Route::get('passes', [PassesAndOffersPageController::class, 'passes'])->name('passes');
        Route::get('offers', [PassesAndOffersPageController::class, 'offers'])->name('offers');
        Route::controller(PassesAndOffersController::class)->group(function () {
            Route::post('news', 'share')->middleware('throttle:5,1,licence-news')->name('news');
            Route::post('news/skip', 'skip')->middleware('throttle:10,1,licence-news')->name('news.skip');
            Route::post('passes/{pass}/congrats', 'congrats')->whereNumber('pass')->middleware('throttle:20,1,licence-congrats')->name('passes.congrats');
            Route::put('offers/opt-in', 'optIn')->middleware('throttle:20,1,licence-offers')->name('offers.opt-in');
            Route::post('offers/{offer}/accept', 'accept')->whereUuid('offer')->middleware('throttle:20,1,licence-offers')->name('offers.accept');
            Route::post('offers/{offer}/decline', 'decline')->whereUuid('offer')->middleware('throttle:20,1,licence-offers')->name('offers.decline');
        });
    });

    // Learn (A5.3b): courses for my state, search inside them, a lesson opened at its place.
    Route::middleware('can:learning.study')->controller(LearnController::class)->prefix('learn')->name('learn.')->group(function () {
        Route::get('/', 'index')->middleware('throttle:60,1,learn')->name('index');
        Route::get('lessons/{lesson}', 'lesson')->whereNumber('lesson')->name('lesson');
    });
    // Practice and exams, and the study plan (A8.3): candidates' own, not admin actions.
    Route::controller(PracticePageController::class)->prefix('practice')->name('practice.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('tests', 'start')->middleware('throttle:30,1,practice-start')->name('start');
        Route::get('attempts/{attempt}', 'attempt')->whereNumber('attempt')->name('attempt');
        Route::post('attempts/{attempt}/submit', 'submit')->whereNumber('attempt')->middleware('throttle:30,1,practice-submit')->name('submit');
        Route::get('attempts/{attempt}/results', 'results')->whereNumber('attempt')->name('results');
    });
    Route::get('readiness', [PracticePageController::class, 'readiness'])->name('practice.readiness');
    // Fix a weak topic and the smart drill's Hint (A9.2b).
    Route::post('practice/topic-plans', [TopicPlanPageController::class, 'store'])->middleware('throttle:30,1,practice-plans')->name('practice.plans.store');
    Route::get('practice/fix/{plan}', [TopicPlanPageController::class, 'show'])->whereNumber('plan')->name('practice.plans.show');
    Route::post('practice/fix/{plan}/continue', [TopicPlanPageController::class, 'continue'])->whereNumber('plan')->middleware('throttle:60,1,practice-plans')->name('practice.plans.continue');
    // The weekly progress report (A9.2e): the page, and sharing it.
    Route::get('practice/progress', [PracticePageController::class, 'progress'])->name('practice.progress');
    Route::get('practice/challenges', [ChallengePageController::class, 'index'])->name('practice.challenges');
    Route::post('practice/challenges', [ChallengeController::class, 'store'])->middleware('throttle:20,1,practice-challenges')->name('practice.challenges.store');
    Route::put('practice/groups/opt-in', [StudyGroupController::class, 'optIn'])->middleware('throttle:20,1,practice-groups')->name('practice.groups.opt-in');
    Route::post('practice/groups', [StudyGroupController::class, 'store'])->middleware('throttle:10,1,practice-groups')->name('practice.groups.store');
    Route::post('practice/groups/{group}/join', [StudyGroupController::class, 'join'])->whereUuid('group')->middleware('throttle:20,1,practice-groups')->name('practice.groups.join');
    Route::post('practice/groups/{group}/leave', [StudyGroupController::class, 'leave'])->whereUuid('group')->middleware('throttle:20,1,practice-groups')->name('practice.groups.leave');
    Route::post('practice/groups/{group}/challenge', [StudyGroupController::class, 'challenge'])->whereUuid('group')->middleware('throttle:20,1,practice-challenges')->name('practice.groups.challenge');
    Route::post('practice/challenges/{challenge}/accept', [ChallengeController::class, 'accept'])->whereUuid('challenge')->middleware('throttle:30,1,practice-challenges')->name('practice.challenges.accept');
    Route::post('practice/reports/weekly/share', [ReportController::class, 'share'])->middleware('throttle:20,1,practice-reports')->name('practice.reports.share');
    // Flashcards and the speed round (A9.2d): the pages, and their JSON.
    Route::get('practice/flashcards', [PracticePageController::class, 'flashcards'])->name('practice.flashcards');
    Route::post('practice/flashcards', [FlashcardController::class, 'store'])->middleware('throttle:60,1,practice-flashcards')->name('practice.flashcards.store');
    Route::post('practice/flashcards/reviews', [FlashcardController::class, 'reviews'])->middleware('throttle:120,1,practice-flashcards')->name('practice.flashcards.reviews');
    Route::get('practice/speed', [PracticePageController::class, 'speed'])->name('practice.speed');
    Route::post('practice/speed', [SpeedRoundController::class, 'store'])->middleware('throttle:30,1,practice-speed')->name('practice.speed.store');
    Route::post('practice/speed/{round}/finish', [SpeedRoundController::class, 'finish'])->whereNumber('round')->middleware('throttle:30,1,practice-speed')->name('practice.speed.finish');
    // Math lab (A9.2c): the page, and its problems and checks as JSON.
    Route::get('practice/math', [PracticePageController::class, 'math'])->name('practice.math');
    Route::post('practice/math/problems', [MathLabController::class, 'store'])->middleware('throttle:120,1,practice-math')->name('practice.math.problems');
    Route::post('practice/math/check', [MathLabController::class, 'check'])->middleware('throttle:240,1,practice-math')->name('practice.math.check');
    Route::post('practice/attempts/{attempt}/questions/{question}/hint', [CoachController::class, 'hint'])->whereNumber(['attempt', 'question'])->middleware('throttle:120,1,practice-hint')->name('practice.hint');
    Route::put('practice/attempts/{attempt}/answers/{question}', [ApiPracticeController::class, 'answer'])->whereNumber(['attempt', 'question'])->middleware('throttle:240,1,practice-answer')->name('practice.answer');
    Route::post('practice/attempts/{attempt}/pause', [ApiPracticeController::class, 'pause'])->whereNumber('attempt')->middleware('throttle:60,1,practice-pause')->name('practice.pause');
    Route::post('practice/attempts/{attempt}/finish', [ApiPracticeController::class, 'finish'])->whereNumber('attempt')->middleware('throttle:30,1,practice-finish')->name('practice.finish');
    Route::get('practice/attempts/{attempt}/similar/{question}', [ApiPracticeController::class, 'similar'])->whereNumber(['attempt', 'question'])->middleware('throttle:60,1,practice-similar')->name('practice.similar');
    Route::get('practice/attempts/{attempt}/why/{question}', [ApiPracticeController::class, 'why'])->whereNumber(['attempt', 'question'])->middleware('throttle:60,1,practice-why')->name('practice.why');
    // Ask, the study mate (A10.1): the page, and questions as JSON.
    Route::get('ask', [AskPageController::class, 'index'])->name('ask.index');
    Route::post('ask', [AskController::class, 'store'])->middleware('throttle:20,1,ask')->name('ask.store');
    Route::post('ask/threads/{thread}/instructor', [AskController::class, 'instructor'])->whereUuid('thread')->middleware('throttle:10,1,ask-instructor')->name('ask.instructor');
    // Student questions (A10.1b): the instructor's queue and answers.
    Route::get('instructor/questions', [QuestionsPageController::class, 'index'])->name('instructor.questions.index');
    Route::post('instructor/questions/{question}/answer', [InstructorQuestionController::class, 'answer'])->whereUuid('question')->middleware('throttle:60,1,instructor-answer')->name('instructor.questions.answer');
    Route::controller(StudyPlanController::class)->prefix('study-plan')->name('study-plan.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('tasks/{task}', 'move')->whereNumber('task')->middleware('throttle:60,1,study-plan-move')->name('move');
        Route::put('settings', 'settings')->middleware('throttle:20,1,study-plan-settings')->name('settings');
    });
    // The player's seat time and check-ins (A8.2).
    // Learners' own progress, not admin actions: the controller checks learning.study.
    Route::controller(PlayerController::class)->prefix('learn')->name('learn.')->group(function () {
        Route::post('lessons/{lesson}/heartbeat', 'heartbeat')->whereNumber('lesson')->middleware('throttle:12,1,learn-heartbeat')->name('heartbeat');
        Route::post('lessons/{lesson}/checkins', 'checkin')->whereNumber('lesson')->middleware('throttle:30,1,learn-checkin')->name('checkin');
        Route::post('lessons/{lesson}/ask', 'ask')->whereNumber('lesson')->middleware('throttle:20,1,learn-ask')->name('ask');
    });
    Route::post('learn/questions/{question}/report', [QuestionReportController::class, 'store'])->whereNumber('question')->middleware('throttle:20,1,learn-question-report')->name('learn.questions.report');
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
    // Bring in your agents from TREC, and seats and who pays (A15.1b): the same actions as the API.
    Route::post('organisation/roster/contacts', [RosterController::class, 'contacts'])->middleware('throttle:10,1,roster-contacts')->name('roster.contacts');
    Route::post('organisation/roster/invite', [RosterController::class, 'invite'])->middleware('throttle:5,1,roster-invite')->name('roster.invite');
    Route::put('organisation/seats', [SeatsController::class, 'update'])->middleware('throttle:20,1,seats-update')->name('seats.update');
    // People and permissions, and Brokerage › Policies (A15.2): the page, and the same actions as the API.
    Route::put('organisation/staff/{member}', [StaffController::class, 'update'])->whereNumber('member')->middleware('throttle:30,1,staff-update')->name('staff.update');
    Route::get('organisation/policies', PoliciesPageController::class)->name('policies.index');
    Route::middleware('throttle:30,1,policy-rules')->group(function () {
        Route::post('organisation/policies/rules', [PoliciesController::class, 'store'])->name('policies.rules.store');
        Route::put('organisation/policies/rules/{rule}', [PoliciesController::class, 'update'])->whereNumber('rule')->name('policies.rules.update');
        Route::delete('organisation/policies/rules/{rule}', [PoliciesController::class, 'destroy'])->whereNumber('rule')->name('policies.rules.destroy');
        Route::post('organisation/policies/rules/{rule}/restore', [PoliciesController::class, 'restore'])->whereNumber('rule')->name('policies.rules.restore');
    });
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
// Recruiting (A10.3b): candidates who opted in to broker offers; offers are audited.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('recruiting')->name('recruiting.')->controller(RecruitingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::put('candidates/{candidate}/offer', 'offer')->whereNumber('candidate')->middleware('throttle:30,1,recruiting-offer')->name('offer');
    Route::post('offers/{offer}/withdraw', 'withdraw')->whereUuid('offer')->middleware('throttle:30,1,recruiting-withdraw')->name('withdraw');
});
// Brokerage integrations (B8.2, broker screen 40): which tools agents may use, the brokerage's own CRM, its mapping.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/integrations')->name('brokerage-integrations.')
    ->controller(BrokerageIntegrationsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('tools/{tool}', 'allow')->where('tool', '[a-z0-9-]+')->middleware('throttle:30,1,brokerage-integrations-allow')->name('allow');
        Route::post('tools/{tool}/key', 'connectKey')->where('tool', '[a-z0-9-]+')->middleware('throttle:10,1,brokerage-integrations-key')->name('key');
        Route::put('connections/{connection}/mapping', 'mapping')->whereNumber('connection')->middleware('throttle:20,1,brokerage-integrations-map')->name('mapping');
    });
// Advertising policy (B12.1, broker screen 35): Texas rules, the brokerage's rules and banned phrases, brand kit, approvals.
// The mentor workspace (A13.1b, web screen 48): Today, mentees, a mentee's notes and goals.
Route::middleware(['auth', 'verified'])->prefix('mentoring')->name('mentoring.')->group(function () {
    Route::get('/', [MentorWorkspacePageController::class, 'index'])->name('index');
    Route::get('{agent}', [MentorWorkspacePageController::class, 'show'])->whereNumber('agent')->name('show');
    Route::controller(MentorWorkspaceController::class)->group(function () {
        Route::post('{agent}/notes', 'addNote')->whereNumber('agent')->middleware('throttle:60,1,mentoring-notes')->name('notes.store');
        Route::delete('notes/{note}', 'removeNote')->whereUuid('note')->middleware('throttle:60,1,mentoring-notes')->name('notes.destroy');
        Route::post('notes/{note}/restore', 'restoreNote')->whereUuid('note')->middleware('throttle:60,1,mentoring-notes')->name('notes.restore');
        Route::post('{agent}/goals', 'addGoal')->whereNumber('agent')->middleware('throttle:30,1,mentoring-goals')->name('goals.store');
    });
});

// Pods and challenges (A13.2): the agent's pod page, and the broker's challenges.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pod', [ChallengesController::class, 'agent'])->name('pod.index');
    Route::controller(ChallengesController::class)->prefix('pod')->name('pod.')->group(function () {
        Route::post('join', 'join')->middleware('throttle:10,1,pod')->name('join');
        Route::post('leave', 'leave')->middleware('throttle:10,1,pod')->name('leave');
        Route::post('rejoin', 'rejoin')->middleware('throttle:10,1,pod')->name('rejoin');
        Route::put('goal', 'goal')->middleware('throttle:30,1,pod')->name('goal');
    });
    Route::controller(ChallengesController::class)->prefix('brokerage-challenges')->name('brokerage-challenges.')->group(function () {
        Route::post('{challenge}/join', 'joinChallenge')->whereUuid('challenge')->middleware('throttle:30,1,challenges')->name('join');
        Route::post('{challenge}/leave', 'leaveChallenge')->whereUuid('challenge')->middleware('throttle:30,1,challenges')->name('leave');
    });
});
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/challenges')->name('organisation.challenges.')->group(function () {
    Route::get('/', [ChallengesController::class, 'broker'])->name('index');
    Route::post('/', [ChallengesController::class, 'store'])->middleware('throttle:20,1,challenges-store')->name('store');
});

// The free candidate studio on the web (B13.3): the page, and JSON for making, checking and posting.
Route::middleware(['auth', 'verified'])->prefix('candidate-studio')->name('candidate-studio.')->group(function () {
    Route::get('/', CandidateStudioPageController::class)->name('index');
    Route::post('moments/{milestone}', [CandidateStudioController::class, 'make'])->whereIn('milestone', ['exam_pass', 'study_streak', 'practice_score'])->middleware('throttle:20,1,candidate-studio')->name('make');
    Route::post('check', [CandidateStudioController::class, 'check'])->middleware('throttle:60,1,candidate-studio-check')->name('check');
    Route::post('posts/{post}', [CandidateStudioController::class, 'post'])->whereUuid('post')->middleware('throttle:10,1,candidate-studio-post')->name('post');
});

// The compliance engine for emails and texts from the web (B13.1): JSON for the reach dialog.
Route::middleware(['auth', 'verified'])->prefix('compliance')->name('compliance.')->group(function () {
    Route::post('check', [ComplianceController::class, 'check'])->middleware('throttle:60,1,compliance')->name('check');
    Route::post('fix', [ComplianceController::class, 'fix'])->middleware('throttle:30,1,compliance')->name('fix');
});

// Feedback from agents after verified closings (A13.3b, screen 125).
Route::middleware(['auth', 'verified'])->prefix('feedback')->name('feedback.')->controller(AgentFeedbackPageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::middleware('throttle:20,1,feedback')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::put('visibility', 'visibility')->name('visibility');
        Route::put('{feedback}/response', 'respond')->whereUuid('feedback')->name('respond');
        Route::post('{feedback}/report', 'report')->whereUuid('feedback')->name('report');
    });
});

// Role-play and the skills assessment (A14.1a, screen 7): private to the agent.
Route::middleware(['auth', 'verified'])->prefix('role-play')->name('role-play.')->controller(RolePlayPageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{rolePlay}', 'show')->whereUuid('rolePlay')->name('show');
    Route::middleware('throttle:20,1,role-play')->group(function () {
        Route::post('/', 'start')->name('start');
        Route::post('assessment', 'assess')->name('assess');
        Route::post('{rolePlay}/finish', 'finish')->whereUuid('rolePlay')->name('finish');
    });
    Route::post('{rolePlay}/turns', 'say')->whereUuid('rolePlay')->middleware('throttle:40,1,role-play-turns')->name('say');
});

// Video practice with an AI review (A14.1b): the words only; the video stays in the browser.
Route::middleware(['auth', 'verified'])->prefix('video-practice')->name('video-practice.')->controller(VideoPracticePageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'review')->middleware('throttle:10,1,video-practice')->name('review');
    Route::get('{videoPractice}', 'show')->whereUuid('videoPractice')->name('show');
});

// Goals and rewards (A13.3a): the agent's goals, streak and rewards; the broker's targets and bonus campaigns.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('rewards', [RewardsPageController::class, 'agent'])->name('rewards.index');
    Route::post('rewards/redeem', [RewardsPageController::class, 'redeem'])->middleware('throttle:10,1,rewards')->name('rewards.redeem');
});
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/rewards')->name('organisation.rewards.')->group(function () {
    Route::get('/', [RewardsPageController::class, 'broker'])->name('index');
    Route::middleware('throttle:30,1,rewards-broker')->group(function () {
        Route::put('goals/{mode}', [RewardsPageController::class, 'targets'])->whereIn('mode', ['launch', 'grow', 'lead', 'own'])->name('goals');
        Route::post('campaigns', [RewardsPageController::class, 'campaign'])->name('campaigns.store');
        Route::post('campaigns/{campaign}/end', [RewardsPageController::class, 'endCampaign'])->whereUuid('campaign')->name('campaigns.end');
    });
});

// The compliance log (B13.2, broker web): every check, fix, approval and send-back, with CSV export.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/compliance-log')->name('organisation.compliance-log.')->group(function () {
    Route::get('/', [ComplianceLogController::class, 'index'])->name('index');
    Route::get('export', [ComplianceLogController::class, 'export'])->middleware('throttle:10,1,compliance-export')->name('export');
});

// The brokerage's mentor program (A13.1a, broker screens 21–23).
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/mentors')->name('organisation.mentors.')
    ->controller(MentorProgramController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('{mentor}', 'update')->whereNumber('mentor')->middleware('throttle:30,1,mentor-program')->name('update');
        Route::get('agents/{agent}/matches', 'matches')->whereNumber('agent')->name('matches');
        Route::post('assign', 'assign')->middleware('throttle:30,1,mentor-program')->name('assign');
        Route::post('reviews/{booking}', 'review')->whereUuid('booking')->middleware('throttle:30,1,mentor-program')->name('review');
    });

Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/advertising')->name('organisation.advertising.')
    ->controller(AdvertisingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->middleware('throttle:20,1,advertising-update')->name('update');
        Route::post('posts/{post}/approve', 'approve')->whereUuid('post')->middleware('throttle:60,1,advertising-approve')->name('approve');
        Route::post('posts/{post}/return', 'sendBack')->whereUuid('post')->middleware('throttle:60,1,advertising-approve')->name('return');
        // Public pages waiting for the broker (B13.2).
        Route::post('pages/{page}/approve', 'approvePage')->where('page', '[a-z0-9-]{1,80}')->middleware('throttle:60,1,advertising-approve')->name('pages.approve');
        Route::post('pages/{page}/return', 'returnPage')->where('page', '[a-z0-9-]{1,80}')->middleware('throttle:60,1,advertising-approve')->name('pages.return');
    });
// The virtual broker, broker side (A12.1, broker screen 34): the manual and its versions, TREC conflicts, the FAQ, escalation, answers.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:organisation.manage'])->prefix('organisation/virtual-broker')->name('organisation.virtual-broker.')
    ->controller(BrokerVirtualBrokerController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('manual', 'manual')->middleware('throttle:10,1,virtual-broker-manual')->name('manual');
        Route::put('settings', 'settings')->middleware('throttle:20,1,virtual-broker-settings')->name('settings');
        Route::post('questions/{question}/reply', 'reply')->whereUuid('question')->middleware('throttle:60,1,virtual-broker-reply')->name('reply');
        Route::delete('faq/{entry}', 'removeFaq')->whereNumber('entry')->middleware('throttle:60,1,virtual-broker-faq')->name('faq.destroy');
        Route::post('faq/{entry}/restore', 'restoreFaq')->whereNumber('entry')->middleware('throttle:60,1,virtual-broker-faq')->name('faq.restore');
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
// Students (A9.2e): weekly reports candidates shared with the school.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:courses.manage'])->get('school/students', [SchoolStudentsController::class, 'index'])->name('school.students.index');
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:courses.manage', 'throttle:20,1,school-groups'])->post('school/groups', [SchoolStudentsController::class, 'storeGroup'])->name('school.groups.store');
// CE reporting to TREC (A11.3, school admin screen 51): TREC's file or REOS submits, the reference, rejections.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:courses.manage'])->prefix('school/ce-reports')->name('school.ce-reports.')->controller(CeReportsController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:10,1,school-ce-reports')->name('store');
    Route::get('{report}/file', 'file')->whereUuid('report')->middleware('throttle:30,1,school-ce-file')->name('file');
    Route::put('{report}/reference', 'reference')->whereUuid('report')->middleware('throttle:20,1,school-ce-reports')->name('reference');
    Route::put('{report}/lines/{completion}', 'line')->whereUuid('report')->whereNumber('completion')->middleware('throttle:60,1,school-ce-reports')->name('line');
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
    Route::put('session-limits', [SchoolInstructorsController::class, 'sessionLimits'])->name('session-limits');
    Route::put('{instructor}/courses', [SchoolInstructorsController::class, 'courses'])->whereNumber('instructor')->name('courses');
    Route::put('{instructor}/twins', [SchoolInstructorsController::class, 'twins'])->whereNumber('instructor')->name('twins');
    Route::delete('{instructor}', [SchoolInstructorsController::class, 'revoke'])->whereNumber('instructor')->name('revoke');
    Route::post('{instructor}/restore', [SchoolInstructorsController::class, 'restore'])->whereNumber('instructor')->name('restore');
});
// The instructor's avatar sample, through a signed link (A7.1b).
Route::get('avatar-samples/{consent}', [AvatarController::class, 'sample'])->whereUuid('consent')->middleware(['signed', 'throttle:60,1,avatar-sample'])->name('avatar.sample');
// The course builder (A7.2, instructor screen 43): school admins and the instructors who teach each course.
Route::middleware(['auth', 'verified', EnsureAdminTwoStep::class, 'can:learning.teach'])->name('builder.')->group(function () {
    Route::get('course-builder', [CourseBuilderController::class, 'index'])->name('index');
    Route::get('course-builder/{course}', [CourseBuilderController::class, 'show'])->whereNumber('course')->name('show');
    Route::get('course-builder/lessons/{lesson}/draft', [CourseBuilderController::class, 'draft'])->whereNumber('lesson')->name('lessons.draft');
    // AI lesson review (screen 44) and question bank (screen 45), A8.1.
    Route::get('course-builder/lessons/{lesson}/review', [LessonReviewController::class, 'show'])->whereNumber('lesson')->name('review.show');
    Route::get('question-bank', [QuestionBankController::class, 'index'])->name('questions.index');
    Route::get('ai-lessons', [LessonReviewController::class, 'index'])->name('review.index');
    Route::middleware('throttle:120,1,builder')->group(function () {
        Route::post('course-builder', [CourseBuilderController::class, 'store'])->name('store');
        Route::put('course-builder/{course}/status', [CourseBuilderController::class, 'status'])->whereNumber('course')->name('status');
        Route::put('course-builder/{course}/settings', [CourseBuilderController::class, 'settings'])->whereNumber('course')->name('settings');
        Route::post('course-builder/{course}/seat-time', [CourseBuilderController::class, 'seatTime'])->whereNumber('course')->middleware('throttle:5,1,seat-time')->name('seat-time');
        Route::post('course-builder/{course}/modules', [CourseBuilderController::class, 'addModule'])->whereNumber('course')->name('modules.store');
        Route::patch('course-builder/modules/{module}', [CourseBuilderController::class, 'renameModule'])->whereNumber('module')->name('modules.update');
        Route::delete('course-builder/modules/{module}', [CourseBuilderController::class, 'removeModule'])->whereNumber('module')->name('modules.destroy');
        Route::post('course-builder/modules/{module}/sources', [CourseBuilderController::class, 'beginSource'])->whereNumber('module')->name('sources.store');
        Route::post('course-builder/sources/{source}/complete', [CourseBuilderController::class, 'completeSource'])->whereNumber('source')->name('sources.complete');
        Route::delete('course-builder/sources/{source}', [CourseBuilderController::class, 'removeSource'])->whereNumber('source')->name('sources.destroy');
        Route::post('course-builder/modules/{module}/lessons', [CourseBuilderController::class, 'addLesson'])->whereNumber('module')->name('lessons.store');
        Route::patch('course-builder/lessons/{lesson}', [CourseBuilderController::class, 'updateLesson'])->whereNumber('lesson')->name('lessons.update');
        Route::post('course-builder/lessons/{lesson}/move', [CourseBuilderController::class, 'moveLesson'])->whereNumber('lesson')->name('lessons.move');
        Route::post('course-builder/lessons/{lesson}/approve', [CourseBuilderController::class, 'approveLesson'])->whereNumber('lesson')->name('lessons.approve');
        Route::delete('course-builder/lessons/{lesson}', [CourseBuilderController::class, 'removeLesson'])->whereNumber('lesson')->name('lessons.destroy');
        Route::post('course-builder/lessons/{lesson}/restore', [CourseBuilderController::class, 'restoreLesson'])->whereNumber('lesson')->name('lessons.restore');
        Route::post('course-builder/lessons/{lesson}/review/lines/{line}', [LessonReviewController::class, 'line'])->whereNumber(['lesson', 'line'])->name('review.line');
        Route::post('course-builder/lessons/{lesson}/review/lines/{line}/suggest', [LessonReviewController::class, 'suggest'])->whereNumber(['lesson', 'line'])->middleware('throttle:30,1,ai-suggest')->name('review.suggest');
        Route::post('course-builder/lessons/{lesson}/review/lines/{line}/comments', [LessonReviewController::class, 'comment'])->whereNumber(['lesson', 'line'])->name('review.comment');
        Route::post('course-builder/lessons/{lesson}/review/items/{item}', [LessonReviewController::class, 'item'])->whereNumber(['lesson', 'item'])->name('review.item');
        Route::post('course-builder/lessons/{lesson}/review/render', [LessonReviewController::class, 'render'])->whereNumber('lesson')->middleware('throttle:6,1,ai-lessons')->name('review.render');
        Route::post('course-builder/lessons/{lesson}/review/publish', [LessonReviewController::class, 'publish'])->whereNumber('lesson')->name('review.publish');
        Route::post('course-builder/lessons/{lesson}/review/send-back', [LessonReviewController::class, 'sendBack'])->whereNumber('lesson')->name('review.send-back');
        Route::put('question-bank/{item}', [QuestionBankController::class, 'update'])->whereNumber('item')->name('questions.update');
        Route::post('question-bank/{item}/decision', [QuestionBankController::class, 'decide'])->whereNumber('item')->name('questions.decide');
        Route::post('question-bank/disputes/{dispute}', [QuestionBankController::class, 'resolve'])->whereNumber('dispute')->name('questions.resolve');
        // The starter bank tab (A9.1): school admins approve, retire, restore or edit REOS's Texas starter questions.
        Route::post('question-bank/starter', [StarterBankController::class, 'decide'])->name('starter.decide');
        Route::put('question-bank/starter/{question}', [StarterBankController::class, 'update'])->whereNumber('question')->name('starter.update');
        // AI lessons (A7.3): generate a module, generate a lesson again.
        Route::post('course-builder/modules/{module}/generate', [CourseBuilderController::class, 'generate'])->whereNumber('module')->middleware('throttle:6,1,ai-lessons')->name('modules.generate');
        Route::post('course-builder/lessons/{lesson}/regenerate', [CourseBuilderController::class, 'regenerate'])->whereNumber('lesson')->middleware('throttle:6,1,ai-lessons')->name('lessons.regenerate');
    });
});
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

// A deal's kept file (B9.3), through its short-lived signed link.
Route::get('deals/files/{file}', [SignaturePageController::class, 'file'])->whereUuid('file')
    ->middleware(['signed', 'throttle:120,1,deal-files-open'])->name('deals.files.show');

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

// Booking 1-on-1 sessions (A10.2a): the pages, and the same JSON as the API.
Route::middleware(['auth', 'verified', RequireFlag::class.':video_rooms'])->prefix('bookings')->name('bookings.')->group(function () {
    Route::get('/', [BookingsPageController::class, 'index'])->name('index');
    Route::get('instructors/{user}', [BookingsPageController::class, 'instructor'])->whereNumber('user')->name('instructor');
    Route::get('hours', [BookingsPageController::class, 'hours'])->name('hours');
    Route::put('hours', [BookingController::class, 'saveOffer'])->middleware('throttle:20,1,bookings-offer')->name('hours.save');
    Route::post('/', [BookingController::class, 'store'])->middleware('throttle:20,1,bookings-store')->name('store');
    Route::post('{booking}/cancel', [BookingController::class, 'cancel'])->whereUuid('booking')->middleware('throttle:10,1,bookings-cancel')->name('cancel');
    Route::post('{booking}/rating', [BookingController::class, 'rate'])->whereUuid('booking')->middleware('throttle:10,1,bookings-rate')->name('rating');
    Route::get('{booking}/tasks', [BookingController::class, 'suggestedTasks'])->whereUuid('booking')->name('tasks');
    Route::post('{booking}/tasks', [BookingController::class, 'sendTasks'])->whereUuid('booking')->middleware('throttle:10,1,bookings-tasks')->name('tasks.send');
});

// Live classes (A10.2b): the schedule page, and the same JSON actions as the API for the web's room.
Route::middleware(['auth', 'verified', RequireFlag::class.':video_rooms'])->prefix('classes')->name('classes.')->group(function () {
    Route::get('/', [ClassesPageController::class, 'index'])->name('index');
    Route::get('{session}/report', [ClassesPageController::class, 'report'])->whereUuid('session')->name('report');
    Route::controller(ClassesController::class)->group(function () {
        Route::post('/', 'store')->middleware('throttle:20,1,classes-schedule')->name('store');
        Route::post('{session}/registration', 'register')->whereUuid('session')->middleware('throttle:20,1,classes-register')->name('register');
        Route::delete('{session}/registration', 'unregister')->whereUuid('session')->middleware('throttle:20,1,classes-register')->name('unregister');
        Route::get('{session}/live', 'live')->whereUuid('session')->middleware('throttle:120,1,classes-live')->name('live');
        Route::post('{session}/hand', 'hand')->whereUuid('session')->middleware('throttle:30,1,classes-hand')->name('hand');
        Route::post('{session}/stage', 'stage')->whereUuid('session')->middleware('throttle:60,1,classes-stage')->name('stage');
        Route::post('{session}/polls', 'poll')->whereUuid('session')->middleware('throttle:20,1,classes-poll')->name('polls');
        Route::post('{session}/polls/{poll}/close', 'closePoll')->whereUuid('session')->whereNumber('poll')->middleware('throttle:20,1,classes-poll')->name('polls.close');
        Route::post('{session}/polls/{poll}/votes', 'vote')->whereUuid('session')->whereNumber('poll')->middleware('throttle:30,1,classes-vote')->name('polls.vote');
        Route::post('{session}/questions', 'ask')->whereUuid('session')->middleware('throttle:10,1,classes-ask')->name('questions');
        Route::post('{session}/questions/{question}/like', 'like')->whereUuid('session')->whereNumber('question')->middleware('throttle:60,1,classes-like')->name('questions.like');
        Route::post('{session}/questions/{question}/answered', 'answered')->whereUuid('session')->whereNumber('question')->middleware('throttle:60,1,classes-answered')->name('questions.answered');
        Route::post('{session}/messages', 'chat')->whereUuid('session')->middleware('throttle:30,1,classes-chat')->name('messages');
        Route::post('{session}/checks', 'check')->whereUuid('session')->middleware('throttle:10,1,classes-check')->name('checks');
        Route::post('{session}/checks/{check}/answer', 'answerCheck')->whereUuid('session')->whereNumber('check')->middleware('throttle:30,1,classes-check-answer')->name('checks.answer');
        Route::post('{session}/report/decisions', 'decide')->whereUuid('session')->middleware('throttle:60,1,classes-report')->name('report.decide');
        Route::post('{session}/report/send', 'sendReport')->whereUuid('session')->middleware('throttle:10,1,classes-report-send')->name('report.send');
    });
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
// DocuSign's sender view on demo data (B9.3): signed, for the agent's own demo envelope.
Route::middleware(['auth', 'signed', 'throttle:60,1,docusign-simulator'])->group(function () {
    Route::get('connections/simulator/docusign/envelopes/{envelope}', [DocuSignSimulatorController::class, 'show'])->whereUuid('envelope')->name('connections.simulator.docusign');
    Route::post('connections/simulator/docusign/envelopes/{envelope}', [DocuSignSimulatorController::class, 'send'])->whereUuid('envelope')->name('connections.simulator.docusign.send');
});

Route::get('connections/simulator/{tool}', [SimulatorSignInController::class, 'show'])
    ->whereIn('tool', ConnectorRegistry::ids())->middleware('throttle:sign-in')->name('connections.simulator');
Route::post('connections/simulator/{tool}', [SimulatorSignInController::class, 'decide'])
    ->whereIn('tool', ConnectorRegistry::ids())->middleware(['throttle:sign-in', 'tenancy.platform'])->name('connections.simulator.decide');
