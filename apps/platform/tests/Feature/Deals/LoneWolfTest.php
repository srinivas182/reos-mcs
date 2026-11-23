<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\LoneWolf\LoneWolfDriver;
use App\Connections\Drivers\LoneWolf\LoneWolfRecords;
use App\Connections\Drivers\LoneWolf\LoneWolfSimulator;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park', 'email' => 'linda.park@demo.reos.test']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Maria Garcia', 'email' => 'Maria.Garcia@demo.reos.test']);
});

/** The broker connects the brokerage's Lone Wolf on screen 40 with its client ID (demo data). */
function loneWolfConnected(object $test): Connection
{
    IntegrationSetting::query()->firstOrCreate(['integration_id' => 'lone-wolf'], ['mode' => IntegrationMode::Simulator]);
    $test->actingAs($test->linda)->post('/organisation/integrations/tools/lone-wolf/key', ['api_key' => '7f3a2c1e-58a4-4c7d-9e2f-5b1a6c8d9e0f'])->assertSessionHasNoErrors()->assertRedirect();

    return Connection::query()->where('integration_id', 'lone-wolf')->sole();
}

describe('Lone Wolf Transact on demo data (the B9.1 done-when)', function () {
    it('connects the brokerage once and gives each agent only their own transactions, as deals with to-dos', function () {
        $connection = loneWolfConnected($this);

        expect($connection->owner_type)->toBe(Connection::OWNER_ORGANISATION)
            ->and($connection->account_label)->toBe('Lone Star Realty (demo)')
            ->and(Deal::query()->where('user_id', $this->james->id)->pluck('property')->all())->toBe(['2210 Heights Blvd'])
            ->and(Deal::query()->where('user_id', $this->maria->id)->pluck('property')->all())->toBe(['418 Magnolia Ct'])
            ->and(Deal::query()->where('property', '90 Office Park Dr')->exists())->toBeFalse()
            ->and($connection->refresh()->meta['unmatched_owners'])->toBe(['coordinator@lonestar.example']);

        $heights = Deal::query()->where('user_id', $this->james->id)->with('dates')->sole();
        expect($heights)->stage->toBe('under_contract')->side->toBe('buy')->price->toBe(505000)->source->toBe('lone-wolf')
            ->and($heights->readOnly())->toBeTrue()
            ->and($heights->external_url)->toBe(LoneWolfRecords::OPEN.'lw-tx-1001')
            ->and($heights->dates->pluck('kind')->all())->toBe(['option_period', 'survey', 'financing', 'other', 'closing'])
            ->and(collect($heights->documents)->firstWhere('status', 'missing')['name'] ?? null)->toBe('Seller\'s Disclosure Notice');
        expect(Todo::query()->where('user_id', $this->james->id)->where('related_type', 'deal')->orderBy('due_date')->first()?->title)->toBe('Option period ends · 2210 Heights Blvd')
            ->and(Todo::query()->where('user_id', $this->james->id)->where('related_type', 'deal')->orderBy('due_date')->first()?->due_date?->toDateString())->toBe('2026-11-26');

        // The broker sees counts and deadlines on screen 40, never the deals.
        $this->actingAs($this->linda)->get('/organisation/integrations')->assertInertia(fn (Assert $page) => $page
            ->where('tools', fn ($tools) => collect($tools)->firstWhere('id', 'lone-wolf')['account']['deals'] === ['count' => 2, 'due_week' => 1]));
        $this->actingAs($this->maria)->getJson("/api/v1/deals/{$heights->uuid}")->assertNotFound();
        $this->actingAs($this->james)->getJson('/api/v1/deals/transactions')->assertOk()->assertJsonPath('data.0.property', '2210 Heights Blvd')->assertJsonPath('data.0.documents', ['complete' => 2, 'total' => 3, 'missing' => 1]);
        $this->putJson("/api/v1/deals/{$heights->uuid}/stage", ['stage' => 'closed'])->assertUnprocessable();
    });

    it('moves the alert and the to-do when a date moves in Lone Wolf, and removes them when the transaction goes', function () {
        $connection = loneWolfConnected($this);
        $opt = static fn (): ?Todo => Todo::query()->where('related_type', 'deal')->where('title', 'like', 'Option period ends%')->first();
        $before = $opt();

        LoneWolfSimulator::changeInTool($connection, 'lw-tx-1001', ['keyDates' => [
            ['id' => 'opt', 'name' => 'Option period ends', 'date' => '2026-11-28'],
            ['id' => 'fin', 'name' => 'Financing approval', 'date' => '2026-12-10'],
        ]]);
        $this->actingAs($this->linda)->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();

        $after = $opt();
        expect($after?->id)->toBe($before?->id)
            ->and($after?->due_date?->toDateString())->toBe('2026-11-28')
            ->and(Todo::query()->where('related_type', 'deal')->where('title', 'like', 'Survey%')->exists())->toBeFalse();

        LoneWolfSimulator::changeInTool($connection, 'lw-tx-1001', [], deleted: true);
        $this->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();
        expect(Deal::query()->where('user_id', $this->james->id)->count())->toBe(0)
            ->and(Todo::query()->where('user_id', $this->james->id)->where('related_type', 'deal')->count())->toBe(0);

        // Disconnecting forgets every deal it brought.
        $this->deleteJson("/api/v1/connections/{$connection->id}")->assertSuccessful();
        expect(Deal::query()->count())->toBe(0);
    });

    it('refuses a client ID Lone Wolf doesn\'t accept', function () {
        IntegrationSetting::query()->firstOrCreate(['integration_id' => 'lone-wolf'], ['mode' => IntegrationMode::Simulator]);
        $this->actingAs($this->linda)->post('/organisation/integrations/tools/lone-wolf/key', ['api_key' => 'abc12'])->assertSessionHasErrors('api_key');
        $this->actingAs($this->james)->post('/organisation/integrations/tools/lone-wolf/key', ['api_key' => '7f3a2c1e-58a4-4c7d'])->assertForbidden();
        expect(Connection::query()->count())->toBe(0);
    });
});

describe('the real Lone Wolf driver', function () {
    it('signs in with client credentials for the brokerage, then reads users\' transactions with contacts, documents and offers', function () {
        Http::fake([
            'api.lwolf.com/oauth/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
            'api.lwolf.com/platform/v1/clients/*/users*' => Http::response(['value' => [['id' => 'u-1', 'email' => 'james.miller@demo.reos.test']]]),
            'api.lwolf.com/transact-workflow/v1/users/u-1/transactions' => Http::sequence()
                ->push(['value' => [['id' => 'tx-9', 'type' => 1, 'phase' => 2, 'address1' => '9 Oak Ct', 'locality' => 'Austin', 'asking' => 610000]]])
                // The next pass: it's gone from Transact.
                ->push(['value' => []]),
            'api.lwolf.com/transact-workflow/v1/users/u-1/Contacts*' => Http::response(['value' => [['contactType' => 0, 'firstname' => 'Ana', 'lastname' => 'Ruiz', 'email' => 'ana@example.com']]]),
            'api.lwolf.com/transact-workflow/v1/users/u-1/documents*' => Http::response(['value' => [['name' => 'Contract']]]),
            'api.lwolf.com/transact-workflow/v1/users/u-1/Offers*' => Http::response(['value' => [['purchasePrice' => 600000, 'acceptanceDate' => '2026-11-20', 'closingDate' => '2026-12-19T00:00:00Z']]]),
        ]);
        $driver = new LoneWolfDriver('partner-id', 'partner-secret', 'sub-key');
        $connection = new Connection(['integration_id' => 'lone-wolf', 'meta' => []]);

        $page = $driver->fetch($connection, '7f3a2c1e-58a4-4c7d-9e2f-5b1a6c8d9e0f', 'deals', null, null);

        expect($page->done)->toBeTrue()->and($page->removed)->toBe([])
            ->and($page->records[0])->toMatchArray(['external_id' => 'tx-9', 'owner_email' => 'james.miller@demo.reos.test', 'property' => '9 Oak Ct', 'side' => 'buy', 'stage' => 'under_contract', 'price' => 600000])
            ->and($page->records[0]['parties'])->toBe([['name' => 'Ana Ruiz', 'role' => 'Buyer', 'email' => 'ana@example.com', 'phone' => null]])
            ->and($page->records[0]['dates'])->toBe([['kind' => 'closing', 'label' => null, 'due_on' => '2026-12-19', 'external_id' => 'tx-9:closing']]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/oauth/token') && $r['grant_type'] === 'client_credentials' && $r['lwt_client_id'] === '7f3a2c1e-58a4-4c7d-9e2f-5b1a6c8d9e0f');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/transactions') && $r->hasHeader('lw-subscription-key', 'sub-key') && $r->hasHeader('Authorization', 'Bearer tok-1'));

        // The next pass: a transaction no longer there is removed.
        expect($driver->fetch($connection, '7f3a2c1e-58a4-4c7d-9e2f-5b1a6c8d9e0f', 'deals', $page->cursor, null)->removed)->toBe(['tx-9']);
    });
});
