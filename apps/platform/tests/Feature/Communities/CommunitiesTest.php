<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Communities\Communities;
use App\Enums\OrganisationType;
use App\Enums\StateStatus;
use App\Models\AuditEvent;
use App\Models\CommunityEvent;
use App\Models\CommunityRsvp;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\State;
use App\Models\User;
use App\Notifications\CommunitySessionPosted;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Communities (B17.2, ADR 0086): verified members, checked posts, expert
 * sessions with RSVP, invites by link and QR, and results.
 */

function communityHost(string $name, string $tier): User
{
    $user = User::factory()->withRole(Role::Provider)->create(['name' => $name]);
    $business = Organisation::factory()->ofType(OrganisationType::ProviderBusiness)->create(['name' => $name.' Lending']);
    ProviderProfile::query()->create([
        'user_id' => $user->id, 'provider_category_id' => ProviderCategory::query()->where('key', 'loan-officer')->value('id'), 'organisation_id' => $business->id,
        'status' => ProviderProfile::LIVE, 'live_at' => now(), 'tier' => $tier, 'business_name' => $name.' Lending', 'welcomed_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    State::query()->whereKey('TX')->update(['status' => StateStatus::Live]);
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->maya = communityHost('Maya Chen', 'pro');
    $this->own = fn () => $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/community')->assertOk();
    $this->code = fn () => basename(($this->own)()->json('data.invite_url'));
    $this->session = fn (array $extra = []) => $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/community/sessions', [
        'title' => 'Reading a loan estimate with your buyers', 'about' => 'What each line means and the questions buyers ask.', 'format' => 'online',
        'starts_at' => '2026-12-02T17:00:00Z', 'minutes' => 60, 'join_url' => 'https://meet.example.com/maya', 'price_cents' => 0, ...$extra,
    ]);
});

it('runs an expert session for 18 agents (the done-when)', function () {
    $code = ($this->code)();
    $agents = User::factory()->count(18)->withRole(Role::Agent, $this->lone)->create();
    foreach ($agents as $i => $agent) {
        $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/join/{$code}", ['via' => $i % 3 === 0 ? 'qr' : 'link'])->assertOk();
    }

    $id = ($this->session)()->assertCreated()->json('data.id');
    Notification::assertSentTo($agents, CommunitySessionPosted::class);
    $community = ($this->own)()->json('data.id');

    foreach ($agents as $agent) {
        $this->actingAs($agent, 'sanctum')->postJson("/api/v1/community-sessions/{$id}/rsvp")->assertOk()->assertJsonPath('data.status', 'going');
    }
    $this->actingAs($agents[0], 'sanctum')->getJson("/api/v1/communities/{$community}")->assertOk()
        ->assertJsonPath('data.sessions.0.going', 18)->assertJsonPath('data.sessions.0.join_url', 'https://meet.example.com/maya')
        ->assertJsonPath('data.sessions.0.ce_course', null)->assertJsonPath('data.invite_url', null);

    $this->travelTo(CarbonImmutable::parse('2026-12-02 13:00', 'America/Chicago'));
    $this->actingAs($this->maya, 'sanctum')->getJson("/api/v1/community/sessions/{$id}/roster")->assertOk()->assertJsonCount(18, 'data');
    $this->actingAs($this->maya, 'sanctum')->putJson("/api/v1/community/sessions/{$id}/attendance", ['user_ids' => $agents->pluck('id')->all()])->assertOk();

    $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/community/results')->assertOk()
        ->assertJsonPath('data.members', 18)->assertJsonPath('data.via.qr', 6)->assertJsonPath('data.via.link', 12)
        ->assertJsonPath('data.rsvps', 18)->assertJsonPath('data.attended', 18)->assertJsonPath('data.show_rate', 100)
        ->assertJsonPath('data.top.0.attended', 18);
    expect(CommunityEvent::query()->where('uuid', $id)->sole()->status)->toBe('done')
        ->and(AuditEvent::query()->where('action', 'community.attendance')->exists())->toBeTrue();
});

it('lets only verified agents and providers join', function () {
    $code = ($this->code)();
    $student = User::factory()->withRole(Role::Candidate)->create();
    $this->actingAs($student, 'sanctum')->getJson("/api/v1/communities/join/{$code}")->assertOk()->assertJsonPath('data.verified', false);
    $this->actingAs($student, 'sanctum')->postJson("/api/v1/communities/join/{$code}")->assertUnprocessable()->assertJsonValidationErrors('code');

    // A new link turns the old one off; members only see the community.
    $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/community/code')->assertOk();
    $agent = User::factory()->withRole(Role::Agent, $this->lone)->create();
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/join/{$code}")->assertNotFound();
    $community = ($this->own)()->json('data.id');
    $this->actingAs($agent, 'sanctum')->getJson("/api/v1/communities/{$community}")->assertNotFound();

    // Listed providers can't lead a community.
    $listed = communityHost('Lee Park', 'basic');
    $this->actingAs($listed, 'sanctum')->getJson('/api/v1/community')->assertUnprocessable()->assertJsonValidationErrors('plan');
});

it('checks posts, and keeps paid sessions for Partner', function () {
    $code = ($this->code)();
    $agent = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/join/{$code}")->assertOk();
    $community = ($this->own)()->json('data.id');

    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/{$community}/posts", ['body' => 'I pay a referral fee for every buyer you send.'])
        ->assertUnprocessable()->assertJsonValidationErrors(['body' => 'Communities can\'t be used to pay for referrals: remove "referral fee".']);
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/{$community}/posts", ['body' => 'Call me at 713-555-0101'])->assertUnprocessable();
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/{$community}/posts", ['body' => 'Perfect for families, this one!'])->assertUnprocessable();
    $post = $this->actingAs($agent, 'sanctum')->postJson("/api/v1/communities/{$community}/posts", ['body' => 'What do buyers ask most about escrow?'])->assertCreated()->json('data.id');
    $this->actingAs($this->maya, 'sanctum')->putJson("/api/v1/community-posts/{$post}/pin", ['pinned' => true])->assertOk();
    $this->actingAs($agent, 'sanctum')->getJson("/api/v1/communities/{$community}")->assertJsonPath('data.posts.0.pinned', true);

    // Pro: free only. Partner: a flat price through checkout.
    ($this->session)(['price_cents' => 2500])->assertUnprocessable()->assertJsonValidationErrors('price_cents');
    ProviderProfile::query()->where('user_id', $this->maya->id)->update(['tier' => 'premium']);
    $paid = ($this->session)(['price_cents' => 2500, 'capacity' => 1, 'ce_course' => 'TREC 41234'])->assertCreated()->json('data.id');
    $held = $this->actingAs($agent, 'sanctum')->postJson("/api/v1/community-sessions/{$paid}/rsvp")->assertOk()->assertJsonPath('data.status', 'held');
    expect($held->json('data.checkout_url'))->toContain('/checkout/');
    $other = User::factory()->withRole(Role::Agent, $this->lone)->create();
    $this->actingAs($other, 'sanctum')->postJson("/api/v1/communities/join/{$code}")->assertOk();
    $this->actingAs($other, 'sanctum')->postJson("/api/v1/community-sessions/{$paid}/rsvp")->assertUnprocessable()->assertJsonValidationErrors(['session' => 'This session is full.']);

    $event = CommunityEvent::query()->where('uuid', $paid)->sole();
    app(Communities::class)->paid((new Order)->forceFill(['product_id' => $event->product_id, 'user_id' => $agent->id]));
    expect(CommunityRsvp::query()->where('community_event_id', $event->id)->where('user_id', $agent->id)->value('status'))->toBe('going');
});

it('shows the community pages on the web', function () {
    $code = ($this->code)();
    $agent = User::factory()->withRole(Role::Agent, $this->lone)->create();
    $this->actingAs($agent)->get("/join/community/{$code}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('community/join')->where('invite.verified', true));
    $this->actingAs($agent)->postJson("/communities/join/{$code}", ['via' => 'qr'])->assertOk();
    $this->actingAs($agent)->get('/communities')->assertOk()->assertInertia(fn (Assert $page) => $page->component('community/mine')->has('communities', 1));
    $this->actingAs($this->maya)->get('/community')->assertOk()->assertInertia(fn (Assert $page) => $page->component('community/manage')->where('results.via.qr', 1));
    $this->actingAs($this->maya)->get('/community/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->actingAs($this->maya)->get('/community/results')->assertOk()->assertInertia(fn (Assert $page) => $page->component('community/results'));
});
