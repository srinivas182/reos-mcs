<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Messaging\Relationships;
use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\MlsMemberLink;
use App\Models\Organisation;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\ProviderRequestAnswered;
use App\ProviderNetwork\ProviderRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * The private network between providers and agents (B16.2, ADR 0080).
 */

function networkAgent(Organisation $org, string $name, string $email, string $phone, string $key): User
{
    $agent = User::factory()->withRole(Role::Agent, $org)->create(['name' => $name, 'email' => $email, 'phone' => $phone]);
    MlsMemberLink::query()->create(['user_id' => $agent->id, 'integration' => 'har-mls', 'member_key' => $key, 'status' => MlsMemberLink::CONFIRMED]);
    DB::table('mls_listings')->insert([
        'uuid' => (string) Str::uuid(), 'integration' => 'har-mls', 'listing_key' => Str::random(10), 'status' => 'active', 'city' => 'Katy', 'state' => 'TX', 'postal_code' => '77494',
        'list_agent_key' => $key, 'listed_on' => '2026-10-01', 'photos' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $agent;
}

function networkProvider(string $name, string $category, string $business, string $email, string $phone): User
{
    $provider = User::factory()->withRole(Role::Provider)->create(['name' => $name, 'email' => $email, 'phone' => $phone]);
    $profile = ProviderProfile::query()->create([
        'user_id' => $provider->id, 'provider_category_id' => ProviderCategory::query()->where('key', $category)->value('id'),
        'status' => ProviderProfile::LIVE, 'live_at' => now(), 'tier' => 'pro', 'business_name' => $business, 'welcomed_at' => now(),
    ]);
    $profile->areas()->create(['kind' => 'city', 'city' => 'Katy']);

    return $provider;
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-11-30 15:00:00');
    $lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->james = networkAgent($lone, 'James Miller', 'james.miller@demo.reos.test', '+17135550101', 'A1');
    $this->maria = networkAgent($lone, 'Maria Garcia', 'maria.garcia@demo.reos.test', '+17135550102', 'A2');
    $this->maya = networkProvider('Maya Chen', 'loan-officer', 'Chen Home Lending', 'maya@chenlending.example', '+17135550199');
    $this->ask = fn (User $provider, User $agent) => ProviderRequest::query()->where('uuid', $this->actingAs($provider, 'sanctum')
        ->postJson('/api/v1/provider/requests', ['agent_id' => $agent->id])->assertCreated()->json('data.id'))->sole();
});

describe('the agent\'s answer (B16.2)', function () {
    it('accepts, which opens a private chat with the provider', function () {
        $request = ($this->ask)($this->maya, $this->james);
        expect(app(Relationships::class)->canMessage($this->maya, $this->james))->toBeFalse();

        $mine = $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/provider-requests')->assertOk()->json();
        expect($mine['pending'][0])->toMatchArray(['id' => $request->uuid])
            ->and($mine['pending'][0]['provider'])->toMatchArray(['name' => 'Chen Home Lending', 'category' => 'Loan officer']);

        $connection = $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/me/provider-requests/{$request->uuid}/accept")->assertOk()->json('data');
        expect($connection)->toMatchArray(['side' => 'agent', 'with' => 'Maya Chen', 'i_shared' => false, 'they_shared' => false, 'contacts' => null])
            ->and($connection['conversation'])->toBe(Conversation::query()->sole()->uuid)
            ->and(app(Relationships::class)->canMessage($this->maya, $this->james))->toBeTrue()
            ->and(app(Relationships::class)->canMessage($this->maya, $this->maria))->toBeFalse();
        Notification::assertSentTo($this->maya, ProviderRequestAnswered::class, fn (ProviderRequestAnswered $n) => $n->accepted);
        expect(AuditEvent::query()->where('action', 'provider.request_accepted')->exists())->toBeTrue();

        // The provider now sees James's full name, but still no contacts.
        $this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/connections')->assertOk()
            ->assertJsonPath('data.0.with', 'James Miller')->assertJsonPath('data.0.contacts', null);
    });

    it('declines, and declining with a block stops requests and messages', function () {
        $request = ($this->ask)($this->maya, $this->james);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/me/provider-requests/{$request->uuid}/decline", ['block' => true])->assertOk();

        Notification::assertSentTo($this->maya, ProviderRequestAnswered::class, fn (ProviderRequestAnswered $n) => ! $n->accepted);
        expect($request->refresh()->status)->toBe(ProviderRequest::DECLINED);
        // James no longer shows for Maya, and can't be asked.
        expect(collect($this->actingAs($this->maya, 'sanctum')->getJson('/api/v1/provider/agents')->json('data.agents'))->pluck('id'))->not->toContain($this->james->id);
        $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $this->james->id])->assertNotFound();

        // Someone else's request can't be answered.
        $other = ($this->ask)($this->maya, $this->maria);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/me/provider-requests/{$other->uuid}/accept")->assertNotFound();
    });
});

describe('mutual contact sharing (B16.2)', function () {
    it('shows contacts only after both agree (the done-when)', function () {
        $request = ($this->ask)($this->maya, $this->james);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/me/provider-requests/{$request->uuid}/accept")->assertOk();
        $show = fn (User $who) => $this->actingAs($who, 'sanctum')->getJson("/api/v1/provider-connections/{$request->uuid}")->assertOk()->json('data');

        // James shares: still nothing for either side.
        $this->actingAs($this->james, 'sanctum')->putJson("/api/v1/provider-connections/{$request->uuid}/share", ['share' => true])->assertOk()
            ->assertJsonPath('data.i_shared', true)->assertJsonPath('data.contacts', null);
        expect($show($this->maya))->toMatchArray(['i_shared' => false, 'they_shared' => true, 'contacts' => null])
            ->and(json_encode($show($this->maya)))->not->toContain('james.miller@')->not->toContain('5550101');

        // Maya shares too: each sees the other's phone and email.
        $this->actingAs($this->maya, 'sanctum')->putJson("/api/v1/provider-connections/{$request->uuid}/share", ['share' => true])->assertOk()
            ->assertJsonPath('data.contacts', ['email' => 'james.miller@demo.reos.test', 'phone' => '+17135550101']);
        expect($show($this->james)['contacts'])->toBe(['email' => 'maya@chenlending.example', 'phone' => '+17135550199']);

        // Either can stop sharing, and then neither sees them.
        $this->actingAs($this->james, 'sanctum')->putJson("/api/v1/provider-connections/{$request->uuid}/share", ['share' => false])->assertOk();
        expect($show($this->maya)['contacts'])->toBeNull()->and($show($this->james)['contacts'])->toBeNull();
        expect(AuditEvent::query()->where('action', 'provider.contacts_shared')->count())->toBe(2);

        // A stranger sees nothing.
        $this->actingAs($this->maria, 'sanctum')->getJson("/api/v1/provider-connections/{$request->uuid}")->assertNotFound();
    });

    it('never shows contacts across a block, and a block ends the chat', function () {
        $request = ($this->ask)($this->maya, $this->james);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/me/provider-requests/{$request->uuid}/accept")->assertOk();
        foreach ([$this->james, $this->maya] as $who) {
            $this->actingAs($who, 'sanctum')->putJson("/api/v1/provider-connections/{$request->uuid}/share", ['share' => true])->assertOk();
        }

        $this->actingAs($this->maya, 'sanctum')->postJson("/api/v1/provider-connections/{$request->uuid}/block")->assertOk();
        $this->actingAs($this->james, 'sanctum')->getJson("/api/v1/provider-connections/{$request->uuid}")
            ->assertJsonPath('data.contacts', null)->assertJsonPath('data.blocked', true)->assertJsonPath('data.conversation', null);
        expect(app(Relationships::class)->canMessage($this->james, $this->maya))->toBeFalse();
    });
});

describe('agent opt-out (B16.2)', function () {
    it('turns off all providers, or one category', function () {
        $inspector = networkProvider('Ravi Patel', 'home-inspector', 'Patel Inspections', 'ravi@patel.example', '+17135550188');

        // James turns off loan officers only.
        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/provider-preferences', ['all' => true, 'off' => ['loan-officer']])->assertOk()
            ->assertJsonPath('data.all', true);
        $this->actingAs($this->maya, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $this->james->id])->assertNotFound();
        $this->actingAs($inspector, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $this->james->id])->assertCreated();

        // Maria turns off every provider.
        $this->actingAs($this->maria, 'sanctum')->putJson('/api/v1/me/provider-preferences', ['all' => false, 'off' => []])->assertOk();
        $this->actingAs($inspector, 'sanctum')->postJson('/api/v1/provider/requests', ['agent_id' => $this->maria->id])->assertNotFound();
        $prefs = $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/provider-requests')->json('preferences');
        expect(collect($prefs['categories'])->firstWhere('key', 'loan-officer')['on'])->toBeFalse()
            ->and(collect($prefs['categories'])->firstWhere('key', 'home-inspector')['on'])->toBeTrue();

        $this->actingAs($this->james, 'sanctum')->putJson('/api/v1/me/provider-preferences', ['all' => true, 'off' => ['no-such-category']])->assertUnprocessable();
    });

    it('shows the agent\'s page on the web', function () {
        $request = ($this->ask)($this->maya, $this->james);

        $this->actingAs($this->james)->get('/providers/requests')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('providers/requests')
            ->where('network.pending.0.id', $request->uuid)
            ->where('network.preferences.all', true));
        expect(ProviderRequests::EXPIRES_DAYS)->toBe(14);
    });
});
