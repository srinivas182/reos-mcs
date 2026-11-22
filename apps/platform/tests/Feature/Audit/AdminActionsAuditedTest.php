<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\MediaKind;
use App\Enums\OrganisationType;
use App\Http\Middleware\EnsureAdminTwoStep;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\Organisation;
use App\Models\PrivacyRequest;
use App\Models\State;
use App\Models\SupportAccessGrant;
use App\Models\User;
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
    'compliance.privacy.update', 'content.courses.update', 'content.courses.status', 'content.modules.update',
];
