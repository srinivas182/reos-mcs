<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Billing\Ledger\Ledger;
use App\Billing\Plans;
use App\Billing\PriceInterval;
use App\Billing\ProductStatus;
use App\Billing\SponsorCodes;
use App\Content\Ai\LessonPipeline;
use App\Content\Avatars\AvatarConsents;
use App\Content\MediaKind;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Features\Rollouts;
use App\Http\Middleware\EnsureAdminTwoStep;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\GatewaySetting;
use App\Models\Invitation;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\MessageReport;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\PrivacyRequest;
use App\Models\Product;
use App\Models\ProviderCategory;
use App\Models\State;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Privacy\ConsentMethod;
use App\Services\Ai\AiSettings;
use App\Services\Content\Courses;
use App\Services\Content\LessonFiles;
use App\Services\Content\Lessons;
use App\Services\Licences\Licences;
use App\Services\Privacy\PrivacyRequests;
use App\Services\StateRules\StateRules;
use App\Services\Tenancy\Invitations;
use App\Services\Tenancy\SingleSignOn;
use App\Services\Tenancy\SupportAccess;
use App\Services\Tenancy\TenantDomains;
use App\Sso\SsoProtocol;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

/*
| A4.3b "done when": every admin action is in the audit log. This walks every
| admin route that changes something and checks it writes an audit row with
| the actor, the tenant and the IP address. A new admin route fails the first
| test until it is listed here, so nothing slips through unaudited.
*/

/** @var array{actor: User, method: string, uri: string, data?: array<string, mixed>, tenant: int|null, confirmed?: bool} */
function auditCase(User $actor, string $method, string $uri, array $data = [], ?int $tenant = null, bool $confirmed = false): array
{
    return compact('actor', 'method', 'uri', 'data', 'tenant', 'confirmed');
}

function auditOps(): User
{
    return User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
}

/** @return array{0: Organisation, 1: User} */
function auditBrokerage(): array
{
    $lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'slug' => 'lone-star']);

    return [$lone, User::factory()->withRole(Role::Broker, $lone)->withTwoFactor()->create(['name' => 'Linda Park'])];
}

/** @return array{0: Organisation, 1: User, 2: Course} */
function auditSchoolCourse(): array
{
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $school)->create(['name' => 'Paul Diaz']);
    $course = Course::query()->create(['organisation_id' => $school->id, 'title' => 'Principles of Real Estate II', 'slug' => 'principles-ii', 'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30]);

    return [$school, $paul, $course];
}

/** @return array{0: Organisation, 1: User, 2: Course, 3: User, 4: CourseModule, 5: Lesson} */
function auditBuilder(): array
{
    [$school, $paul, $course] = auditSchoolCourse();
    $rachel = User::factory()->withRole(Role::Instructor, $school)->create(['name' => 'Dr. Rachel Hughes']);
    $course->instructors()->attach($rachel->id);
    $module = CourseModule::query()->create(['course_id' => $course->id, 'title' => 'Agency', 'position' => 1]);
    $lesson = Lesson::query()->create(['course_id' => $course->id, 'course_module_id' => $module->id, 'title' => 'Who you represent', 'position' => 1, 'body' => 'Explain who you represent.', 'review_status' => 'draft']);

    return [$school, $paul, $course, $rachel, $module, $lesson];
}

/** A builder module with source material and a school with credits, for AI lessons (A7.3). */
function auditAiModule(): array
{
    Storage::fake(config()->string('reos.content.disk'));
    [$school, $paul, $course, $rachel, $module, $lesson] = auditBuilder();
    $module->sources()->create([
        'role' => 'script', 'kind' => 'document', 'path' => "tenants/{$school->id}/courses/{$course->id}/sources/a.pdf", 'original_name' => 'Agency script.pdf',
        'mime' => 'application/pdf', 'size' => 100, 'status' => 'ready', 'scanned_at' => now(), 'position' => 1, 'words' => 20,
        'sections' => [['page' => 1, 'text' => 'In Texas, a license holder tells each party who they represent at the first substantive contact.']],
    ]);
    $ledger = app(Ledger::class);
    $ledger->post('pool_funded', [[$ledger->platform('credits_sold'), -500], [$ledger->organisation($school, 'credits'), 500]], 'organisation', (string) $school->id);

    return [$school, $paul, $course, $rachel, $module, $lesson];
}

/** @return array{0: Organisation, 1: User} */
function auditAssociation(): array
{
    $texas = Organisation::factory()->ofType(OrganisationType::Association)->create(['name' => 'Texas REALTORS®', 'slug' => 'texas-realtors']);

    return [$texas, User::factory()->withRole(Role::AssociationAdmin, $texas)->withTwoFactor()->create(['name' => 'Karen Webb'])];
}

function auditInvitation(Organisation $lone, User $linda): Invitation
{
    app(Invitations::class)->invite($lone, $linda, [['email' => 'rosa@example.com', 'phone' => null, 'name' => 'Rosa Lima']], Role::Agent, null, 'en');

    return app(Tenancy::class)->asPlatform(fn () => Invitation::query()->sole());
}

function auditGrant(Organisation $lone, User $ops, bool $approve = false, ?User $linda = null): SupportAccessGrant
{
    $grant = app(SupportAccess::class)->ask($lone, $ops, 'Linda asked us to check her calendar sync');

    return $approve && $linda !== null ? app(SupportAccess::class)->answer($grant, $linda, true) : $grant;
}

function auditCourse(User $ops): Course
{
    return app(Courses::class)->create(['title' => 'Principles of Real Estate II', 'type' => 'test_prep', 'states' => ['TX']], $ops);
}

function auditLesson(User $ops): Lesson
{
    return app(Lessons::class)->create(auditCourse($ops), ['title' => 'Agency disclosure'], $ops);
}

function auditPlan(User $ops): Product
{
    return app(Plans::class)->create(['name' => 'Agent Core', 'kind' => 'plan', 'buyer' => 'agent'], $ops);
}

/** @return array{0: Course, 1: string} a course with a flagged line, and the flag */
function auditFlag(User $ops): array
{
    $lesson = auditLesson($ops);
    app(Lessons::class)->update($lesson, ['body' => 'We guarantee you pass.'], $ops);

    return [$lesson->course, $lesson->passages()->sole()->content_hash.':guarantee'];
}

/** A captions file uploaded to storage, waiting for "done". */
function auditFile(User $ops): LessonMedia
{
    $bytes = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello";
    $media = app(LessonFiles::class)->begin(auditLesson($ops), MediaKind::Captions, 'a.vtt', strlen($bytes), $ops)['media'];
    LessonFiles::disk()->put($media->path, $bytes);

    return $media;
}

/** @return array<string, Closure(TestCase): array<string, mixed>> */
function adminActions(): array
{
    $stripe = ['publishable_key' => 'example-publishable-key-0001', 'secret_key' => 'sk_live_fake-0000-0000-9876', 'webhook_secret' => 'example-webhook-secret-4321'];

    return [
        // Super admin: Integration Hub and catalogue (screens 64–65).
        'integrations.keys.update' => fn () => auditCase(auditOps(), 'PUT', '/admin/integrations/stripe/keys/live', ['values' => $stripe], confirmed: true),
        'integrations.keys.destroy' => function () use ($stripe) {
            $ops = auditOps();
            test()->actingAs($ops)->withSession(['auth.password_confirmed_at' => time()])->put('/admin/integrations/stripe/keys/live', ['values' => $stripe]);

            return auditCase($ops, 'DELETE', '/admin/integrations/stripe/keys/live', confirmed: true);
        },
        'integrations.mode.update' => fn () => auditCase(auditOps(), 'PATCH', '/admin/integrations/stripe/mode', ['mode' => 'off'], confirmed: true),
        'integrations.checks.store' => fn () => auditCase(auditOps(), 'POST', '/admin/integrations/stripe/checks/sandbox', confirmed: true),
        'integrations.catalogue.update' => fn () => auditCase(auditOps(), 'PUT', '/admin/integrations/zoom/catalogue', [
            'status' => 'beta', 'states' => ['TX'], 'roles' => ['agent'], 'beta_organisations' => [],
            'tenants_may_hide' => true, 'tenants_may_require' => false, 'show_coming_soon' => true,
        ], confirmed: true),
        'integrations.pause' => fn () => auditCase(auditOps(), 'PUT', '/admin/integrations/google-calendar/pause', ['paused' => true], confirmed: true),

        // Super admin: People, States, Tenants (screens 59–61).
        'people.consents.update' => fn () => auditCase(auditOps(), 'PUT', '/admin/people/'.User::factory()->create()->id.'/consents/marketing_email', ['granted' => false, 'note' => 'Asked by email to stop news'], confirmed: true),
        'people.roles.store' => function () {
            [$lone] = auditBrokerage();

            return auditCase(auditOps(), 'POST', '/admin/people/'.User::factory()->create()->id.'/roles', ['role' => 'agent', 'organisation_id' => $lone->id], confirmed: true);
        },
        'people.roles.destroy' => function () {
            [$lone] = auditBrokerage();
            $person = User::factory()->withRole(Role::Agent, $lone)->create();

            return auditCase(auditOps(), 'DELETE', "/admin/people/{$person->id}/roles", ['role' => 'agent', 'organisation_id' => $lone->id], confirmed: true);
        },
        'states.update' => fn () => auditCase(auditOps(), 'PATCH', '/admin/states/LA', ['status' => 'in_preparation'], confirmed: true),
        'states.prepare' => fn () => auditCase(auditOps(), 'PUT', '/admin/states/LA/details', ['regulator' => 'LREC', 'exam_vendor' => 'PSI', 'qualifying_hours' => 90, 'ce_hours' => 12], confirmed: true),
        'states.rules.draft' => fn () => auditCase(auditOps(), 'PUT', '/admin/states/TX/rules/ce.hours', ['value' => ['number' => 18], 'citation' => '22 TAC §535.92(a)', 'effective_from' => '2027-01-01'], confirmed: true),
        'states.rules.discard' => function () {
            $ops = auditOps();
            $draft = app(StateRules::class)->draft(State::query()->findOrFail('TX'), 'ce.hours', ['value' => ['number' => 18], 'citation' => 'x', 'effective_from' => '2027-01-01'], $ops);

            return auditCase($ops, 'DELETE', "/admin/states/TX/rules/drafts/{$draft->id}", confirmed: true);
        },
        'states.rules.publish' => function () {
            $ops = auditOps();
            app(StateRules::class)->draft(State::query()->findOrFail('TX'), 'ce.hours', ['value' => ['number' => 18], 'citation' => 'x', 'effective_from' => '2027-01-01'], $ops);

            return auditCase($ops, 'POST', '/admin/states/TX/rules/publish', confirmed: true);
        },
        // Super admin: content library (A5.3).
        'content.courses.store' => fn () => auditCase(auditOps(), 'POST', '/admin/content/courses', ['title' => 'Legal Update I', 'type' => 'ce', 'states' => ['TX']], confirmed: true),
        'content.courses.update' => fn () => auditCase($ops = auditOps(), 'PATCH', '/admin/content/courses/'.auditCourse($ops)->id, ['title' => 'Legal Update II'], confirmed: true),
        'content.courses.status' => fn () => auditCase($ops = auditOps(), 'PUT', '/admin/content/courses/'.auditCourse($ops)->id.'/status', ['status' => 'in_review'], confirmed: true),
        'content.courses.destroy' => fn () => auditCase($ops = auditOps(), 'DELETE', '/admin/content/courses/'.auditCourse($ops)->id, confirmed: true),
        'content.courses.restore' => function () {
            $ops = auditOps();
            $course = auditCourse($ops);
            $course->delete();

            return auditCase($ops, 'POST', "/admin/content/courses/{$course->id}/restore", confirmed: true);
        },
        'content.flags.clear' => function () {
            $ops = auditOps();
            [$course, $key] = auditFlag($ops);

            return auditCase($ops, 'POST', "/admin/content/courses/{$course->id}/flags/{$key}", confirmed: true);
        },
        'content.flags.restore' => function () {
            $ops = auditOps();
            [$course, $key] = auditFlag($ops);
            app(Courses::class)->clearFlag($course, $key, $ops);

            return auditCase($ops, 'DELETE', "/admin/content/courses/{$course->id}/flags/{$key}", confirmed: true);
        },
        'content.modules.store' => fn () => auditCase($ops = auditOps(), 'POST', '/admin/content/courses/'.auditCourse($ops)->id.'/modules', ['title' => 'Unit 6'], confirmed: true),
        'content.modules.update' => fn () => auditCase($ops = auditOps(), 'PATCH', '/admin/content/modules/'.auditCourse($ops)->modules()->create(['title' => 'Unit 6'])->id, ['title' => 'Unit 6: Agency'], confirmed: true),
        'content.modules.destroy' => fn () => auditCase($ops = auditOps(), 'DELETE', '/admin/content/modules/'.auditCourse($ops)->modules()->create(['title' => 'Unit 6'])->id, confirmed: true),
        'content.lessons.store' => fn () => auditCase($ops = auditOps(), 'POST', '/admin/content/courses/'.auditCourse($ops)->id.'/lessons', ['title' => 'Agency disclosure'], confirmed: true),
        'content.lessons.update' => fn () => auditCase($ops = auditOps(), 'PATCH', '/admin/content/lessons/'.auditLesson($ops)->id, ['body' => 'The IABS notice.'], confirmed: true),
        'content.lessons.destroy' => fn () => auditCase($ops = auditOps(), 'DELETE', '/admin/content/lessons/'.auditLesson($ops)->id, confirmed: true),
        'content.lessons.restore' => function () {
            $ops = auditOps();
            $lesson = auditLesson($ops);
            $lesson->delete();

            return auditCase($ops, 'POST', "/admin/content/lessons/{$lesson->id}/restore", confirmed: true);
        },
        'content.lessons.reindex' => fn () => auditCase($ops = auditOps(), 'POST', '/admin/content/lessons/'.auditLesson($ops)->id.'/reindex', confirmed: true),
        'content.files.store' => function () {
            Storage::fake();
            $ops = auditOps();

            return auditCase($ops, 'POST', '/admin/content/lessons/'.auditLesson($ops)->id.'/files', ['kind' => 'captions', 'name' => 'a.vtt', 'size' => 10], confirmed: true);
        },
        'content.files.complete' => function () {
            Storage::fake();
            $ops = auditOps();
            $media = auditFile($ops);

            return auditCase($ops, 'POST', "/admin/content/files/{$media->id}/complete", confirmed: true);
        },
        'content.files.destroy' => function () {
            Storage::fake();
            $ops = auditOps();

            return auditCase($ops, 'DELETE', '/admin/content/files/'.auditFile($ops)->id, confirmed: true);
        },
        // Super admin: plans and pricing (B5.1, screen 67).
        'plans.store' => fn () => auditCase(auditOps(), 'POST', '/admin/plans', ['name' => 'Agent Core', 'kind' => 'plan', 'buyer' => 'agent'], confirmed: true),
        'plans.update' => fn () => auditCase($ops = auditOps(), 'PATCH', '/admin/plans/'.auditPlan($ops)->id, ['name' => 'Agent Core Plus'], confirmed: true),
        'plans.status' => function () {
            $ops = auditOps();
            $plan = auditPlan($ops);
            app(Plans::class)->setPrice($plan, 'TX', PriceInterval::Month, 2900, null, $ops);

            return auditCase($ops, 'PUT', "/admin/plans/{$plan->id}/status", ['status' => 'live'], confirmed: true);
        },
        'plans.prices.store' => fn () => auditCase($ops = auditOps(), 'POST', '/admin/plans/'.auditPlan($ops)->id.'/prices', ['state' => 'TX', 'interval' => 'month', 'amount' => 29], confirmed: true),
        'plans.prices.destroy' => function () {
            $ops = auditOps();
            $plan = auditPlan($ops);
            $price = app(Plans::class)->setPrice($plan, 'TX', PriceInterval::Month, 2900, null, $ops);

            return auditCase($ops, 'DELETE', "/admin/plans/{$plan->id}/prices/{$price->id}", confirmed: true);
        },
        'plans.ios-buy-link' => fn () => auditCase(auditOps(), 'PUT', '/admin/plans/ios-buy-link', ['mode' => 'link'], confirmed: true),
        // Super admin: payment gateways (B5.2a, screen 66).
        'gateways.update' => fn () => auditCase(auditOps(), 'PUT', '/admin/gateways/paypal', ['states' => ['TX'], 'used_for' => ['package'], 'is_default' => true, 'tenant_accounts' => false], confirmed: true),
        'gateways.pause' => fn () => auditCase(auditOps(), 'POST', '/admin/gateways/stripe/pause', ['reason' => 'Card testing attack'], confirmed: true),
        'gateways.resume' => function () {
            $ops = auditOps();
            GatewaySetting::query()->create(['gateway' => 'stripe', 'paused' => true, 'paused_reason' => 'Outage']);

            return auditCase($ops, 'DELETE', '/admin/gateways/stripe/pause', confirmed: true);
        },
        // Sponsor pools (B5.3).
        'tenants.pool.fund' => function () {
            [$lone] = auditBrokerage();

            return auditCase(auditOps(), 'POST', "/admin/tenants/{$lone->id}/pool", ['credits' => 1000, 'seats' => 5, 'note' => 'Invoice INV-1042'], $lone->id, true);
        },
        // Back office (A6.1): provider categories (68) and AI and credits (69).
        'provider-categories.store' => fn () => auditCase(auditOps(), 'POST', '/admin/provider-categories', ['name' => 'Stager', 'prices' => [['state' => 'TX', 'tier' => 'basic', 'monthly' => 19]]], confirmed: true),
        'provider-categories.update' => function () {
            $ops = auditOps();
            $id = ProviderCategory::query()->where('key', 'photographer')->value('id');

            return auditCase($ops, 'PATCH', "/admin/provider-categories/{$id}", ['areas_on_pro' => 5], confirmed: true);
        },
        'ai.update' => function () {
            $ops = auditOps();
            $settings = app(AiSettings::class)->current();
            $settings['hourly_limit'] = 50;

            return auditCase($ops, 'PUT', '/admin/ai', $settings, confirmed: true);
        },
        // Features and releases (A6.2, screen 70).
        'features.rollout' => fn () => auditCase(auditOps(), 'PUT', '/admin/features/messages/rollout', ['stage' => 'everyone', 'tenant_ids' => [], 'states' => [], 'percent' => 0, 'roles' => [], 'error_threshold' => 5]),
        'features.kill' => fn () => auditCase(auditOps(), 'POST', '/admin/features/messages/kill'),
        'features.restore' => function () {
            $ops = auditOps();
            app(Rollouts::class)->kill(app(Flags::class)->flag('messages'), $ops);

            return auditCase($ops, 'DELETE', '/admin/features/messages/kill');
        },
        'releases.store' => fn () => auditCase(auditOps(), 'POST', '/admin/releases', ['version' => '6.2.0', 'released_on' => now()->toDateString(), 'notes' => ['agent' => 'Messages are here.'], 'flags' => []]),
        'gateways.refund-policy' => fn () => auditCase(auditOps(), 'PUT', '/admin/gateways/refund-policy', ['mode' => 'full_only', 'window_days' => 7, 'max_progress_percent' => 25], confirmed: true),
        // Refunds from REOS (B5.3, screen 66).
        'gateways.refund' => function () {
            $ops = auditOps();
            $plan = auditPlan($ops);
            app(Plans::class)->setPrice($plan, 'TX', PriceInterval::Once, 2900, null, $ops);
            $buyer = User::factory()->create();
            $order = Order::query()->create([
                'uuid' => (string) Str::uuid(), 'user_id' => $buyer->id, 'product_id' => $plan->id, 'price_id' => $plan->prices()->firstOrFail()->id,
                'state_code' => 'TX', 'amount_cents' => 2900, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => 2900,
                'status' => 'paid', 'gateway' => 'stripe', 'environment' => 'simulator', 'gateway_payment_id' => 'sim_pi_1', 'paid_at' => now(),
            ]);

            return auditCase($ops, 'POST', "/admin/gateways/orders/{$order->uuid}/refund", ['amount' => '5', 'reason' => 'Goodwill'], confirmed: true);
        },
        'tenants.codes.store' => function () {
            [$lone] = auditBrokerage();
            $ops = auditOps();
            $plan = auditPlan($ops);
            app(Plans::class)->setPrice($plan, 'TX', PriceInterval::Month, 2900, null, $ops);
            app(Plans::class)->setStatus($plan, ProductStatus::Live, $ops);

            return auditCase($ops, 'POST', "/admin/tenants/{$lone->id}/codes", ['product_id' => $plan->id, 'state' => 'TX', 'seats' => 5], $lone->id, true);
        },
        'tenants.codes.destroy' => function () {
            [$lone] = auditBrokerage();
            $ops = auditOps();
            $code = app(SponsorCodes::class)->create($lone, auditPlan($ops), 'TX', 5, null, null, $ops);

            return auditCase($ops, 'DELETE', "/admin/tenants/{$lone->id}/codes/{$code->id}", tenant: $lone->id, confirmed: true);
        },
        'states.checklist' => fn () => auditCase(auditOps(), 'PUT', '/admin/states/LA/checklist', ['item' => 'rules', 'done' => true], confirmed: true),
        'people.licences.approve' => function () {
            [$licence] = app(Licences::class)->add(User::factory()->create(['name' => 'James Miller']), State::query()->findOrFail('TX'), '0798888', null, null);

            return auditCase(auditOps(), 'POST', "/admin/people/licences/{$licence->id}/approve", confirmed: true);
        },
        'people.licences.reject' => function () {
            [$licence] = app(Licences::class)->add(User::factory()->create(['name' => 'James Miller']), State::query()->findOrFail('TX'), '0798888', null, null);

            return auditCase(auditOps(), 'POST', "/admin/people/licences/{$licence->id}/reject", ['note' => 'Not on the TREC record.'], confirmed: true);
        },
        'tenants.store' => fn () => auditCase(auditOps(), 'POST', '/admin/tenants', ['name' => 'Gulf Coast Homes', 'type' => 'brokerage', 'states' => ['TX'], 'plan' => 'brokerage', 'seats' => 40], confirmed: true),
        'tenants.update' => function () {
            [$lone] = auditBrokerage();

            return auditCase(auditOps(), 'PATCH', "/admin/tenants/{$lone->id}", ['plan' => 'enterprise'], $lone->id, confirmed: true);
        },
        'tenants.status' => function () {
            [$lone] = auditBrokerage();

            return auditCase(auditOps(), 'PUT', "/admin/tenants/{$lone->id}/status", ['status' => 'suspended', 'reason' => 'Unpaid since October'], $lone->id, confirmed: true);
        },
        'tenants.support.store' => function () {
            [$lone] = auditBrokerage();

            return auditCase(auditOps(), 'POST', "/admin/tenants/{$lone->id}/support", ['reason' => 'Linda asked us to check her calendar sync'], $lone->id, confirmed: true);
        },
        'tenants.support.start' => function () {
            [$lone, $linda] = auditBrokerage();
            $ops = auditOps();
            auditGrant($lone, $ops, approve: true, linda: $linda);

            return auditCase($ops, 'POST', "/admin/tenants/{$lone->id}/support/start", tenant: $lone->id, confirmed: true);
        },

        // Super admin: privacy requests on Compliance and audit (screen 71).
        'compliance.reports.resolve' => function () {
            $report = MessageReport::query()->create(['uuid' => (string) Str::uuid7(), 'reason' => 'spam', 'messages' => [], 'status' => 'open']);

            return auditCase(auditOps(), 'PATCH', "/admin/compliance/message-reports/{$report->uuid}", confirmed: true);
        },
        'compliance.privacy.update' => function () {
            $person = User::factory()->create();
            $request = app(PrivacyRequests::class)->submit($person, PrivacyRequest::CORRECT, 'My last name is Lima, not Lim.');

            return auditCase(auditOps(), 'PATCH', "/admin/compliance/privacy/{$request->id}", ['action' => 'extend', 'note' => 'We are checking with your brokerage.'], confirmed: true);
        },

        // Tenant owners: support consent (A4.1c).
        'support-access.approve' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'POST', '/support-access/'.auditGrant($lone, auditOps())->id.'/approve', tenant: $lone->id);
        },
        'support-access.decline' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'POST', '/support-access/'.auditGrant($lone, auditOps())->id.'/decline', tenant: $lone->id);
        },
        'support-access.revoke' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'POST', '/support-access/'.auditGrant($lone, auditOps(), approve: true, linda: $linda)->id.'/revoke', tenant: $lone->id);
        },
        'support-session.destroy' => function () {
            [$lone, $linda] = auditBrokerage();
            $ops = auditOps();
            auditGrant($lone, $ops, approve: true, linda: $linda);
            test()->actingAs($ops)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/tenants/{$lone->id}/support/start");

            return auditCase($ops, 'DELETE', '/support-session', tenant: $lone->id);
        },

        // Tenant admins: Branding and domain (screen 57), Brokerage settings (41).
        'branding.update' => function () {
            [$texas, $karen] = auditAssociation();

            return auditCase($karen, 'PUT', '/organisation/branding', ['primary' => '#1E3A8A'], $texas->id);
        },
        'branding.logo' => function () {
            Storage::fake();
            [$texas, $karen] = auditAssociation();

            return auditCase($karen, 'POST', '/organisation/branding/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)], $texas->id);
        },
        'branding.logo.destroy' => function () {
            Storage::fake();
            [$texas, $karen] = auditAssociation();
            test()->actingAs($karen)->post('/organisation/branding/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)]);

            return auditCase($karen, 'DELETE', '/organisation/branding/logo', tenant: $texas->id);
        },
        'domain.store' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'POST', '/organisation/domain', ['hostname' => 'learn.lonestarrealty.com'], $lone->id);
        },
        'domain.verify' => function () {
            [$lone, $linda] = auditBrokerage();
            app(TenantDomains::class)->add($lone, 'learn.lonestarrealty.com', $linda);

            return auditCase($linda, 'POST', '/organisation/domain/verify', tenant: $lone->id);
        },
        'domain.destroy' => function () {
            [$lone, $linda] = auditBrokerage();
            app(TenantDomains::class)->add($lone, 'learn.lonestarrealty.com', $linda);

            return auditCase($linda, 'DELETE', '/organisation/domain', tenant: $lone->id);
        },
        'sso.update' => function () {
            [$texas, $karen] = auditAssociation();

            return auditCase($karen, 'PUT', '/organisation/sso', ['protocol' => 'oidc', 'issuer' => 'https://test-idp.reos.invalid', 'client_id' => 'reos-test', 'client_secret' => 'reos-test-secret'], $texas->id);
        },
        'sso.test-provider' => function () {
            [$texas, $karen] = auditAssociation();

            return auditCase($karen, 'POST', '/organisation/sso/test-provider', ['protocol' => 'saml'], $texas->id);
        },
        'sso.test' => function () {
            [$texas, $karen] = auditAssociation();
            app(SingleSignOn::class)->useTestProvider($texas, SsoProtocol::Saml, $karen);

            return auditCase($karen, 'POST', '/organisation/sso/test', tenant: $texas->id);
        },
        'sso.enable' => function () {
            [$texas, $karen] = auditAssociation();
            app(SingleSignOn::class)->useTestProvider($texas, SsoProtocol::Saml, $karen);
            app(SingleSignOn::class)->recordTest($texas, true, 'ok', $karen);

            return auditCase($karen, 'POST', '/organisation/sso/enable', tenant: $texas->id);
        },
        'sso.disable' => function () {
            [$texas, $karen] = auditAssociation();
            $sso = app(SingleSignOn::class);
            $sso->useTestProvider($texas, SsoProtocol::Saml, $karen);
            $sso->recordTest($texas, true, 'ok', $karen);
            $sso->enable($texas, $karen);

            return auditCase($karen, 'POST', '/organisation/sso/disable', tenant: $texas->id);
        },
        'invites.store' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'POST', '/organisation/invites', ['mode' => 'one', 'contact' => 'rosa@example.com', 'role' => 'agent', 'locale' => 'en'], $lone->id);
        },
        'invites.resend' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'POST', '/organisation/invites/'.auditInvitation($lone, $linda)->id.'/resend', tenant: $lone->id);
        },
        'invites.destroy' => function () {
            [$lone, $linda] = auditBrokerage();

            return auditCase($linda, 'DELETE', '/organisation/invites/'.auditInvitation($lone, $linda)->id, tenant: $lone->id);
        },
        // School: Sales and payouts (B5.2b, screen 53).
        'sales.connect' => function () {
            $school = Organisation::factory()->ofType(OrganisationType::School)->create();
            $paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $school)->create();

            return auditCase($paul, 'POST', '/organisation/sales/connect', tenant: $school->id);
        },
        'sales.connect-own' => function () {
            $school = Organisation::factory()->ofType(OrganisationType::School)->create();
            $paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $school)->create();
            GatewaySetting::query()->create(['gateway' => 'stripe', 'is_default' => true, 'tenant_accounts' => true]);

            return auditCase($paul, 'POST', '/organisation/sales/own-account', tenant: $school->id);
        },
        // School: course approvals (A7.1, screen 50).
        'school.approvals.store' => function () {
            [$school, $paul, $course] = auditSchoolCourse();

            return auditCase($paul, 'POST', '/school/approvals', ['course_id' => $course->id, 'state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451'], tenant: $school->id);
        },
        'school.approvals.update' => function () {
            [$school, $paul, $course] = auditSchoolCourse();
            $approval = $course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);

            return auditCase($paul, 'PATCH', "/school/approvals/{$approval->id}", ['expires_on' => '2029-08-31'], tenant: $school->id);
        },
        'school.approvals.status' => function () {
            [$school, $paul, $course] = auditSchoolCourse();
            $approval = $course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);

            return auditCase($paul, 'PUT', "/school/approvals/{$approval->id}/status", ['status' => 'withdrawn'], tenant: $school->id);
        },
        // School: instructors (A7.1b, screen 52).
        'school.instructors.courses' => function () {
            [$school, $paul, $course] = auditSchoolCourse();
            $rachel = User::factory()->withRole(Role::Instructor, $school)->create();

            return auditCase($paul, 'PUT', "/school/instructors/{$rachel->id}/courses", ['courses' => [$course->id]], tenant: $school->id);
        },
        'school.instructors.twins' => function () {
            [$school, $paul] = auditSchoolCourse();
            $rachel = User::factory()->withRole(Role::Instructor, $school)->create();
            app(Tenancy::class)->run($school->id, fn () => app(AvatarConsents::class)->give($rachel, $school, UploadedFile::fake()->create('sample.mp4', 4_000, 'video/mp4'), ConsentMethod::Web));

            return auditCase($paul, 'PUT', "/school/instructors/{$rachel->id}/twins", ['heygen' => 'twin_01'], tenant: $school->id);
        },
        'school.instructors.revoke' => function () {
            [$school, $paul] = auditSchoolCourse();
            $rachel = User::factory()->withRole(Role::Instructor, $school)->create();

            return auditCase($paul, 'DELETE', "/school/instructors/{$rachel->id}", tenant: $school->id);
        },
        'school.instructors.restore' => function () {
            [$school, $paul] = auditSchoolCourse();
            $rachel = User::factory()->create();

            return auditCase($paul, 'POST', "/school/instructors/{$rachel->id}/restore", tenant: $school->id);
        },
        // The course builder (A7.2, screen 43): an instructor who teaches the course.
        'builder.store' => function () {
            [$school, , , $rachel] = auditBuilder();

            return auditCase($rachel, 'POST', '/course-builder', ['title' => 'Legal Update I', 'type' => 'ce', 'delivery' => 'self_paced'], tenant: $school->id);
        },
        'builder.status' => function () {
            [$school, $paul, $course] = auditBuilder();
            $course->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451', 'status' => 'approved']);

            return auditCase($paul, 'PUT', "/course-builder/{$course->id}/status", ['status' => 'live'], tenant: $school->id);
        },
        'builder.settings' => function () {
            [$school, , $course, $rachel] = auditBuilder();

            return auditCase($rachel, 'PUT', "/course-builder/{$course->id}/settings", ['language' => 'both', 'lesson_minutes' => 10, 'presenter' => 'stock'], tenant: $school->id);
        },
        'builder.seat-time' => function () {
            [$school, , $course, $rachel] = auditBuilder();

            return auditCase($rachel, 'POST', "/course-builder/{$course->id}/seat-time", ['hours' => 32, 'reason' => 'New contract forms.'], tenant: $school->id);
        },
        'builder.modules.store' => function () {
            [, , $course, $rachel] = auditBuilder();

            return auditCase($rachel, 'POST', "/course-builder/{$course->id}/modules", ['title' => 'Agency']);
        },
        'builder.modules.update' => function () {
            [, , , $rachel, $module] = auditBuilder();

            return auditCase($rachel, 'PATCH', "/course-builder/modules/{$module->id}", ['title' => 'Agency in Texas']);
        },
        'builder.modules.destroy' => function () {
            [, , , $rachel, $module] = auditBuilder();

            return auditCase($rachel, 'DELETE', "/course-builder/modules/{$module->id}");
        },
        'builder.sources.store' => function () {
            Storage::fake(config()->string('reos.content.disk'));
            [$school, , , $rachel, $module] = auditBuilder();

            return auditCase($rachel, 'POST', "/course-builder/modules/{$module->id}/sources", ['role' => 'script', 'name' => 'script.pdf', 'size' => 2048], tenant: $school->id);
        },
        'builder.sources.complete' => function () {
            [$school, , , $rachel, $module] = auditBuilder();
            Storage::fake(config()->string('reos.content.disk'));
            $source = $module->sources()->create(['role' => 'captions', 'kind' => 'captions', 'path' => "tenants/{$school->id}/courses/x.vtt", 'original_name' => 'x.vtt', 'mime' => 'text/vtt', 'size' => 8, 'status' => 'awaiting_upload']);
            LessonFiles::disk()->put($source->path, 'WEBVTT\n');

            return auditCase($rachel, 'POST', "/course-builder/sources/{$source->id}/complete", tenant: $school->id);
        },
        'builder.sources.destroy' => function () {
            Storage::fake(config()->string('reos.content.disk'));
            [$school, , , $rachel, $module] = auditBuilder();
            $source = $module->sources()->create(['role' => 'script', 'kind' => 'document', 'path' => "tenants/{$school->id}/courses/x.pdf", 'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 8, 'status' => 'ready']);

            return auditCase($rachel, 'DELETE', "/course-builder/sources/{$source->id}", tenant: $school->id);
        },
        'builder.lessons.store' => function () {
            [, , , $rachel, $module] = auditBuilder();

            return auditCase($rachel, 'POST', "/course-builder/modules/{$module->id}/lessons", ['title' => 'Who you represent']);
        },
        'builder.lessons.update' => function () {
            [, , , $rachel, , $lesson] = auditBuilder();

            return auditCase($rachel, 'PATCH', "/course-builder/lessons/{$lesson->id}", ['title' => 'Who you represent in Texas']);
        },
        'builder.lessons.move' => function () {
            [$school, , $course, $rachel, $module, $lesson] = auditBuilder();
            Lesson::query()->create(['course_id' => $course->id, 'course_module_id' => $module->id, 'title' => 'Second', 'position' => 2, 'review_status' => 'draft']);

            return auditCase($rachel, 'POST', "/course-builder/lessons/{$lesson->id}/move", ['direction' => 'down'], tenant: $school->id);
        },
        // AI lessons (A7.3): generate a module, generate a lesson again.
        'builder.modules.generate' => function () {
            [$school, , , $rachel, $module] = auditAiModule();

            return auditCase($rachel, 'POST', "/course-builder/modules/{$module->id}/generate", tenant: $school->id);
        },
        'builder.lessons.regenerate' => function () {
            [$school, , $course, $rachel, $module] = auditAiModule();
            app(LessonPipeline::class)->generate($course, $module, 15, $rachel);
            app(LessonPipeline::class)->checkRenders();

            return auditCase($rachel, 'POST', '/course-builder/lessons/'.$module->lessons()->whereHas('versions')->sole()->id.'/regenerate', tenant: $school->id);
        },
        'builder.lessons.approve' => function () {
            [$school, , , $rachel, , $lesson] = auditBuilder();

            return auditCase($rachel, 'POST', "/course-builder/lessons/{$lesson->id}/approve", tenant: $school->id);
        },
        'builder.lessons.destroy' => function () {
            [, , , $rachel, , $lesson] = auditBuilder();

            return auditCase($rachel, 'DELETE', "/course-builder/lessons/{$lesson->id}");
        },
        'builder.lessons.restore' => function () {
            [, , , $rachel, , $lesson] = auditBuilder();
            $lesson->delete();

            return auditCase($rachel, 'POST', "/course-builder/lessons/{$lesson->id}/restore");
        },
        'plans.splits' => function () {
            $ops = auditOps();
            $plan = auditPlan($ops);
            $school = Organisation::factory()->ofType(OrganisationType::School)->create();
            $plan->forceFill(['seller_organisation_id' => $school->id])->save();
            $rachel = User::factory()->withRole(Role::Instructor, $school)->create();

            return auditCase($ops, 'PUT', "/admin/plans/{$plan->id}/splits", ['splits' => [['email' => $rachel->email, 'role' => 'instructor', 'percent' => 15]]], confirmed: true);
        },
        'seats.request' => function () {
            [$lone, $linda] = auditBrokerage();
            auditOps();

            return auditCase($linda, 'POST', '/organisation/seats/request', tenant: $lone->id);
        },
    ];
}

/** Admin routes that change something: admin areas, tenant settings and consent screens. */
function adminMutatingRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(static function (LaravelRoute $route): bool {
            $middleware = implode(' ', $route->gatherMiddleware());

            return array_diff($route->methods(), ['GET', 'HEAD']) !== []
                && (str_contains($middleware, EnsureAdminTwoStep::class) || str_contains($middleware, 'can:') || str_starts_with($route->uri(), 'admin/')
                    || str_starts_with($route->uri(), 'support-access/') || $route->uri() === 'support-session');
        })
        ->map(static fn (LaravelRoute $route): string => (string) $route->getName())
        ->sort()->values()->all();
}

beforeEach(fn () => Notification::fake());

it('knows every admin route that changes something', function () {
    expect(adminMutatingRoutes())->toEqualCanonicalizing(array_keys(adminActions()));
});

it('audits the admin action', function (string $name) {
    $case = adminActions()[$name]($this);
    $before = (int) AuditEvent::query()->max('id');

    $request = $this->actingAs($case['actor'])->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);

    if ($case['confirmed']) {
        $request = $request->withSession(['auth.password_confirmed_at' => time()]);
    }

    $response = $request->call($case['method'], $case['uri'], $case['data']);

    expect($response->status())->toBeLessThan(400, "{$name} answered {$response->status()}");
    $rows = AuditEvent::query()->where('id', '>', $before)->get();
    expect($rows)->not->toBeEmpty("{$name} wrote no audit row");

    $row = $rows->firstWhere('actor_id', $case['actor']->id);
    expect($row)->not->toBeNull("{$name}: no audit row names the person who did it")
        ->and($row?->ip_address)->toBe('203.0.113.7');

    if ($case['tenant'] !== null) {
        expect($rows->pluck('organisation_id')->all())->toContain($case['tenant']);
    }

    // Changes to a value carry what it was and what it became.
    if (in_array($name, CHANGES, true)) {
        $props = $row?->properties ?? [];
        expect((array_key_exists('before', $props) && array_key_exists('after', $props)) || (array_key_exists('from', $props) && array_key_exists('to', $props)))
            ->toBeTrue("{$name}: no before and after values");
    }
})->with(array_keys(adminActions()));

/** Routes that change a value (rather than add, remove or start something). */
const CHANGES = [
    'integrations.mode.update', 'integrations.catalogue.update', 'integrations.pause', 'states.update', 'states.prepare', 'states.rules.publish',
    'tenants.update', 'tenants.status', 'branding.update', 'sso.update', 'sso.test-provider', 'invites.destroy',
    'compliance.privacy.update', 'content.courses.update', 'content.courses.status', 'content.modules.update', 'plans.update', 'plans.status', 'plans.prices.store', 'plans.prices.destroy', 'plans.ios-buy-link', 'tenants.codes.destroy',
    'gateways.update', 'gateways.pause', 'gateways.resume', 'plans.splits', 'tenants.pool.fund', 'gateways.refund-policy', 'provider-categories.update', 'ai.update',
    'features.rollout', 'features.kill', 'features.restore', 'releases.store', 'people.consents.update', 'compliance.reports.resolve',
];
