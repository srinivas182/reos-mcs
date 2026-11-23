<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Drivers\Dotloop\DotloopDriver;
use App\Connections\Drivers\Dotloop\DotloopSimulator;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Connections\Drivers\SkySlope\SkySlopeDriver;
use App\Connections\Drivers\SkySlope\SkySlopeSimulator;
use App\Connections\Exceptions\AccessRevoked;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $this->linda = User::factory()->withTwoFactor()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park', 'email' => 'linda.park@demo.reos.test']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'email' => 'james.miller@demo.reos.test']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Maria Garcia', 'email' => 'Maria.Garcia@demo.reos.test']);
});

/** James connects his own dotloop through the demo sign-in (OAuth). */
function dotloopConnected(object $test): Connection
{
    IntegrationSetting::query()->firstOrCreate(['integration_id' => 'dotloop'], ['mode' => IntegrationMode::Simulator]);
    connectOnWeb($test, $test->james, tool: 'dotloop');

    return Connection::query()->where('integration_id', 'dotloop')->sole();
}

describe('dotloop on demo data (the B9.2 done-when)', function () {
    it('connects the agent\'s own dotloop and brings both profiles\' loops as read-only deals, with dates, tasks and documents', function () {
        $connection = dotloopConnected($this);

        expect($connection->owner_type)->toBe(Connection::OWNER_USER)
            ->and(Deal::query()->where('user_id', $this->james->id)->orderBy('property')->pluck('property')->all())->toBe(['1302 Live Oak St', '55 Ranch Rd']);

        $oak = Deal::query()->where('property', '1302 Live Oak St')->with('dates')->sole();
        expect($oak)->stage->toBe('under_contract')->side->toBe('buy')->price->toBe(475000)->source->toBe('dotloop')->city->toBe('Austin')
            ->and($oak->readOnly())->toBeTrue()
            ->and($oak->external_url)->toBe('https://www.dotloop.com/my/loop/90210')
            ->and(collect($oak->documents)->pluck('name')->all())->toContain('One to Four Family Residential Contract')
            ->and(collect($oak->parties)->pluck('name')->all())->toContain('Priya Shah')
            ->and($oak->dates->pluck('kind')->sort()->values()->all())->toBe(['closing', 'inspection', 'option_period', 'other', 'other']);

        // Each date is a to-do on its day; a task done in dotloop is a done to-do.
        $todos = Todo::query()->where('user_id', $this->james->id)->where('related_type', 'deal')->get();
        expect($todos->firstWhere('title', 'Option period ends · 1302 Live Oak St')?->due_date?->toDateString())->toBe('2026-11-25')
            ->and($todos->firstWhere('title', 'Earnest money delivered · 1302 Live Oak St')?->done_at)->not->toBeNull()
            ->and($todos->firstWhere('title', 'HOA documents requested · 1302 Live Oak St')?->done_at)->toBeNull();

        // The deal can't be dragged: dotloop is the record.
        $this->actingAs($this->james)->putJson("/api/v1/deals/{$oak->uuid}/stage", ['stage' => 'closed'])->assertUnprocessable();
        $this->actingAs($this->maria)->getJson("/api/v1/deals/{$oak->uuid}")->assertNotFound();
    });

    it('lets the agent choose which profiles sync, on the website and the phone alike', function () {
        $connection = dotloopConnected($this);

        $this->actingAs($this->james)->getJson('/api/v1/connections')->assertOk()
            ->assertJsonPath('data.0.profiles', [['id' => '4711', 'name' => 'Individual', 'chosen' => true], ['id' => '4712', 'name' => 'Lone Star Team', 'chosen' => true]]);

        $this->patchJson("/api/v1/connections/{$connection->id}", ['profiles' => ['4711']])->assertOk()
            ->assertJsonPath('data.profiles.1.chosen', false);
        expect(Deal::query()->where('user_id', $this->james->id)->pluck('property')->all())->toBe(['1302 Live Oak St'])
            ->and(Todo::query()->where('related_type', 'deal')->where('title', 'like', '%55 Ranch Rd')->exists())->toBeFalse();

        // Not a profile on the account, none at all, or a tool without profiles.
        $this->patchJson("/api/v1/connections/{$connection->id}", ['profiles' => ['999']])->assertUnprocessable()->assertJsonValidationErrors('profiles');
        $this->patchJson("/api/v1/connections/{$connection->id}", ['profiles' => []])->assertUnprocessable()->assertJsonValidationErrors('profiles');
        $this->actingAs($this->maria)->patchJson("/api/v1/connections/{$connection->id}", ['profiles' => ['4711']])->assertNotFound();
    });

    it('syncs at once when dotloop says a loop changed, and only for a signed, fresh delivery', function () {
        $connection = dotloopConnected($this);
        DotloopSimulator::changeInTool($connection, '90210', ['status' => 'SOLD']);
        Queue::fake();
        $body = (string) json_encode(['eventId' => 'evt-77', 'eventType' => 'LOOP_UPDATED', 'event' => ['id' => 90210, 'profileId' => 4711]]);
        $url = '/webhooks/dotloop?c='.WebhookTarget::seal($connection->id, 'dotloop-webhook');
        $now = (string) time();
        $signed = hash_hmac('sha1', $now.'.'.$body, DotloopDriver::signingKey($connection->id));
        $send = fn (string $to, string $signature, string $at) => $this->call('POST', $to, [], [], [], ['HTTP_X-DOTLOOP-WEBHOOK-SIGNATURE' => $signature, 'HTTP_X-DOTLOOP-WEBHOOK-TIMESTAMP' => $at, 'CONTENT_TYPE' => 'application/json'], $body);

        $send($url, 'bad', $now)->assertStatus(401);
        $send('/webhooks/dotloop?c='.$connection->id.'.forged000000000000000000000000000', $signed, $now)->assertStatus(401);
        Queue::assertNothingPushed();

        $send($url, $signed, $now)->assertSuccessful();
        Queue::assertPushed(SyncConnection::class);
    });

    it('refuses an old webhook with the real driver, so a recorded one can\'t be replayed', function () {
        $connection = dotloopConnected($this);
        $driver = new DotloopDriver('client', 'secret');
        $body = (string) json_encode(['eventId' => 'evt-1']);
        $request = function (string $at) use ($connection, $body): Request {
            return Request::create('/webhooks/dotloop?c='.WebhookTarget::seal($connection->id, 'dotloop-webhook'), 'POST', server: [
                'HTTP_X_DOTLOOP_WEBHOOK_SIGNATURE' => hash_hmac('sha1', $at.'.'.$body, DotloopDriver::signingKey($connection->id)),
                'HTTP_X_DOTLOOP_WEBHOOK_TIMESTAMP' => $at,
            ], content: $body);
        };

        expect($driver->verifyWebhook($request((string) time()))?->connectionIds)->toBe([$connection->id])
            ->and($driver->verifyWebhook($request((string) (time() - 600))))->toBeNull();
    });
});

describe('the real dotloop driver', function () {
    it('reads each chosen profile\'s loops with their details, participants, documents and tasks', function () {
        $connection = dotloopConnected($this);
        Http::fake([
            'api-gateway.dotloop.com/public/v2/profile' => Http::response(['data' => [['id' => 11, 'name' => 'Individual'], ['id' => 12, 'name' => 'Team']]]),
            'api-gateway.dotloop.com/public/v2/profile/11/loop?*' => Http::response(['data' => [[
                'id' => 501, 'name' => '9 Elm St, Austin', 'transactionType' => 'PURCHASE_OFFER', 'status' => 'UNDER_CONTRACT', 'loopUrl' => 'https://www.dotloop.com/my/loop/501', 'updated' => '2026-11-22T10:00:00Z',
                'details' => ['Property Address' => ['Street Number' => '9', 'Street Name' => 'Elm St', 'City' => 'Austin'], 'Financials' => ['Purchase/Sale Price' => '$350,000'], 'Contract Dates' => ['Closing Date' => '12/30/2026']],
            ]]]),
            'api-gateway.dotloop.com/public/v2/profile/11/loop/501/tasklist/' => Http::response(['data' => [['id' => 3, 'name' => 'Checklist']]]),
            'api-gateway.dotloop.com/public/v2/profile/11/loop/501/tasklist/3/task' => Http::response(['data' => [['id' => 31, 'name' => 'Appraisal ordered', 'dueDate' => '2026-12-01T00:00:00Z', 'completed' => false]]]),
            'api-gateway.dotloop.com/public/v2/profile/11/loop/501/participant' => Http::response(['data' => [['fullName' => 'Ana Ruiz', 'role' => 'BUYER', 'email' => 'ana@example.com']]]),
            'api-gateway.dotloop.com/public/v2/profile/11/loop/501/folder*' => Http::response(['data' => [['name' => 'Contract', 'documents' => [['name' => 'Purchase contract']]]]]),
            'api-gateway.dotloop.com/public/v2/profile/12/loop?*' => Http::response(['data' => []]),
        ]);
        $driver = new DotloopDriver('client', 'secret');

        $first = $driver->fetch($connection, 'tok', 'deals', null, null);
        expect($first->done)->toBeFalse()
            ->and($first->records[0])->toMatchArray(['external_id' => '501', 'property' => '9 Elm St', 'city' => 'Austin', 'side' => 'buy', 'stage' => 'under_contract', 'price' => 350000])
            ->and(collect($first->records[0]['dates'])->pluck('kind', 'label')->all())->toBe(['' => 'closing', 'Appraisal ordered' => 'other'])
            ->and($first->records[0]['documents'][0]['name'])->toBe('Purchase contract')
            ->and($connection->refresh()->meta['profiles'])->toBe([['id' => '11', 'name' => 'Individual'], ['id' => '12', 'name' => 'Team']]);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/profile/11/loop?') && $r->hasHeader('Authorization', 'Bearer tok') && $r['include_details'] === 'true');

        $second = $driver->fetch($connection, 'tok', 'deals', $first->cursor, null);
        expect($second->done)->toBeTrue()->and($second->records)->toBe([]);
    });
});

/** The broker connects the brokerage's SkySlope on screen 40 with an access key and secret (demo data). */
function skySlopeConnected(object $test): Connection
{
    IntegrationSetting::query()->firstOrCreate(['integration_id' => 'skyslope'], ['mode' => IntegrationMode::Simulator]);
    $test->actingAs($test->linda)->post('/organisation/integrations/tools/skyslope/key', ['api_key' => 'AKIA-demo-1', 'api_secret' => 'shh-secret'])->assertSessionHasNoErrors()->assertRedirect();

    return Connection::query()->where('integration_id', 'skyslope')->sole();
}

describe('SkySlope on demo data (the B9.2 done-when)', function () {
    it('connects the brokerage once and matches each sale and listing to its agent by email', function () {
        $this->actingAs($this->linda)->get('/organisation/integrations')->assertInertia(fn (Assert $page) => $page
            ->where('tools', fn ($tools) => collect($tools)->firstWhere('id', 'skyslope')['connects_with'] === 'key_and_secret'));
        $connection = skySlopeConnected($this);

        expect($connection->owner_type)->toBe(Connection::OWNER_ORGANISATION)
            ->and(Deal::query()->where('user_id', $this->james->id)->pluck('property')->all())->toBe(['7 Cypress Creek Pkwy'])
            ->and(Deal::query()->where('user_id', $this->maria->id)->pluck('property')->all())->toBe(['310 Bluebonnet Ln'])
            ->and($connection->refresh()->meta['unmatched_owners'])->toBe(['tc@lonestar.example']);

        $sale = Deal::query()->where('user_id', $this->james->id)->with('dates')->sole();
        expect($sale)->stage->toBe('under_contract')->side->toBe('buy')->price->toBe(389000)->source->toBe('skyslope')
            ->and($sale->dates->pluck('kind')->all())->toBe(['closing'])
            ->and(Todo::query()->where('user_id', $this->james->id)->where('related_type', 'deal')->sole()->due_date?->toDateString())->toBe('2026-12-14');
        $listing = Deal::query()->where('user_id', $this->maria->id)->sole();
        expect($listing)->stage->toBe('active')->side->toBe('sell')->price->toBe(725000);

        // Closed in SkySlope: closed in REOS too.
        SkySlopeSimulator::changeInTool($connection, 'sale', 'ss-sale-1', ['status' => 'closed']);
        $this->actingAs($this->linda)->postJson("/api/v1/connections/{$connection->id}/sync")->assertAccepted();
        expect($sale->refresh()->stage)->toBe('closed');
    });

    it('needs both the key and the secret, and only from the broker', function () {
        IntegrationSetting::query()->firstOrCreate(['integration_id' => 'skyslope'], ['mode' => IntegrationMode::Simulator]);
        $this->actingAs($this->linda)->post('/organisation/integrations/tools/skyslope/key', ['api_key' => 'AKIA-demo-1'])->assertSessionHasErrors('api_secret');
        $this->actingAs($this->linda)->post('/organisation/integrations/tools/skyslope/key', ['api_key' => 'AK:IA', 'api_secret' => 'shh-secret'])->assertSessionHasErrors('api_key');
        $this->actingAs($this->james)->post('/organisation/integrations/tools/skyslope/key', ['api_key' => 'AKIA-demo-1', 'api_secret' => 'shh-secret'])->assertForbidden();
        // Lone Wolf takes no secret.
        IntegrationSetting::query()->firstOrCreate(['integration_id' => 'lone-wolf'], ['mode' => IntegrationMode::Simulator]);
        $this->actingAs($this->linda)->post('/organisation/integrations/tools/lone-wolf/key', ['api_key' => '7f3a2c1e-58a4', 'api_secret' => 'x1234'])->assertSessionHasErrors('api_secret');
        expect(Connection::query()->count())->toBe(0);
    });
});

describe('the real SkySlope driver', function () {
    it('logs in with an HMAC-signed request, then reads changed sales and listings with the session', function () {
        Cache::flush();
        Http::fake([
            'api.skyslope.com/auth/login' => Http::response(['Session' => 'sess-1', 'Expiration' => '2026-11-23T17:00:00Z']),
            'api.skyslope.com/api/files/sales*' => Http::response(['value' => ['sales' => [[
                'saleGuid' => 'g-1', 'email' => 'james.miller@demo.reos.test', 'status' => 'pending', 'salePrice' => 410000.0, 'escrowClosingDate' => '2026-12-20T00:00:00',
                'property' => ['streetNumber' => '12', 'streetAddress' => 'Pecan Dr', 'city' => 'Austin'], 'buyers' => [['firstName' => 'Ana', 'lastName' => 'Ruiz']], 'modifiedOn' => '2026-11-22T10:00:00',
            ]]], 'links' => []]),
            'api.skyslope.com/api/files/listings*' => Http::response(['value' => ['listings' => []], 'links' => []]),
        ]);
        $driver = new SkySlopeDriver('reos-client', 'reos-secret');
        $connection = new Connection(['integration_id' => 'skyslope', 'meta' => []]);

        expect($driver->accountForKey('AKIA1:shh')->externalId)->toBe('AKIA1');
        $sales = $driver->fetch($connection, 'AKIA1:shh', 'deals', null, CarbonImmutable::parse('2026-11-01'));
        $listings = $driver->fetch($connection, 'AKIA1:shh', 'deals', $sales->cursor, null);

        expect($sales->done)->toBeFalse()
            ->and($sales->records[0])->toMatchArray(['external_id' => 'sale:g-1', 'owner_email' => 'james.miller@demo.reos.test', 'property' => '12 Pecan Dr', 'stage' => 'under_contract', 'price' => 410000])
            ->and($sales->records[0]['dates'])->toBe([['kind' => 'closing', 'label' => null, 'due_on' => '2026-12-20', 'external_id' => 'g-1:escrowClosingDate']])
            ->and($listings->done)->toBeTrue();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/auth/login') && str_starts_with((string) $r->header('Authorization')[0], 'SS AKIA1:') && $r['clientID'] === 'reos-client'
            && $r->header('Authorization')[0] === 'SS AKIA1:'.base64_encode(hash_hmac('sha256', 'reos-client:reos-secret:'.$r->header('Timestamp')[0], 'shh', true)));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/files/sales') && $r->hasHeader('Session', 'sess-1') && str_contains($r->url(), 'earliestDate='));
        // One login, reused for the session's life.
        Http::assertSentCount(3);
    });

    it('turns a refused key into a reconnect', function () {
        Cache::flush();
        Http::fake(['api.skyslope.com/auth/login' => Http::response(['message' => 'Unauthorized'], 401)]);

        expect(fn () => (new SkySlopeDriver('reos-client', 'reos-secret'))->accountForKey('AKIA1:wrong'))->toThrow(AccessRevoked::class);
    });
});
