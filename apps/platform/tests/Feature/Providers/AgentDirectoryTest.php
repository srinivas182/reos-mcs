<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Messaging\Relationships;
use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Organisation;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\AgentAskedToConnect;
use App\ProviderNetwork\AgentsInArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Providers near you, reviews, and Request to connect in two taps (B16.3, ADR 0081).
 */

function directoryProvider(string $name, string $category, string $business, string $city, string $tier = 'pro'): ProviderProfile
{
    $provider = User::factory()->withRole(Role::Provider)->create(['name' => $name]);
    $profile = ProviderProfile::query()->create([
        'user_id' => $provider->id, 'provider_category_id' => ProviderCategory::query()->where('key', $category)->value('id'),
        'status' => ProviderProfile::LIVE, 'live_at' => now(), 'tier' => $tier, 'business_name' => $business, 'welcomed_at' => now(),
        'published' => ['bio' => "{$business} serves {$city}.", 'services' => [], 'specialities' => [], 'response_hours' => 2], 'published_at' => now(),
    ]);
    $profile->areas()->create(['kind' => 'city', 'city' => $city]);

    return $profile;
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-30 15:00:00');
    $lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->james = User::factory()->withRole(Role::Agent, $lone)->create(['name' => 'James Miller']);
    // James works in Katy (a deal there this year).
    Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'organisation_id' => $lone->id, 'source' => 'reos', 'property' => '1420 Pecan St', 'city' => 'Katy',
        'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
    $this->chen = directoryProvider('Maya Chen', 'loan-officer', 'Chen Home Lending', 'Katy', 'basic');
    $this->patel = directoryProvider('Ravi Patel', 'home-inspector', 'Patel Inspections', 'Katy');
    $this->far = directoryProvider('Lena Ortiz', 'loan-officer', 'Ortiz Mortgage', 'Austin', 'premium');
});

describe('providers near you (B16.3)', function () {
    it('lists providers serving the agent\'s cities first, by category', function () {
        $near = $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/providers/near')->assertOk()->json();

        expect($near['cities'])->toBe(['Katy'])
            ->and(array_column($near['providers'], 'name'))->toBe(['Patel Inspections', 'Chen Home Lending', 'Ortiz Mortgage'])
            ->and(array_column($near['providers'], 'near'))->toBe([true, true, false])
            ->and(collect($near['categories'])->firstWhere('key', 'loan-officer')['count'])->toBe(1);
        expect(array_column($this->actingAs($this->james, 'sanctum')->getJson('/api/v1/providers/near?category=loan-officer')->json('providers'), 'name'))
            ->toBe(['Chen Home Lending', 'Ortiz Mortgage']);
    });
});

describe('request to connect (B16.3)', function () {
    it('connects an agent with a lender in two taps (the done-when)', function () {
        // Tap 1: open the lender from Providers near you.
        $this->actingAs($this->james, 'sanctum')->getJson("/api/v1/providers/{$this->chen->id}/network")->assertOk()
            ->assertJsonPath('data.can_connect', true)->assertJsonPath('data.connection', null);

        // Tap 2: Request to connect. Connected at once, with the chat open.
        $connection = $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/connect")->assertCreated()->json('data');
        expect($connection)->toMatchArray(['side' => 'agent', 'with' => 'Maya Chen', 'contacts' => null])
            ->and($connection['conversation'])->toBe(Conversation::query()->sole()->uuid)
            ->and(app(Relationships::class)->canMessage($this->chen->user, $this->james))->toBeTrue();
        Notification::assertSentTo($this->chen->user, AgentAskedToConnect::class);
        expect(AuditEvent::query()->where('action', 'provider.agent_connected')->exists())->toBeTrue();

        // It doesn't use Maya's Listed requests, and a second tap is the same connection.
        expect(app(AgentsInArea::class)->usage($this->chen)['used'])->toBe(0);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/connect")->assertCreated()->assertJsonPath('data.id', $connection['id']);
        expect(ProviderRequest::query()->count())->toBe(1);
        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/providers/near')->assertJsonPath('providers.1.connection', $connection['id']);
    });

    it('accepts a provider\'s waiting request instead, and only agents connect', function () {
        $request = ProviderRequest::query()->create(['uuid' => (string) Str::uuid7(), 'provider_profile_id' => $this->patel->id, 'provider_id' => $this->patel->user_id, 'agent_id' => $this->james->id,
            'status' => ProviderRequest::PENDING, 'expires_at' => now()->addDays(14)]);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->patel->id}/connect")->assertCreated()->assertJsonPath('data.id', $request->uuid);
        expect($request->refresh()->status)->toBe(ProviderRequest::ACCEPTED);

        $broker = User::factory()->withRole(Role::Broker, Organisation::factory()->ofType(OrganisationType::Brokerage)->create())->create();
        $this->actingAs($broker, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/connect")->assertNotFound();
        $hidden = directoryProvider('Sam Hill', 'loan-officer', 'Hill Loans', 'Katy');
        $hidden->forceFill(['status' => ProviderProfile::HIDDEN])->save();
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$hidden->id}/connect")->assertNotFound();
    });
});

describe('reviews (B16.3)', function () {
    it('lets a connected agent review after 30 days, once', function () {
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/reviews", ['rating' => 5])->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/connect")->assertCreated();
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/reviews", ['rating' => 5])->assertUnprocessable()
            ->assertJsonValidationErrors(['review' => 'You can review on December 30, 30 days after connecting.']);

        $this->travel(31)->days();
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/reviews", ['rating' => 4, 'body' => 'Call 713-555-0142'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/reviews", ['rating' => 4, 'body' => 'Pre-approvals in a day; my buyers closed on time.'])->assertOk()
            ->assertJsonPath('data.rating', ['average' => 4, 'count' => 1])
            ->assertJsonPath('data.reviews.0.agent', 'James M.');
        // A second review replaces the first.
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/reviews", ['rating' => 5])->assertOk()->assertJsonPath('data.rating', ['average' => 5, 'count' => 1]);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/providers/{$this->chen->id}/reviews", ['rating' => 6])->assertUnprocessable();
        expect(AuditEvent::query()->where('action', 'provider.reviewed')->count())->toBe(2);
    });

    it('shows reviews and Request to connect on the web profile', function () {
        $this->actingAs($this->james)->get("/providers/{$this->chen->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('providers/show')
            ->where('network.can_connect', true)
            ->where('network.rating.count', 0));
        $this->actingAs($this->james)->get('/providers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('near.cities', ['Katy'])
            ->where('near.providers.0.near', true));
        $this->actingAs($this->james)->post("/providers/{$this->chen->id}/connect", [], ['Accept' => 'application/json'])->assertCreated();
    });
});
