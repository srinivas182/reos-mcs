<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\ConnectionStatus;
use App\Enums\OrganisationType;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\Organisation;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/** Tenant isolation (A4.1, ADR 0015): no tenant can read another's data. */
function isolationConnection(string $ownerType, int $ownerId, string $account): Connection
{
    return Connection::query()->create([
        'integration_id' => 'google-workspace', 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'mode' => IntegrationMode::Simulator,
        'external_account_id' => $account, 'scopes' => [], 'access_token' => 'x', 'status' => ConnectionStatus::Active,
    ]);
}

function hideFor(Organisation $organisation, string $tool): void
{
    DB::table('organisation_hidden_integrations')->insert(['organisation_id' => $organisation->id, 'integration_id' => $tool, 'created_at' => now()]);
}

/** @return array{0: Organisation, 1: Organisation, 2: User} Two brokerages with tenant data, and a person with their own. */
function twoTenants(): array
{
    $lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty']);
    $gulf = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Gulf Coast Homes']);
    $maria = User::factory()->create();

    isolationConnection(Connection::OWNER_ORGANISATION, $lone->id, 'lone-star');
    isolationConnection(Connection::OWNER_ORGANISATION, $gulf->id, 'gulf-coast');
    isolationConnection(Connection::OWNER_USER, $maria->id, 'maria');
    hideFor($lone, 'zoom');
    hideFor($gulf, 'lofty');

    return [$lone, $gulf, $maria];
}

describe('in the database (PostgreSQL row-level security)', function () {
    beforeEach(function () {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Row-level security is PostgreSQL only; CI runs this on PostgreSQL.');
        }
    });

    it('shows a tenant only its own rows, even to raw SQL', function () {
        [$lone, $gulf] = twoTenants();

        app(Tenancy::class)->run($lone->id, function () use ($lone) {
            expect(DB::table('organisation_hidden_integrations')->pluck('integration_id')->all())->toBe(['zoom'])
                ->and(DB::table('connections')->orderBy('external_account_id')->pluck('external_account_id')->all())->toBe(['lone-star', 'maria'])
                ->and(DB::select("select count(*) as n from connections where owner_type = 'organisation'")[0]->n)->toBe(1)
                ->and(DB::table('connections')->where('organisation_id', '!=', $lone->id)->whereNotNull('organisation_id')->count())->toBe(0);
        });
    });

    it('refuses to write another tenant’s rows', function () {
        [$lone, $gulf] = twoTenants();

        app(Tenancy::class)->run($lone->id, function () use ($gulf) {
            expect(fn () => DB::transaction(fn () => hideFor($gulf, 'zoom')))->toThrow(QueryException::class);
            expect(DB::table('connections')->where('external_account_id', 'gulf-coast')->update(['account_label' => 'taken']))->toBe(0)
                ->and(DB::table('connections')->where('external_account_id', 'gulf-coast')->delete())->toBe(0);
        });

        expect(DB::table('connections')->where('external_account_id', 'gulf-coast')->value('account_label'))->toBeNull();
    });

    it('shows no tenant rows without a tenant, and everything in platform scope', function () {
        twoTenants();

        app(Tenancy::class)->enterNone();
        expect(DB::table('organisation_hidden_integrations')->count())->toBe(0)
            ->and(DB::table('connections')->pluck('external_account_id')->all())->toBe(['maria']);

        app(Tenancy::class)->enterPlatform();
        expect(DB::table('organisation_hidden_integrations')->count())->toBe(2)
            ->and(DB::table('connections')->count())->toBe(3);
    });

    it('fails closed when the session has no tenant setting at all', function () {
        twoTenants();

        DB::statement("select set_config('reos.tenant', '', false), set_config('reos.scope', '', false)");
        expect(DB::table('organisation_hidden_integrations')->count())->toBe(0);
        app(Tenancy::class)->enterPlatform();
    });

    it('is enforced for the database user the app connects as', function () {
        expect(Artisan::call('reos:tenancy:check'))->toBe(0);
    });

    it('fails the check when a new tenant table has no policy', function () {
        Schema::create('unprotected_notes', function ($table) {
            $table->id();
            $table->foreignId('organisation_id');
        });

        expect(Artisan::call('reos:tenancy:check'))->toBe(1)
            ->and(Artisan::output())->toContain('unprotected_notes');
    });
});

describe('in the app (every database)', function () {
    it('scopes Eloquent queries to the tenant', function () {
        [$lone] = twoTenants();

        $seen = app(Tenancy::class)->run($lone->id, fn () => Connection::query()->orderBy('external_account_id')->pluck('external_account_id')->all());
        expect($seen)->toBe(['lone-star', 'maria']);

        app(Tenancy::class)->enterNone();
        expect(Connection::query()->pluck('external_account_id')->all())->toBe(['maria']);
        app(Tenancy::class)->enterPlatform();
    });

    it('sets the tenant from the role in use; platform for super admins; none for visitors', function () {
        Route::middleware('web')->get('/_tenancy-probe', fn () => app(Tenancy::class)->forJob());
        $brokerage = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();

        $this->actingAs(User::factory()->withRole(Role::Agent, $brokerage)->create())->get('/_tenancy-probe')->assertSee((string) $brokerage->id);
        $this->actingAs(User::factory()->superAdmin()->create())->get('/_tenancy-probe')->assertSee('platform');
        auth()->logout();
        $this->get('/_tenancy-probe')->assertSee('none');

        // Platform scope comes back for the test after each request.
        expect(app(Tenancy::class)->isPlatform())->toBeTrue();
    });

    it('never shows a broker another brokerage’s connections through the API', function () {
        [$lone, $gulf] = twoTenants();
        $broker = User::factory()->withRole(Role::Broker, $lone)->withTwoFactor()->create();
        $theirs = Connection::query()->where('external_account_id', 'gulf-coast')->sole();

        $this->actingAs($broker)->get('/connections')->assertOk()->assertInertia(fn ($page) => $page
            ->where('connections', fn ($connections) => collect($connections)->pluck('account')->doesntContain('gulf-coast')));
        $this->actingAs($broker)->post("/connections/{$theirs->id}/sync")->assertNotFound();
        $this->actingAs($broker)->delete("/connections/{$theirs->id}")->assertNotFound();
        expect(Connection::query()->whereKey($theirs->id)->exists())->toBeTrue();
    });

    it('runs queued jobs as the tenant that queued them', function () {
        $brokerage = Organisation::factory()->ofType(OrganisationType::Brokerage)->create();
        // Queued closures are serialized, so they report through config (same process, sync queue).
        config(['probe.seen' => []]);
        $record = static fn () => config(['probe.seen' => [...config('probe.seen'), app(Tenancy::class)->forJob()]]);

        app(Tenancy::class)->run($brokerage->id, function () use ($record): void {
            dispatch($record); // sent when the pending dispatch is destroyed, at the end of this statement
        });
        dispatch($record);

        expect(config('probe.seen'))->toBe([(string) $brokerage->id, 'platform'])
            ->and(app(Tenancy::class)->isPlatform())->toBeTrue();
    });

    it('keeps cache keys and files apart', function () {
        $tenancy = app(Tenancy::class);
        $tenancy->enter(12);
        expect($tenancy->cacheKey('brand'))->toBe('t12:brand')
            ->and($tenancy->path('logos/logo.png'))->toBe('tenants/12/logos/logo.png');
        $tenancy->enterPlatform();
        expect($tenancy->cacheKey('brand'))->toBe('platform:brand')
            ->and($tenancy->path('exports'))->toBe('platform/exports');
    });

    it('gives every tenant a unique slug', function () {
        $first = Organisation::factory()->create(['name' => 'Lone Star Realty']);
        $second = Organisation::factory()->create(['name' => 'Lone Star Realty']);

        expect($first->slug)->toBe('lone-star-realty')
            ->and($second->slug)->toStartWith('lone-star-realty-')->not->toBe($first->slug);
    });
});
