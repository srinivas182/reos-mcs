<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Features\Flags;
use App\Features\RolloutStage;
use App\Models\AuditEvent;
use App\Models\FeatureFlag;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\ProviderLicence;
use App\Models\ProviderProfile;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\ProviderNetwork\NetworkRules;
use App\Services\Authorization\Memberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Joining the provider network in about ten minutes (B15.2, ADR 0074).
 */

beforeEach(function () {
    Notification::fake();
    Storage::fake(config()->string('reos.content.disk'));
    $this->travelTo('2026-11-30 15:00:00');
    $this->artisan('reos:regulators:import')->assertSuccessful();
    $this->ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
    $this->openNetwork = function (): void {
        app(Flags::class)->flag('provider_network');
        FeatureFlag::query()->where('key', 'provider_network')->update(['stage' => RolloutStage::Everyone]);
        app(Flags::class)->changed(FeatureFlag::query()->where('key', 'provider_network')->sole());
    };
    // Agents listing in Katy and Fulshear this year (the MLS feed).
    foreach ([['Katy', '77494', 29.78, -95.82, 'A1'], ['Katy', '77494', 29.79, -95.81, 'A2'], ['Katy', '77450', 29.75, -95.75, 'A3'], ['Fulshear', '77441', 29.69, -95.89, 'A4']] as [$city, $zip, $lat, $lng, $agent]) {
        DB::table('mls_listings')->insert([
            'uuid' => (string) Str::uuid(), 'integration' => 'har-mls', 'listing_key' => Str::random(10), 'status' => 'active', 'city' => $city, 'state' => 'TX', 'postal_code' => $zip,
            'latitude' => $lat, 'longitude' => $lng, 'list_agent_key' => $agent, 'listed_on' => '2026-10-01', 'photos' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

/** Pays for the plan on the checkout page the way a browser would. */
function payForPlan(object $test, string $checkoutUrl): void
{
    parse_str((string) parse_url($checkoutUrl, PHP_URL_QUERY), $query);
    $price = (int) basename((string) parse_url($checkoutUrl, PHP_URL_PATH));
    $test->get($checkoutUrl)->assertOk();
    $order = Order::query()->where('uuid', $test->postJson("/checkout/{$price}", ['signup' => $query['signup']])->assertCreated()->json('order'))->sole();
    $test->post("/checkout/orders/{$order->uuid}/simulate");
    $test->get("/checkout/orders/{$order->uuid}/return")->assertOk();
}

describe('joining the provider network (B15.2)', function () {
    it('takes a loan officer from welcome to live (the done-when)', function () {
        ($this->openNetwork)();
        $maya = User::factory()->create(['name' => 'Maya Chen']);
        $start = now();

        $status = $this->actingAs($maya)->getJson('/api/v1/provider/join')->assertOk()->json('data');
        expect($status['status'])->toBe('joining')->and($status['done'])->toBe(0)->and(array_column($status['steps'], 'key'))->toBe(['welcome', 'category', 'licence', 'areas', 'plan', 'rules']);

        $this->postJson('/api/v1/provider/join/welcome')->assertOk()->assertJsonPath('data.done', 1);
        $this->putJson('/api/v1/provider/profile/category', ['category' => 'loan-officer'])->assertOk();
        $this->postJson('/api/v1/provider/licences', ['kind' => 'nmls', 'number' => '1234567'])->assertOk()->assertJsonPath('data.status', 'review');

        $areas = $this->postJson('/api/v1/provider/join/areas', ['kind' => 'city', 'city' => 'Katy'])->assertOk()->json('data.areas');
        expect($areas[0])->toMatchArray(['kind' => 'city', 'city' => 'Katy', 'agents' => 3, 'providers' => 0]);
        $radius = $this->postJson('/api/v1/provider/join/areas', ['kind' => 'radius', 'postal_code' => '77494', 'radius_miles' => 15])->assertOk()->json('data');
        expect($radius['areas'][1])->toMatchArray(['kind' => 'radius', 'postal_code' => '77494', 'radius_miles' => 15, 'agents' => 4])
            ->and(array_column($radius['plans'], 'tier'))->toBe(['basic', 'pro', 'premium'])
            ->and($radius['plans'][1])->toBe(['tier' => 'pro', 'monthly' => 14900, 'areas' => 3]);

        // Listed has one area; Pro has three.
        $this->postJson('/api/v1/provider/join/plan', ['tier' => 'basic'])->assertUnprocessable()->assertJsonValidationErrors('tier');
        $checkout = $this->postJson('/api/v1/provider/join/plan', ['tier' => 'pro'])->assertOk()->json('data.checkout_url');
        payForPlan($this, $checkout);

        $profile = ProviderProfile::query()->sole();
        $business = Organisation::query()->findOrFail($profile->organisation_id);
        expect($profile->tier)->toBe('pro')->and($business->name)->toBe('Maya Chen')
            ->and(app(Memberships::class)->holdersIn(Role::Provider, $business)->pluck('id')->all())->toBe([$maya->id]);

        $this->actingAs($maya->refresh())->postJson('/api/v1/provider/join/rules', ['version' => '2020-01-01', 'accept' => true])->assertUnprocessable()->assertJsonValidationErrors('version');
        $done = $this->postJson('/api/v1/provider/join/rules', ['version' => NetworkRules::VERSION, 'accept' => true])->assertOk()->json('data');
        // Every step done; the NMLS ID is still with a reviewer.
        expect($done['done'])->toBe(6)->and($done['status'])->toBe('joining')->and($done['licence']['verified'])->toBeFalse();

        $licence = ProviderLicence::query()->where('user_id', $maya->id)->sole();
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->timestamp])->post("/admin/people/provider-licences/{$licence->id}/approve")->assertRedirect();
        expect($licence->refresh()->status)->toBe('verified');

        $this->travel(8)->minutes();
        expect(ProviderProfile::query()->sole())->toMatchArray(['status' => 'live'])
            ->and(ProviderProfile::query()->sole()->live_at?->diffInMinutes($start))->toBeLessThanOrEqual(10)
            ->and(ProviderProfile::query()->sole()->rules_version)->toBe(NetworkRules::VERSION)
            ->and(AuditEvent::query()->where('action', 'provider.live')->count())->toBe(1);

        $this->actingAs($maya)->get('/provider/join')->assertOk()->assertInertia(fn (Assert $page) => $page->component('provider/join')->where('onboarding.status', 'live'));
    });

    it('waits for the network to open in the state', function () {
        $pat = User::factory()->create(['name' => 'Pat Rivera']);
        $this->actingAs($pat)->postJson('/api/v1/provider/join/welcome');
        $this->putJson('/api/v1/provider/profile/category', ['category' => 'home-inspector']);
        $this->postJson('/api/v1/provider/licences', ['kind' => 'trec_inspector', 'number' => '24567'])->assertJsonPath('data.status', 'verified');
        $this->postJson('/api/v1/provider/join/areas', ['kind' => 'zip', 'postal_code' => '77494']);
        payForPlan($this, $this->postJson('/api/v1/provider/join/plan', ['tier' => 'basic'])->json('data.checkout_url'));
        $this->actingAs($pat->refresh())->postJson('/api/v1/provider/join/rules', ['version' => NetworkRules::VERSION, 'accept' => true])->assertJsonPath('data.status', 'waiting');

        ($this->openNetwork)();
        $this->postJson('/api/v1/provider/join/welcome')->assertJsonPath('data.status', 'live');
    });

    it('hides a profile whose licence expired at the monthly re-check, and shows it again once fixed', function () {
        ($this->openNetwork)();
        $pat = User::factory()->create(['name' => 'Pat Rivera']);
        $this->actingAs($pat)->postJson('/api/v1/provider/join/welcome');
        $this->putJson('/api/v1/provider/profile/category', ['category' => 'home-inspector']);
        $this->postJson('/api/v1/provider/licences', ['kind' => 'trec_inspector', 'number' => '24567']);
        $this->postJson('/api/v1/provider/join/areas', ['kind' => 'city', 'city' => 'Katy']);
        payForPlan($this, $this->postJson('/api/v1/provider/join/plan', ['tier' => 'basic'])->json('data.checkout_url'));
        $this->actingAs($pat->refresh())->postJson('/api/v1/provider/join/rules', ['version' => NetworkRules::VERSION, 'accept' => true])->assertJsonPath('data.status', 'live');

        RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'inspectors')->where('number_digits', 'like', '%24567')->update(['expires_on' => '2026-11-01']);
        $this->artisan('reos:providers:recheck')->assertSuccessful();
        expect(ProviderProfile::query()->sole())->toMatchArray(['status' => 'hidden', 'hidden_reason' => 'licence_expired']);
        $this->getJson('/api/v1/provider/join')->assertJsonPath('data.status', 'hidden')->assertJsonPath('data.hidden_reason', 'licence_expired');

        RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'inspectors')->where('number_digits', 'like', '%24567')->update(['expires_on' => '2028-11-30']);
        $this->artisan('reos:providers:recheck')->assertSuccessful();
        expect(ProviderProfile::query()->sole()->status)->toBe('live');
    });

    it('checks business details and a photo ID for a category without a licence', function () {
        $sam = User::factory()->create(['name' => 'Sam Patel']);
        $this->actingAs($sam)->putJson('/api/v1/provider/profile/category', ['category' => 'photographer'])->assertOk();
        $this->postJson('/api/v1/provider/join/business', ['name' => 'Patel Photo', 'address' => '12 Main St, Katy TX'])->assertUnprocessable()->assertJsonValidationErrors('id_photo');
        $done = $this->post('/api/v1/provider/join/business', ['name' => 'Patel Photo', 'address' => '12 Main St, Katy TX', 'id_photo' => UploadedFile::fake()->image('licence.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->json('data');
        expect($done['licence'])->toMatchArray(['kind' => 'business', 'business' => ['name' => 'Patel Photo', 'address' => '12 Main St, Katy TX'], 'verified' => false])
            ->and(collect($done['steps'])->firstWhere('key', 'licence')['done'])->toBeTrue();

        $id = ProviderLicence::query()->where('kind', 'business_id')->sole();
        Storage::disk(config()->string('reos.content.disk'))->assertExists((string) $id->document_path);
        $this->actingAs($this->ops)->withSession(['auth.password_confirmed_at' => now()->timestamp])->get('/admin/people')->assertInertia(fn (Assert $page) => $page->where('providerLicenceReviews.0.document_url', route('people.provider-licences.document', $id)));
        $this->get("/admin/people/provider-licences/{$id->id}/document")->assertOk();
        $this->actingAs($sam)->get("/admin/people/provider-licences/{$id->id}/document")->assertForbidden();
    });

    it('keeps areas to ten, without duplicates', function () {
        $sam = User::factory()->create();
        $this->actingAs($sam)->putJson('/api/v1/provider/profile/category', ['category' => 'photographer']);
        $this->postJson('/api/v1/provider/join/areas', ['kind' => 'zip', 'postal_code' => '7749'])->assertJsonValidationErrors('postal_code');
        $this->postJson('/api/v1/provider/join/areas', ['kind' => 'city', 'city' => 'Katy'])->assertOk();
        $this->postJson('/api/v1/provider/join/areas', ['kind' => 'city', 'city' => 'katy'])->assertJsonValidationErrors('area');
        $id = $this->getJson('/api/v1/provider/join')->json('data.areas.0.id');
        $this->deleteJson("/api/v1/provider/join/areas/{$id}")->assertOk()->assertJsonPath('data.areas', []);

        foreach (range(1, 10) as $n) {
            $this->postJson('/api/v1/provider/join/areas', ['kind' => 'zip', 'postal_code' => (string) (77400 + $n)])->assertOk();
        }

        $this->postJson('/api/v1/provider/join/areas', ['kind' => 'zip', 'postal_code' => '77499'])->assertJsonValidationErrors('area');
    });
});
