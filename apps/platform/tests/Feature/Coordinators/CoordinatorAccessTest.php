<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\DealShare;
use App\Models\Message;
use App\Models\Organisation;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\DealShared;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Coordinators (B17.1, ADR 0085): a deal shared by part with a connected
 * transaction coordinator, until closing.
 */

function tcCoordinator(string $name, string $category = 'transaction-coordinator'): User
{
    $user = User::factory()->withRole(Role::Provider)->create(['name' => $name]);
    ProviderProfile::query()->create([
        'user_id' => $user->id, 'provider_category_id' => ProviderCategory::query()->where('key', $category)->value('id'),
        'status' => ProviderProfile::LIVE, 'live_at' => now(), 'tier' => 'pro', 'business_name' => $name.' Closings', 'welcomed_at' => now(),
    ]);

    return $user;
}

function tcConnect(User $provider, User $agent): void
{
    $profile = ProviderProfile::query()->where('user_id', $provider->id)->sole();
    ProviderRequest::query()->create([
        'uuid' => (string) Str::uuid7(), 'provider_profile_id' => $profile->id, 'provider_id' => $provider->id, 'agent_id' => $agent->id,
        'provider_category_id' => $profile->provider_category_id, 'status' => ProviderRequest::ACCEPTED, 'initiated_by' => 'provider',
        'expires_at' => now()->addDays(14), 'responded_at' => now(),
    ]);
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->tina = tcCoordinator('Tina Brooks');
    tcConnect($this->tina, $this->james);
    $this->deal = Deal::query()->create([
        'uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'organisation_id' => $this->lone->id, 'source' => 'reos', 'property' => '1302 Live Oak St', 'city' => 'Katy',
        'side' => 'buy', 'stage' => 'under_contract', 'price' => 425000,
        'parties' => [['name' => 'Rosa Lima', 'role' => 'buyer', 'email' => 'rosa@example.com', 'phone' => null]],
        'documents' => [['name' => 'Inspection report', 'status' => 'missing', 'url' => null], ['name' => 'Contract', 'status' => 'complete', 'url' => null]],
    ]);
    $this->option = DealDate::query()->create(['deal_id' => $this->deal->id, 'kind' => 'option_period', 'label' => 'Option period ends', 'due_on' => '2026-11-27']);
    DealDate::query()->create(['deal_id' => $this->deal->id, 'kind' => 'closing', 'label' => 'Closing', 'due_on' => '2026-12-18']);
    $this->share = fn (array $switches = []) => $this->actingAs($this->james, 'sanctum')
        ->postJson("/api/v1/deals/{$this->deal->uuid}/coordinators", ['coordinator_id' => $this->tina->id, 'switches' => $switches])->assertCreated()->json('data.id');
});

it('ends coordinator access at closing (the done-when)', function () {
    $id = ($this->share)();
    Notification::assertSentTo($this->tina, DealShared::class);

    $this->actingAs($this->tina, 'sanctum')->getJson('/api/v1/coordinator/files')->assertOk()
        ->assertJsonPath('data.counts.active', 1)->assertJsonPath('data.counts.due_this_week', 1)->assertJsonPath('data.counts.missing', 1)
        ->assertJsonPath('data.files.0.next.label', 'Option period ends');

    $this->actingAs($this->james, 'sanctum')->putJson("/api/v1/deals/{$this->deal->uuid}/stage", ['stage' => 'closed'])->assertOk();

    $share = DealShare::query()->where('uuid', $id)->sole();
    expect($share->status)->toBe('ended')->and($share->end_reason)->toBe('closed')
        ->and(AuditEvent::query()->where('action', 'coordinator.access_ended')->where('organisation_id', $this->lone->id)->exists())->toBeTrue();
    $this->actingAs($this->tina, 'sanctum')->getJson('/api/v1/coordinator/files')->assertJsonPath('data.counts.active', 0);
    $this->actingAs($this->tina, 'sanctum')->getJson("/api/v1/coordinator/files/{$id}")->assertNotFound();
    $this->actingAs($this->james, 'sanctum')->getJson("/api/v1/deals/{$this->deal->uuid}/coordinators")->assertOk()
        ->assertJsonPath('data.shares.0.status', 'ended')->assertJsonPath('data.shares.0.end_reason', 'closed');
    $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/deals/{$this->deal->uuid}/coordinators", ['coordinator_id' => $this->tina->id, 'switches' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('deal');
});

it('shows only the parts that were shared: documents and dates by default', function () {
    $id = ($this->share)();
    $this->actingAs($this->tina, 'sanctum')->getJson("/api/v1/coordinator/files/{$id}")->assertOk()
        ->assertJsonPath('data.switches', ['dates' => true, 'documents' => true, 'parties' => false, 'messages' => false])
        ->assertJsonPath('data.parties', null)->assertJsonPath('data.documents.0.name', 'Inspection report')->assertJsonCount(2, 'data.dates')
        ->assertJsonPath('data.draft', fn (string $d) => str_contains($d, 'Inspection report'));
    $this->actingAs($this->tina, 'sanctum')->postJson("/api/v1/coordinator/files/{$id}/messages", ['body' => 'Hi James'])->assertUnprocessable()->assertJsonValidationErrors('body');

    // The agent switches documents off and parties and messages on.
    $this->actingAs($this->james, 'sanctum')->putJson("/api/v1/deal-shares/{$id}", ['switches' => ['documents' => false, 'parties' => true, 'messages' => true]])->assertOk();
    $this->actingAs($this->tina, 'sanctum')->getJson("/api/v1/coordinator/files/{$id}")->assertJsonPath('data.documents', null)->assertJsonPath('data.parties.0.name', 'Rosa Lima')
        ->assertJsonMissingPath('data.parties.0.email');

    // Ticking an item shows the agent; a message goes to the agent's chat.
    $this->actingAs($this->tina, 'sanctum')->putJson("/api/v1/coordinator/files/{$id}/checklist", ['item' => (string) $this->option->id, 'done' => true])->assertOk();
    $this->actingAs($this->tina, 'sanctum')->getJson('/api/v1/coordinator/files')->assertJsonPath('data.files.0.next.label', 'Closing');
    $this->actingAs($this->tina, 'sanctum')->postJson("/api/v1/coordinator/files/{$id}/messages", ['body' => 'The inspection report is still missing.'])->assertCreated();
    expect(Message::query()->where('body', 'The inspection report is still missing.')->exists())->toBeTrue()
        ->and(AuditEvent::query()->whereIn('action', ['coordinator.shared', 'coordinator.switches', 'coordinator.ticked', 'coordinator.messaged'])->count())->toBe(4);
});

it('shares only with a connected coordinator, and ends access when removed, cancelled or swept', function () {
    $other = tcCoordinator('Omar Diaz');
    $inspector = tcCoordinator('Ivy Hart', 'home-inspector');
    tcConnect($inspector, $this->james);
    foreach ([$other, $inspector] as $who) {
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/deals/{$this->deal->uuid}/coordinators", ['coordinator_id' => $who->id, 'switches' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('coordinator_id');
    }
    $this->actingAs($this->james, 'sanctum')->getJson("/api/v1/deals/{$this->deal->uuid}/coordinators")->assertJsonCount(1, 'data.coordinators');

    // Only the deal's agent can share or change it; only the coordinator sees the file.
    $maria = User::factory()->withRole(Role::Agent, $this->lone)->create();
    $this->actingAs($maria, 'sanctum')->postJson("/api/v1/deals/{$this->deal->uuid}/coordinators", ['coordinator_id' => $this->tina->id, 'switches' => []])->assertNotFound();
    $id = ($this->share)();
    $this->actingAs($maria, 'sanctum')->putJson("/api/v1/deal-shares/{$id}", ['switches' => ['parties' => true]])->assertNotFound();
    $this->actingAs($other, 'sanctum')->getJson("/api/v1/coordinator/files/{$id}")->assertNotFound();

    // Removed.
    $this->actingAs($this->james, 'sanctum')->deleteJson("/api/v1/deal-shares/{$id}")->assertOk();
    $this->actingAs($this->tina, 'sanctum')->getJson("/api/v1/coordinator/files/{$id}")->assertNotFound();
    expect(DealShare::query()->where('uuid', $id)->sole()->end_reason)->toBe('removed');

    // Closed some other way (a tool's sync writes the stage without the model): the sweep ends it.
    $again = ($this->share)();
    Deal::query()->whereKey($this->deal->id)->toBase()->update(['stage' => 'cancelled']);
    $this->artisan('reos:coordinators:sweep')->assertSuccessful();
    expect(DealShare::query()->where('uuid', $again)->sole()->end_reason)->toBe('cancelled');
});

it('shows My files on the web', function () {
    $id = ($this->share)();
    $this->actingAs($this->tina)->get('/provider/files')->assertOk()->assertInertia(fn (Assert $page) => $page->component('provider/files')->where('board.counts.active', 1)->where('file', null));
    $this->actingAs($this->tina)->get("/provider/files/{$id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('provider/files')->where('file.property', '1302 Live Oak St'));
});
