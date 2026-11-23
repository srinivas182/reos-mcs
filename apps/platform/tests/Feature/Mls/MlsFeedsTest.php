<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorUnavailable;
use App\Integrations\Catalogue;
use App\Integrations\Checks\TesterRegistry;
use App\Integrations\Checks\Testers\MlsFeedTester;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncMlsFeed;
use App\Mls\MlsFeeds;
use App\Mls\MlsSync;
use App\Models\IntegrationSetting;
use App\Models\Licence;
use App\Models\MlsFeed;
use App\Models\MlsListing;
use App\Models\MlsMember;
use App\Models\MlsOpenHouse;
use App\Models\Organisation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
});

function mlsSync(string $integration): array
{
    return app(MlsSync::class)->run(app(MlsFeeds::class)->find($integration) ?? throw new RuntimeException($integration));
}

function mlsOn(string $integration, IntegrationMode $mode, array $keys): void
{
    app(CredentialVault::class)->put($integration, $mode === IntegrationMode::Live ? CredentialEnvironment::Live : CredentialEnvironment::Sandbox, $keys, null);
    IntegrationSetting::query()->updateOrCreate(['integration_id' => $integration], ['mode' => $mode]);
}

/** A RESO listing as vendors send it. */
function resoListing(string $key, string $status, string $modified, array $extra = []): array
{
    return [
        'ListingKey' => $key, 'ListingId' => 'L'.$key, 'StandardStatus' => $status, 'UnparsedAddress' => "1 {$key} St, Austin, TX 78701",
        'City' => 'Austin', 'StateOrProvince' => 'TX', 'PostalCode' => '78701', 'ListPrice' => 450000, 'BedroomsTotal' => 3,
        'BathroomsTotalInteger' => 2, 'LivingArea' => 1800, 'ListAgentKey' => 'AG1', 'ModificationTimestamp' => $modified, ...$extra,
    ];
}

/**
 * Answers a vendor's OData requests from `$pages`: "Resource" => list of pages,
 * each a list of records; next links follow on the same host.
 *
 * @param  array<string, list<list<array<string, mixed>>>>  $pages
 */
function fakeReso(string $host, array $pages, array $extra = []): void
{
    // A fresh fake each time, so a later call replaces the earlier answers.
    Http::swap(new Factory);
    Http::fake([...$extra, "{$host}/*" => function (Request $request) use ($host, $pages) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $resource = basename($path);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $index = (int) ($query['page'] ?? 0);

        if (isset($query['$select'])) {
            $resource = 'keys';
        }

        $list = $pages[$resource] ?? [[]];
        $next = isset($list[$index + 1]) ? "https://{$host}{$path}?page=".($index + 1) : null;

        return Http::response(array_filter(['value' => $list[$index] ?? [], '@odata.nextLink' => $next], static fn ($v) => $v !== null));
    }]);
}

describe('the MLS feeds (B10.1)', function () {
    it('come from the register: one entry per MLS with its vendor, and a tester for each', function () {
        $feeds = app(MlsFeeds::class)->all();
        $ids = array_map(static fn ($d) => $d->id, $feeds);
        $mls = array_filter(app(Catalogue::class)->all(), static fn ($d) => $d->category === 'mls');

        expect($ids)->toBe(array_values(array_map(static fn ($d) => $d->id, $mls)))
            ->and($ids)->toContain('mls-har', 'mls-ntreis', 'mls-unlock', 'mls-sabor')
            ->and(app(MlsFeeds::class)->find('mls-har')?->mls?->shortName)->toBe('HAR')
            ->and(app(TesterRegistry::class)->for('mls-ntreis'))->toBeInstanceOf(MlsFeedTester::class);
    });

    it('are synced every 15 minutes', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'reos:mls:sync'));

        expect($event?->expression)->toBe('*/15 * * * *');
    });

    it('replicate the Simulator\'s demo listings, members, offices and open houses, leaving off-market ones out', function () {
        $this->artisan('reos:mls:sync --now')->assertSuccessful();

        expect(MlsListing::query()->where('integration', 'mls-har')->count())->toBe(6)
            ->and(MlsListing::query()->where('listing_key', 'HAR-L7')->exists())->toBeFalse()
            ->and(MlsListing::query()->where('integration', 'mls-ntreis')->count())->toBe(2)
            ->and(MlsMember::query()->where('licence_digits', '798765')->sole()->name)->toBe('James Miller')
            ->and(MlsOpenHouse::query()->where('integration', 'mls-har')->count())->toBe(2)
            ->and(MlsFeed::query()->where('integration', 'mls-har')->sole())->source->toBe('simulator')->synced_at->not->toBeNull();

        $listing = MlsListing::query()->where('listing_key', 'HAR-L1')->sole();
        expect($listing)->status->toBe('active')->list_price->toBe(489000)->bedrooms->toBe(4)->list_office_name->toBe('Lone Star Realty')
            ->and($listing->photos)->toHaveCount(4)
            ->and($listing->photos[0]['caption'])->toBe('Front');

        // The next run repeats only the last instant (upserts), and adds nothing.
        $this->artisan('reos:mls:sync --now --mls=mls-har')->assertSuccessful();
        expect(MlsListing::query()->where('integration', 'mls-har')->count())->toBe(6);
    });

    it('queue one job per MLS without --now', function () {
        Queue::fake();
        $this->artisan('reos:mls:sync')->assertSuccessful();

        Queue::assertPushed(SyncMlsFeed::class, 4);
    });
});

describe('the RESO Web API driver (B10.1, ADR 0042)', function () {
    it('reads Bridge with the server token, pages by next link, then replicates from the last change', function () {
        mlsOn('mls-har', IntegrationMode::Sandbox, ['access_token' => 'bridge-token']);
        fakeReso('api.bridgedataoutput.com', [
            'Property' => [
                [resoListing('A1', 'Active', '2026-11-22T10:00:00Z', ['Media' => [['MediaURL' => 'https://photos.example/a1-2.jpg', 'Order' => 2], ['MediaURL' => 'https://photos.example/a1-1.jpg', 'Order' => 1, 'ShortDescription' => 'Kitchen']]])],
                [resoListing('A2', 'Pending', '2026-11-22T11:30:00.250Z')],
            ],
            'Member' => [[['MemberKey' => 'AG1', 'MemberFullName' => 'Kim Lee', 'MemberStateLicense' => '0612345', 'OfficeKey' => 'OF1', 'ModificationTimestamp' => '2026-11-20T00:00:00Z']]],
            'keys' => [[['ListingKey' => 'A1'], ['ListingKey' => 'A2']]],
        ]);

        expect(mlsSync('mls-har'))->toMatchArray(['source' => 'reso', 'saved' => 3]);

        $a1 = MlsListing::query()->where('listing_key', 'A1')->sole();
        expect($a1->photos)->toBe([['url' => 'https://photos.example/a1-1.jpg', 'caption' => 'Kitchen'], ['url' => 'https://photos.example/a1-2.jpg', 'caption' => null]])
            ->and(MlsListing::query()->where('listing_key', 'A2')->sole()->status)->toBe('pending')
            ->and(MlsFeed::query()->where('integration', 'mls-har')->sole()->cursors['Property'])->toBe('2026-11-22T11:30:00.250Z');

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.bridgedataoutput.com/api/v2/OData/har/Property?')
            && $r->hasHeader('Authorization', 'Bearer bridge-token') && ! str_contains(urldecode($r->url()), '$expand'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/Property?page=1'));

        mlsSync('mls-har');
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), '$filter=ModificationTimestamp ge 2026-11-22T11:30:00.250Z'));
    });

    it('signs in to Trestle with client credentials and expands photos', function () {
        mlsOn('mls-ntreis', IntegrationMode::Sandbox, ['client_id' => 'reos', 'client_secret' => 'secret']);
        fakeReso('api-trestle.corelogic.com', [
            'Property' => [[resoListing('N1', 'Active', '2026-11-22T10:00:00Z', ['Media' => [['MediaURL' => 'https://photos.example/n1.jpg', 'Order' => 1, 'MediaCategory' => 'Photo'], ['MediaURL' => 'https://photos.example/plan.pdf', 'MediaCategory' => 'Document']]])]],
            'keys' => [[['ListingKey' => 'N1']]],
        ], ['api-trestle.corelogic.com/trestle/oidc/connect/token' => Http::response(['access_token' => 'trestle-token', 'expires_in' => 28800])]);

        mlsSync('mls-ntreis');

        expect(MlsListing::query()->where('listing_key', 'N1')->sole()->photos)->toBe([['url' => 'https://photos.example/n1.jpg', 'caption' => null]]);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api-trestle.corelogic.com/trestle/oidc/connect/token' && $r['grant_type'] === 'client_credentials' && $r['client_id'] === 'reos');
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), 'odata/Property?') && str_contains(urldecode($r->url()), '$expand=Media') && $r->hasHeader('Authorization', 'Bearer trestle-token'));
    });

    it('names the MLS on MLS Grid, and removes what the MLS withdraws or takes off the market', function () {
        mlsOn('mls-unlock', IntegrationMode::Sandbox, ['access_token' => 'grid-token']);
        fakeReso('api.mlsgrid.com', [
            'Property' => [[resoListing('U1', 'Active', '2026-11-22T10:00:00Z'), resoListing('U2', 'Active', '2026-11-22T10:05:00Z'), resoListing('U3', 'Active', '2026-11-22T10:06:00Z')]],
            'keys' => [[['ListingKey' => 'U1', 'MlgCanView' => true], ['ListingKey' => 'U2', 'MlgCanView' => true], ['ListingKey' => 'U3', 'MlgCanView' => true]]],
        ]);
        mlsSync('mls-unlock');
        expect(MlsListing::query()->where('integration', 'mls-unlock')->count())->toBe(3);
        Http::assertSent(fn (Request $r) => str_contains(urldecode($r->url()), "OriginatingSystemName eq 'actris'"));

        fakeReso('api.mlsgrid.com', [
            'Property' => [[resoListing('U1', 'Withdrawn', '2026-11-23T08:00:00Z'), resoListing('U2', 'Active', '2026-11-23T08:01:00Z', ['MlgCanView' => false])]],
        ]);
        mlsSync('mls-unlock');

        expect(MlsListing::query()->where('integration', 'mls-unlock')->pluck('listing_key')->all())->toBe(['U3']);
    });

    it('removes listings deleted at the MLS, once a day', function () {
        mlsOn('mls-har', IntegrationMode::Sandbox, ['access_token' => 't']);
        fakeReso('api.bridgedataoutput.com', [
            'Property' => [[resoListing('B1', 'Active', '2026-11-22T10:00:00Z'), resoListing('B2', 'Active', '2026-11-22T10:01:00Z')]],
            'keys' => [[['ListingKey' => 'B1'], ['ListingKey' => 'B2']]],
        ]);
        mlsSync('mls-har');

        fakeReso('api.bridgedataoutput.com', ['keys' => [[['ListingKey' => 'B1']]]]);
        mlsSync('mls-har');
        expect(MlsListing::query()->where('integration', 'mls-har')->count())->toBe(2);

        $this->travel(25)->hours();
        mlsSync('mls-har');
        expect(MlsListing::query()->where('integration', 'mls-har')->pluck('listing_key')->all())->toBe(['B1']);
    });

    it('never mixes sample data with real: switching from the Simulator purges it', function () {
        mlsSync('mls-har');
        expect(MlsListing::query()->where('integration', 'mls-har')->count())->toBe(6);

        mlsOn('mls-har', IntegrationMode::Sandbox, ['access_token' => 't']);
        fakeReso('api.bridgedataoutput.com', ['Property' => [[resoListing('R1', 'Active', '2026-11-22T10:00:00Z')]], 'keys' => [[['ListingKey' => 'R1']]]]);
        mlsSync('mls-har');

        expect(MlsListing::query()->where('integration', 'mls-har')->pluck('listing_key')->all())->toBe(['R1'])
            ->and(MlsMember::query()->where('integration', 'mls-har')->count())->toBe(0);
    });

    it('records a refused key or a next link to another host, and stays off for a vendor not yet named', function () {
        mlsOn('mls-har', IntegrationMode::Sandbox, ['access_token' => 'old']);
        Http::fake(['api.bridgedataoutput.com/*' => Http::response(['error' => 'unauthorized'], 401)]);

        expect(fn () => mlsSync('mls-har'))->toThrow(AccessRevoked::class)
            ->and(MlsFeed::query()->where('integration', 'mls-har')->sole())->failures->toBe(1)->last_error->toContain('401');

        mlsOn('mls-ntreis', IntegrationMode::Sandbox, ['client_id' => 'a', 'client_secret' => 'b']);
        Http::swap(new Factory);
        Http::fake([
            'api-trestle.corelogic.com/trestle/oidc/connect/token' => Http::response(['access_token' => 'x', 'expires_in' => 3600]),
            'api-trestle.corelogic.com/*' => Http::response(['value' => [], '@odata.nextLink' => 'https://evil.example/steal']),
        ]);
        expect(fn () => mlsSync('mls-ntreis'))->toThrow(VendorUnavailable::class);

        mlsOn('mls-sabor', IntegrationMode::Sandbox, ['server_url' => 'https://sabor.example/odata', 'client_id' => 'a', 'client_secret' => 'b']);
        expect(mlsSync('mls-sabor'))->toBe(['source' => null, 'saved' => 0, 'removed' => 0]);
    });

    it('tests REOS\'s MLS keys with one office record', function () {
        mlsOn('mls-ntreis', IntegrationMode::Sandbox, ['client_id' => 'reos', 'client_secret' => 'secret']);
        Http::fake([
            'api-trestle.corelogic.com/trestle/oidc/connect/token' => Http::response(['access_token' => 'ok', 'expires_in' => 3600]),
            'api-trestle.corelogic.com/trestle/odata/Office*' => Http::response(['value' => [['OfficeKey' => 'O1']]]),
        ]);
        $definition = app(Catalogue::class)->find('mls-ntreis');

        expect(app(TesterRegistry::class)->for('mls-ntreis')->test($definition, CredentialEnvironment::Sandbox, ['client_id' => 'reos', 'client_secret' => 'secret'])->outcome->value)->toBe('passed');

        Http::swap(new Factory);
        Http::fake(['api-trestle.corelogic.com/*' => Http::response(['error' => 'invalid_client'], 400)]);
        $tester = app(TesterRegistry::class)->for('mls-ntreis');
        expect($tester->test($definition, CredentialEnvironment::Sandbox, ['client_id' => 'bad', 'client_secret' => 'x'])->outcome->value)->toBe('failed')
            ->and($tester->test(app(Catalogue::class)->find('mls-sabor'), CredentialEnvironment::Sandbox, [])->outcome->value)->toBe('unverified');
    });
});

describe('My listings (B10.1)', function () {
    beforeEach(function () {
        $this->artisan('reos:mls:sync --now')->assertSuccessful();
        $this->james = mlsAgent('James Miller', '0798765');
    });

    it('finds the agent\'s MLS member record by licence, and shows their listings once confirmed', function () {
        $this->actingAs($this->james)->getJson('/api/v1/listings')->assertOk()
            ->assertJsonPath('data.licensed', true)->assertJsonPath('data.linked', [])->assertJsonPath('data.listings', [])
            ->assertJsonPath('data.candidates.0.member_key', 'HAR-M1')->assertJsonPath('data.candidates.0.office', 'Lone Star Realty')
            ->assertJsonPath('data.candidates.0.short_name', 'HAR');

        $data = $this->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk()->json('data');

        expect($data['candidates'])->toBe([])
            ->and($data['sample'])->toBeTrue()
            ->and($data['synced_at'])->not->toBeNull()
            ->and(array_column($data['listings'], 'status'))->toBe(['coming_soon', 'active', 'pending', 'closed'])
            ->and(array_column($data['listings'], 'role'))->toBe(['list', 'list', 'co_list', 'list'])
            ->and($data['listings'][1])->toMatchArray(['address' => '2418 Willow Bend Dr, Katy, TX 77494', 'price' => 489000, 'beds' => 4, 'baths' => 3.0, 'days_on_market' => 12, 'short_name' => 'HAR', 'can_post' => true])
            ->and($data['listings'][1]['photos'])->toHaveCount(4)
            ->and($data['listings'][1]['attribution'])->toBe('Listing courtesy of Lone Star Realty. Source: HAR MLS.')
            ->and($data['listings'][1]['open_house'])->toMatchArray(['kind' => 'public'])
            ->and($data['listings'][3])->toMatchArray(['close_price' => 331000]);
    });

    it('lets the agent say "not me", unlink and link again; a record links to one account', function () {
        $this->actingAs($this->james)->postJson('/api/v1/listings/members/decline', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk()
            ->assertJsonPath('data.candidates', [])->assertJsonPath('data.linked', []);
        // Declined: it can't be confirmed afterwards from a stale screen.
        $this->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertUnprocessable();

        $dana = mlsAgent('Dana Brooks', '0711111');
        $this->actingAs($dana)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M2'])->assertOk()
            ->assertJsonCount(2, 'data.listings');
        $this->postJson('/api/v1/listings/members/unlink', ['mls' => 'mls-har', 'member_key' => 'HAR-M2'])->assertOk()
            ->assertJsonPath('data.listings', [])->assertJsonPath('data.candidates.0.member_key', 'HAR-M2');
        $this->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M2'])->assertOk();

        // Someone else with the same number can't take Dana's record, or one not under their licence.
        $other = mlsAgent('D. Brooks', '711111');
        $this->actingAs($other)->getJson('/api/v1/listings')->assertJsonPath('data.candidates', []);
        $this->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M2'])->assertUnprocessable();
        $this->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M4'])->assertUnprocessable()->assertJsonValidationErrors('member_key');
    });

    it('asks for a licence first, and is for agents and brokers only', function () {
        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->getJson('/api/v1/listings')->assertOk()->assertJsonPath('data.licensed', false)->assertJsonPath('data.candidates', []);

        $candidate = User::factory()->withRole(Role::Candidate, Organisation::platform())->create();
        $this->actingAs($candidate)->getJson('/api/v1/listings')->assertNotFound();
        $this->actingAs($candidate)->get('/deals/listings')->assertNotFound();
    });

    it('serves the page beside Deals', function () {
        $this->actingAs($this->james)->post('/deals/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'], ['Accept' => 'application/json'])->assertOk();

        $this->get('/deals/listings')->assertOk()->assertInertia(fn (Assert $page) => $page->component('listings/index')
            ->where('listings.linked.0.name', 'James Miller')->has('listings.listings', 4)->where('listings.sample', true));
    });
});
