<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AgentPage;
use App\Models\AuditEvent;
use App\Models\CeCompletion;
use App\Models\ComplianceEvent;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invitation;
use App\Models\MentorAssignment;
use App\Models\Organisation;
use App\Models\Pod;
use App\Models\PodMember;
use App\Models\SocialPost;
use App\Models\State;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Services\Licences\Licences;
use App\Services\Tenancy\Invitations;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Switching brokerage or team (A14.3, ADR 0063): the agent's own data moves
 * with them; the old brokerage keeps its records; what was the old
 * brokerage's ends.
 */

beforeEach(function () {
    Notification::fake();
    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'slug' => 'lone-star', 'limits' => ['seats' => 25, 'payer' => 'organisation']]);
    $this->gulf = Organisation::factory()->create(['name' => 'Gulf Coast Homes', 'slug' => 'gulf-coast', 'limits' => ['seats' => 25, 'payer' => 'organisation']]);
    $this->dana = User::factory()->withRole(Role::Broker, $this->gulf)->withTwoFactor()->create(['name' => 'Dana Brooks']);
    $this->mentor = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Ana Ruiz']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Maria Garcia', 'email' => 'maria@example.com']);

    // Maria's own things…
    [$licence] = app(Licences::class)->add($this->maria, State::query()->findOrFail('TX'), '0712345', null, null);
    CeCompletion::query()->create(['user_id' => $this->maria->id, 'state_code' => 'TX', 'title' => 'Legal Update I', 'course_code' => 'LU1', 'hours' => 4, 'source' => 'reos', 'completed_on' => '2026-10-01']);
    CeCompletion::query()->create(['user_id' => $this->maria->id, 'state_code' => 'TX', 'title' => 'Contracts', 'course_code' => null, 'hours' => 3, 'source' => 'reos', 'completed_on' => '2026-10-15']);
    Contact::query()->create(['uuid' => (string) Str::uuid7(), 'owner_type' => 'user', 'owner_id' => $this->maria->id, 'first_name' => 'Rosa', 'last_name' => 'Lima', 'stage' => 'lead']);
    $this->waiting = SocialPost::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->maria->id, 'body' => 'Just listed in Katy', 'attribution' => 'Maria Garcia, Lone Star Realty', 'status' => 'approval', 'approval_requested_at' => now()]);
    (new AgentPage)->forceFill(['user_id' => $this->maria->id, 'handle' => 'maria-garcia', 'published' => true, 'approval_requested_at' => now()])->save();

    // …and Lone Star's.
    app(Tenancy::class)->run($this->lone->id, function () {
        Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->maria->id, 'organisation_id' => $this->lone->id, 'source' => 'reos', 'property' => '12 Willow Bend', 'side' => 'sell', 'stage' => 'closed', 'parties' => [], 'documents' => []]);
        ComplianceEvent::query()->create(['organisation_id' => $this->lone->id, 'user_id' => $this->maria->id, 'actor_id' => $this->maria->id, 'channel' => 'post', 'action' => 'checked', 'issues' => []]);
        MentorAssignment::query()->create(['organisation_id' => $this->lone->id, 'mentor_id' => $this->mentor->id, 'agent_id' => $this->maria->id, 'reasons' => ['language']]);
        $pod = Pod::query()->create(['uuid' => (string) Str::uuid7(), 'name' => 'Katy closers', 'organisation_id' => $this->lone->id, 'state_code' => 'TX', 'goal' => 'grow', 'language' => 'en', 'part_time' => false, 'timezone' => 'America/Chicago']);
        PodMember::query()->create(['pod_id' => $pod->id, 'user_id' => $this->maria->id, 'share' => [], 'joined_at' => now()]);
    });

    app(Invitations::class)->invite($this->gulf, $this->dana, [['email' => 'maria@example.com', 'phone' => null, 'name' => 'Maria Garcia']], Role::Agent, null, 'en');
    $this->invite = app(Tenancy::class)->asPlatform(fn () => Invitation::query()->where('email', 'maria@example.com')->sole());
    unset($licence);
});

describe('switching brokerage (A14.3)', function () {
    it('shows what moves, stays and ends, then moves the agent without losing CE (the done-when)', function () {
        $link = app(Invitations::class)->link($this->invite);

        $this->actingAs($this->maria)->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/invite')
            ->where('move.kind', 'brokerage')
            ->where('move.from.name', 'Lone Star Realty')
            ->where('move.to.name', 'Gulf Coast Homes')
            ->where('move.moves', [['key' => 'licenses', 'count' => 1], ['key' => 'ce_hours', 'count' => 7], ['key' => 'certificates', 'count' => 2], ['key' => 'contacts', 'count' => 1], ['key' => 'posts', 'count' => 1]])
            ->where('move.stays', [['key' => 'deals', 'count' => 1], ['key' => 'compliance', 'count' => 1]])
            ->where('move.ends', [['key' => 'mentor', 'count' => 1], ['key' => 'pods', 'count' => 1], ['key' => 'approvals', 'count' => 2]]));

        // Joining needs the agent's confirmation.
        $this->post($link.'/accept')->assertSessionHasErrors('move');
        expect(app(Memberships::class)->find($this->maria, Role::Agent, $this->gulf->id))->toBeNull();

        $this->post($link.'/accept', ['move' => '1'])->assertRedirect('/dashboard');

        $memberships = app(Memberships::class);
        expect($memberships->find($this->maria, Role::Agent, $this->gulf->id))->not->toBeNull()
            ->and($memberships->find($this->maria, Role::Agent, $this->lone->id))->toBeNull()
            // CE, contacts and posts are still Maria's.
            ->and((float) CeCompletion::query()->where('user_id', $this->maria->id)->sum('hours'))->toBe(7.0)
            ->and(Contact::query()->where('owner_type', 'user')->where('owner_id', $this->maria->id)->count())->toBe(1)
            // Waiting for Lone Star's broker: back to a draft; the page's request withdrawn.
            ->and($this->waiting->refresh()->status)->toBe('draft')
            ->and(AgentPage::query()->where('user_id', $this->maria->id)->sole()->approval_requested_at)->toBeNull();

        // Lone Star keeps its records; its mentor and pod end.
        app(Tenancy::class)->run($this->lone->id, function () {
            expect(Deal::query()->where('organisation_id', $this->lone->id)->count())->toBe(1)
                ->and(ComplianceEvent::query()->where('organisation_id', $this->lone->id)->count())->toBe(1)
                ->and(MentorAssignment::query()->sole()->ended_at)->not->toBeNull()
                ->and(PodMember::query()->sole()->left_at)->not->toBeNull();
        });

        expect(app(Tenancy::class)->asPlatform(fn () => AuditEvent::query()->where('action', 'agent.moved')->pluck('organisation_id')->sort()->values()->all()))->toBe([$this->lone->id, $this->gulf->id]);
        // The CE wallet still shows the hours.
        $this->getJson('/api/v1/me/licences')->assertOk();
    });

    it('joins from the phone with the invite code', function () {
        $code = $this->invite->secret['code'];

        $this->actingAs($this->maria)->postJson('/api/v1/me/moves/preview', ['code' => $code])->assertOk()
            ->assertJsonPath('data.tenant', 'Gulf Coast Homes')
            ->assertJsonPath('data.role', 'agent')
            ->assertJsonPath('data.move.kind', 'brokerage')
            ->assertJsonPath('data.move.moves.1', ['key' => 'ce_hours', 'count' => 7]);

        $this->postJson('/api/v1/me/moves', ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('move');
        $this->postJson('/api/v1/me/moves', ['code' => $code, 'confirm' => true])->assertOk()->assertJsonPath('data.organisation', 'Gulf Coast Homes');
        expect(app(Memberships::class)->find($this->maria, Role::Agent, $this->lone->id))->toBeNull();

        // A used or unknown code, or someone else's, is turned away.
        $this->postJson('/api/v1/me/moves/preview', ['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/me/moves/preview', ['code' => 'ZZZZ-ZZZZ'])->assertUnprocessable();
        app(Invitations::class)->invite($this->gulf, $this->dana, [['email' => 'rosa@example.com', 'phone' => null, 'name' => 'Rosa']], Role::Agent, null, 'en');
        $rosa = app(Tenancy::class)->asPlatform(fn () => Invitation::query()->where('email', 'rosa@example.com')->sole());
        $this->postJson('/api/v1/me/moves/preview', ['code' => $rosa->secret['code']])->assertUnprocessable()->assertJsonValidationErrors('code');
    });

    it('changes team inside the same brokerage without ending anything', function () {
        $team = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'The Katy Team', 'slug' => 'katy-team', 'parent_id' => $this->lone->id]);
        $linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
        app(Invitations::class)->invite($this->lone, $linda, [['email' => 'maria@example.com', 'phone' => null, 'name' => 'Maria']], Role::Agent, $team, 'en');
        $invite = app(Tenancy::class)->asPlatform(fn () => Invitation::query()->where('team_id', $team->id)->sole());

        $preview = $this->actingAs($this->maria)->postJson('/api/v1/me/moves/preview', ['code' => $invite->secret['code']])->assertOk()->json('data.move');
        expect($preview)->toMatchArray(['kind' => 'team', 'stays' => [], 'ends' => []]);

        $this->postJson('/api/v1/me/moves', ['code' => $invite->secret['code'], 'confirm' => true])->assertOk();
        expect(app(Memberships::class)->find($this->maria, Role::Agent, $team->id))->not->toBeNull()
            ->and(app(Memberships::class)->find($this->maria, Role::Agent, $this->lone->id))->toBeNull()
            ->and($this->waiting->refresh()->status)->toBe('approval');
        app(Tenancy::class)->run($this->lone->id, fn () => expect(MentorAssignment::query()->sole()->ended_at)->toBeNull());
    });

    it('joins without a move when the person is not an agent anywhere', function () {
        $rosa = User::factory()->create(['email' => 'rosa@example.com']);
        app(Invitations::class)->invite($this->gulf, $this->dana, [['email' => 'rosa@example.com', 'phone' => null, 'name' => 'Rosa']], Role::Agent, null, 'en');
        $invite = app(Tenancy::class)->asPlatform(fn () => Invitation::query()->where('email', 'rosa@example.com')->sole());

        $this->actingAs($rosa)->postJson('/api/v1/me/moves/preview', ['code' => $invite->secret['code']])->assertJsonPath('data.move', null);
        $this->postJson('/api/v1/me/moves', ['code' => $invite->secret['code']])->assertOk();
        expect(app(Memberships::class)->find($rosa, Role::Agent, $this->gulf->id))->not->toBeNull();
    });
});
