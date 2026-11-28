<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Licence;
use App\Models\MlsMemberLink;
use App\Models\Organisation;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\ProviderRequestReceived;
use App\Privacy\ConsentMethod;
use App\Privacy\ConsentPurpose;
use App\ProviderNetwork\ProviderPlans;
use App\Services\Privacy\Consents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Agents in my areas and requests to connect (B16.1, ADR 0079).
 */

/** An MLS listing in the area, listed by the member key. */
function katyListing(string $agentKey, string $city = 'Katy', string $zip = '77494', string $listed = '2026-10-01'): void
{
    DB::table('mls_listings')->insert([
        'uuid' => (string) Str::uuid(), 'integration' => 'har-mls', 'listing_key' => Str::random(10), 'status' => 'closed', 'city' => $city, 'state' => 'TX', 'postal_code' => $zip,
        'latitude' => 29.78, 'longitude' => -95.82, 'list_agent_key' => $agentKey, 'listed_on' => $listed, 'close_date' => $listed, 'photos' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** A REOS agent with their confirmed MLS member record and some listings. */
function areaAgent(Organisation $org, string $name, string $key, int $listings, string $licensed = '2018-05-01'): User
{
    $agent = User::factory()->withRole(Role::Agent, $org)->create(['name' => $name]);
    MlsMemberLink::query()->create(['user_id' => $agent->id, 'integration' => 'har-mls', 'member_key' => $key, 'status' => MlsMemberLink::CONFIRMED]);
    Licence::query()->create(['user_id' => $agent->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => (string) random_int(600000, 899999), 'status' => LicenceStatus::Active,
        'licensed_on' => $licensed, 'expires_on' => '2027-12-31', 'holder_name' => $name, 'verified_by' => 'simulator', 'verified_at' => now()]);

    for ($i = 0; $i < $listings; $i++) {
        katyListing($key);
    }

    return $agent;
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-30 15:00:00');
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    // Maya Chen: a live loan officer on Listed (5 requests a month) serving Katy.
    $this->maya = User::factory()->withRole(Role::Provider)->create(['name' => 'Maya Chen']);
    $this->profile = ProviderProfile::query()->create([
        'user_id' => $this->maya->id, 'provider_category_id' => ProviderCategory::query()->where('key', 'loan-officer')->value('id'),
        'status' => ProviderProfile::LIVE, 'live_at' => now(), 'tier' => 'basic', 'business_name' => 'Chen Home Lending', 'welcomed_at' => now(),
    ]);
    $this->profile->areas()->create(['kind' => 'city', 'city' => 'Katy']);
    $this->james = areaAgent($this->lone, 'James Miller', 'A1', 14);
    $this->maria = areaAgent($this->lone, 'Maria Garcia', 'A2', 3);
    $this->priya = areaAgent($this->lone, 'Priya Shah', 'A3', 1, licensed: '2026-04-15');
    // Two more agents, so the five requests can be used.
    $this->dana = areaAgent($this->lone, 'Dana Brooks', 'A4', 2);
    $this->omar = areaAgent($this->lone, 'Omar Haddad', 'A5', 2);
    $this->lee = areaAgent($this->lone, 'Lee Wong', 'A6', 2);
    // Working in Houston only: not in Maya's areas.
    $this->far = areaAgent($this->lone, 'Ana Ruiz', 'A7', 0);
    katyListing('A7', 'Houston', '77002');
});

describe('agents in my areas (B16.1)', function () {
    it('shows agents in the areas by level, with first names only and no contacts', function () {
        $view = $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/agents')->assertOk()->json('data');

        expect($view['counts'])->toBe(['new' => 1, 'active' => 4, 'top' => 1])
            ->and($view['agents'][0])->toMatchArray(['name' => 'James M.', 'brokerage' => 'Lone Star Realty', 'level' => 'top', 'sides' => 14, 'cities' => ['Katy'], 'request' => null, 'can_request' => true])
            ->and(collect($view['agents'])->pluck('name'))->not->toContain('Ana R.')
            ->and(json_encode($view))->not->toContain('Miller')->not->toContain('@')
            ->and($view['usage'])->toMatchArray(['tier' => 'basic', 'limit' => 5, 'used' => 0, 'left' => 5, 'resets_on' => '2026-12-01']);

        $new = $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/agents?level=new')->json('data.agents');
        expect(array_column($new, 'name'))->toBe(['Priya S.']);
        $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/agents?level=famous')->assertUnprocessable();
    });

    it('leaves out agents who turned the provider network off', function () {
        app(Consents::class)->withdraw($this->maria, ConsentPurpose::ProviderNetwork, ConsentMethod::Web);

        $names = collect($this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/agents')->json('data.agents'))->pluck('name');
        expect($names)->not->toContain('Maria G.')->toContain('James M.');
        $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $this->maria->id])->assertNotFound();
    });

    it('is only for a live provider', function () {
        $this->profile->forceFill(['status' => ProviderProfile::WAITING])->save();
        $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/agents')->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->actingAs($this->maya)->get('/provider/agents')->assertRedirect('/provider/join');
    });
});

describe('requests to connect (B16.1)', function () {
    it('sends 3 requests and enforces the plan\'s monthly limit (the done-when)', function () {
        $ask = fn (User $agent) => $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $agent->id, 'message' => 'Happy to help your buyers in Katy with pre-approvals.']);

        foreach ([$this->james, $this->maria, $this->priya] as $agent) {
            $ask($agent)->assertCreated();
        }

        Notification::assertSentTo($this->james, ProviderRequestReceived::class);
        expect($this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/requests')->json('usage'))->toMatchArray(['used' => 3, 'left' => 2])
            ->and(ProviderRequest::query()->where('agent_id', $this->james->id)->sole()->expires_at->toDateString())->toBe('2026-12-14')
            ->and(AuditEvent::query()->where('action', 'provider.request_sent')->count())->toBe(3);

        // Listed allows 5 a month: two more, then no more.
        $ask($this->dana)->assertCreated();
        $ask($this->omar)->assertCreated()->assertJsonPath('usage.left', 0);
        $ask($this->lee)->assertUnprocessable()->assertJsonValidationErrors(['limit' => 'You\'ve used all 5 requests for this month. More on December 1, or move to a bigger plan.']);
        expect(ProviderRequest::query()->count())->toBe(5);

        // Withdrawing doesn't give one back.
        $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/provider/requests/'.ProviderRequest::query()->where('agent_id', $this->omar->id)->value('uuid').'/withdraw')->assertOk();
        $ask($this->lee)->assertUnprocessable();

        // On the 1st the month starts again (Central time).
        $this->travelTo('2026-12-01 06:05:00');
        $ask($this->lee)->assertCreated();
    });

    it('allows Pro 25 and Partner 75 a month', function () {
        expect(ProviderPlans::requests('pro'))->toBe(25)
            ->and(ProviderPlans::requests('premium'))->toBe(75)
            ->and(ProviderPlans::requests(null))->toBe(0);
    });

    it('asks once at a time, respects a decline, and expires after 14 days', function () {
        $ask = fn (User $agent, array $extra = []) => $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $agent->id, ...$extra]);

        $ask($this->james)->assertCreated();
        $ask($this->james)->assertUnprocessable()->assertJsonValidationErrors('agent');
        // No contact details in the note.
        $ask($this->maria, ['message' => 'Call me at 713-555-0142'])->assertUnprocessable()->assertJsonValidationErrors('message');
        $ask($this->maria, ['message' => 'See chenlending.com'])->assertUnprocessable()->assertJsonValidationErrors('message');
        // Not in the area.
        $ask($this->far)->assertNotFound();

        ProviderRequest::query()->where('agent_id', $this->james->id)->update(['status' => ProviderRequest::DECLINED]);
        $ask($this->james)->assertUnprocessable()->assertJsonValidationErrors(['agent' => 'This agent declined your request.']);

        $ask($this->maria)->assertCreated();
        $this->travel(15)->days();
        $this->artisan('reos:providers:requests')->assertSuccessful();
        expect(ProviderRequest::query()->where('agent_id', $this->maria->id)->sole()->status)->toBe(ProviderRequest::EXPIRED);
        $ask($this->maria)->assertUnprocessable()->assertJsonValidationErrors('agent');
        $this->travel(30)->days();
        $ask($this->maria)->assertCreated();
    });

    it('shows the page on the web with the same view and requests', function () {
        $this->actingAs($this->maya)->post('/provider/requests', ['agent_id' => $this->james->id], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($this->maya)->get('/provider/agents?level=top')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('provider/agents')
            ->where('view.agents.0.request.status', 'pending')
            ->where('view.agents.0.can_request', false)
            ->where('view.usage.used', 1)
            ->where('sent.0.agent', 'James M.'));
    });
});
