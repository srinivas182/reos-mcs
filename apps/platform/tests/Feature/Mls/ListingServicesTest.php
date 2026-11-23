<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Mls\ListingPurpose;
use App\Mls\ListingService;
use App\Mls\MlsFeeds;
use App\Mls\OpenHouseCalendar;
use App\Models\CalendarEvent;
use App\Models\ListingPost;
use App\Models\MlsFeed;
use App\Models\MlsListing;
use App\Models\MlsOpenHouse;
use App\Models\Organisation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $this->james = mlsAgent('James Miller', '0798765');
    $this->actingAs($this->james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
    $this->listing = fn (string $key): MlsListing => MlsListing::query()->where('listing_key', $key)->sole();
});

describe('the display rules (B10.2, decision 7)', function () {
    it('come from the register and cap photos for every purpose, posts at 10', function () {
        $rules = app(ListingService::class)->rules('mls-har');
        $listing = ($this->listing)('HAR-L1');
        $listing->forceFill(['photos' => array_map(static fn (int $n) => ['url' => "https://photos.example/{$n}.jpg", 'caption' => null], range(1, 40))])->save();

        expect($rules)->maxPhotos->toBe(25)->refreshHours->toBe(12)->soldPublic->toBeFalse()->soldInCma->toBeTrue()->ownListingsOnly->toBeTrue()
            ->and(app(ListingService::class)->view($listing, ListingPurpose::Agent, true)['photos'])->toHaveCount(25)
            ->and(app(ListingService::class)->view($listing, ListingPurpose::Public)['photos'])->toHaveCount(25)
            ->and(app(ListingService::class)->view($listing, ListingPurpose::Post, true)['photos'])->toHaveCount(10);
    });

    it('keep sold prices off public pages and posts, but in the agent\'s own view and CMAs', function () {
        $sold = ($this->listing)('HAR-L4');
        $service = app(ListingService::class);

        expect($service->view($sold, ListingPurpose::Post, true))->toBeNull()
            ->and($service->view($sold, ListingPurpose::Public))->toBeNull()
            ->and($service->view($sold, ListingPurpose::Agent, true))->toMatchArray(['close_price' => 331000, 'price_per_sqft' => 186])
            ->and($service->view($sold, ListingPurpose::Cma)['close_price'])->toBe(331000);
    });

    it('show nothing to the public from a stale feed, honour the MLS\'s consumer flags, and name the brokerage and MLS', function () {
        $service = new ListingService(app(MlsFeeds::class));
        $listing = ($this->listing)('HAR-L1');

        expect($service->view($listing, ListingPurpose::Public)['attribution'])->toBe('Listing courtesy of Lone Star Realty. Source: HAR MLS.')
            ->and($service->view($listing, ListingPurpose::Public)['sample'])->toBeTrue()
            ->and($service->view(($this->listing)('HAR-L5'), ListingPurpose::Post))->toBeNull(); // Maria's listing: posts are for one's own listings.

        $listing->forceFill(['address_shown' => false])->save();
        expect($service->view($listing, ListingPurpose::Public))->toMatchArray(['address' => 'Address withheld, Katy', 'latitude' => null])
            ->and($service->view($listing, ListingPurpose::Agent, true)['address'])->toBe('2418 Willow Bend Dr, Katy, TX 77494');

        $listing->forceFill(['idx' => false])->save();
        expect($service->view($listing, ListingPurpose::Public))->toBeNull();

        $listing->forceFill(['idx' => true])->save();
        MlsFeed::query()->where('integration', 'mls-har')->update(['synced_at' => now()->subHours(13)]);
        $fresh = new ListingService(app(MlsFeeds::class));
        expect($fresh->view($listing, ListingPurpose::Public))->toBeNull()
            ->and($fresh->view($listing, ListingPurpose::Post, true))->toBeNull()
            ->and($fresh->view($listing, ListingPurpose::Agent, true))->not->toBeNull();
    });
});

describe('listing posts (B10.2: MLS photos within the licence terms)', function () {
    it('suggest a caption and the first photos, and save the agent\'s choice with the attribution', function () {
        $uuid = ($this->listing)('HAR-L1')->uuid;

        $draft = $this->getJson("/api/v1/listings/{$uuid}/post")->assertOk()->json('data');
        expect($draft['caption'])->toBe('Just listed: 2418 Willow Bend Dr, Katy, TX 77494, Katy. $489,000 · 4 bd · 3 ba · 2,850 sq ft')
            ->and($draft['photos'])->toHaveCount(4)->and($draft['max_photos'])->toBe(4)->and($draft['chosen'])->toHaveCount(4)
            ->and($draft['attribution'])->toBe('Listing courtesy of Lone Star Realty. Source: HAR MLS.')
            ->and($draft['saved_at'])->toBeNull();

        $photos = array_column($draft['photos'], 'url');
        $this->putJson("/api/v1/listings/{$uuid}/post", ['caption' => 'Open Saturday!', 'photos' => [$photos[2], $photos[0]]])->assertOk()
            ->assertJsonPath('data.caption', 'Open Saturday!')->assertJsonPath('data.chosen', [$photos[2], $photos[0]]);

        expect(ListingPost::query()->sole())->photos->toBe([$photos[2], $photos[0]])->attribution->toBe('Listing courtesy of Lone Star Realty. Source: HAR MLS.');
    });

    it('refuse photos the MLS doesn\'t allow, others\' listings, and sold ones', function () {
        $listing = ($this->listing)('HAR-L1');
        $listing->forceFill(['photos' => array_map(static fn (int $n) => ['url' => "https://photos.example/{$n}.jpg", 'caption' => null], range(1, 30))])->save();

        $this->putJson("/api/v1/listings/{$listing->uuid}/post", ['caption' => 'x', 'photos' => ['https://elsewhere.example/a.jpg']])->assertUnprocessable()->assertJsonValidationErrors('photos');
        // The 11th photo is beyond what a post may carry.
        $this->putJson("/api/v1/listings/{$listing->uuid}/post", ['caption' => 'x', 'photos' => ['https://photos.example/11.jpg']])->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->getJson('/api/v1/listings/'.($this->listing)('HAR-L5')->uuid.'/post')->assertUnprocessable()->assertJsonValidationErrors('listing');
        $this->getJson('/api/v1/listings/'.($this->listing)('HAR-L4')->uuid.'/post')->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', "The MLS doesn't allow posts about this listing right now.");
    });
});

describe('open houses on the agent\'s calendar (B10.2)', function () {
    it('appear once the agent is linked, read-only, and follow the MLS', function () {
        $events = CalendarEvent::query()->where('user_id', $this->james->id)->orderBy('starts_at')->get();

        expect($events)->toHaveCount(2)
            ->and($events[0])->source->toBe('mls-har')->kind->toBe('showing')->title->toBe('Open house: 2418 Willow Bend Dr, Katy, TX 77494')
            ->related_type->toBe('listing')->related_id->toBe(($this->listing)('HAR-L1')->uuid)
            ->and($events[1]->title)->toStartWith('Broker open house:');

        $this->getJson('/api/v1/calendar/events?from='.now()->toDateString().'&to='.now()->addDays(10)->toDateString())->assertOk()
            ->assertJsonPath('data.0.editable', false)->assertJsonPath('data.0.deletable', false);

        // The MLS cancels one; the next sync takes it off.
        MlsOpenHouse::query()->where('open_house_key', 'HAR-H2')->delete();
        app(OpenHouseCalendar::class)->syncMls('mls-har');
        expect(CalendarEvent::query()->where('user_id', $this->james->id)->pluck('external_id')->all())->toBe(['HAR-H1']);

        $this->postJson('/api/v1/listings/members/unlink', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
        expect(CalendarEvent::query()->where('user_id', $this->james->id)->count())->toBe(0);
    });
});

describe('CMAs (B10.2b)', function () {
    it('compare the agent\'s listing with sold and active homes nearby, adjusted to its size', function () {
        $uuid = ($this->listing)('HAR-L1')->uuid;
        $cma = $this->getJson("/api/v1/listings/{$uuid}/cma?radius=1&months=6")->assertOk()->json('data');

        $addresses = array_map(static fn (array $c) => $c['listing']['address'], $cma['comparables']);
        expect($cma['subject']['address'])->toBe('2418 Willow Bend Dr, Katy, TX 77494')
            // Within a mile: the sold neighbours from the last 6 months, the active and pending ones; not Fulshear or Houston.
            ->and($addresses)->toContain('2502 Willow Bend Dr, Katy, TX 77494', '2215 Lakeview Bend Dr, Katy, TX 77494', '2738 Prairie Grove Ln, Katy, TX 77494')
            ->and($addresses)->not->toContain('31 Sable Oak Ct, Fulshear, TX 77441', '5520 Heron Lake Ln, Houston, TX 77041')
            ->and($cma['sold'])->toBeGreaterThanOrEqual(3)
            ->and($cma['comparables'][0]['distance'])->toBeLessThanOrEqual($cma['comparables'][1]['distance'])
            ->and($cma['low'])->toBeLessThanOrEqual($cma['suggested'])->and($cma['suggested'])->toBeLessThanOrEqual($cma['high'])
            ->and($cma['sample'])->toBeTrue();

        $nearest = collect($cma['comparables'])->firstWhere('listing.address', '2502 Willow Bend Dr, Katy, TX 77494');
        // Sold for $463,000 (98% of $472,000) over 2,780 sq ft = $167/sq ft; × 2,850 sq ft ≈ $476,000.
        expect($nearest['listing']['close_price'])->toBe(463000)->and($nearest['listing']['price_per_sqft'])->toBe(167)->and($nearest['adjusted'])->toBe(476000);

        // A shorter date range leaves older sales out.
        $short = $this->getJson("/api/v1/listings/{$uuid}/cma?radius=1&months=3")->assertOk()->json('data');
        expect($short['sold'])->toBeLessThan($cma['sold']);
    });

    it('are for the agent\'s own listings, with a radius and range from the list', function () {
        $this->getJson('/api/v1/listings/'.($this->listing)('HAR-L5')->uuid.'/cma')->assertUnprocessable();
        $this->getJson('/api/v1/listings/'.($this->listing)('HAR-L1')->uuid.'/cma?radius=50')->assertUnprocessable()->assertJsonValidationErrors('radius');

        $this->get('/deals/listings/'.($this->listing)('HAR-L1')->uuid.'/cma?radius=2&months=12')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('listings/cma')->where('cma.radius', 2)->where('cma.months', 12)->has('radii', 4));
    });
});

describe('the agent\'s public page (B10.2b)', function () {
    it('is off until published, then shows the listings the MLS lets the public see', function () {
        $this->getJson('/api/v1/listings/page')->assertOk()->assertJsonPath('data.handle', 'james-miller')->assertJsonPath('data.published', false);
        auth()->forgetGuards();
        $this->get('/agent/james-miller')->assertNotFound();

        $this->actingAs($this->james)->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'Katy homes, twelve years.'])->assertOk()
            ->assertJsonPath('data.url', url('/agent/james-miller'));
        auth()->forgetGuards();

        $this->get('/agent/james-miller')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('public/agent')
            ->where('page.name', 'James Miller')->where('page.brokerage', 'Lone Star Realty')->where('page.bio', 'Katy homes, twelve years.')
            ->where('page.licences.0.number', '0798765')->where('page.sample', true)
            // Coming soon, active and pending; the sold one stays off (Texas: no public sold prices).
            ->has('page.listings', 3)->where('page.listings.1.attribution', 'Listing courtesy of Lone Star Realty. Source: HAR MLS.'));

        // A stale feed shows no listings to the public.
        MlsFeed::query()->where('integration', 'mls-har')->update(['synced_at' => now()->subHours(13)]);
        $this->get('/agent/james-miller')->assertInertia(fn (AssertableInertia $page) => $page->has('page.listings', 0));
    });

    it('gets its own address when the name is taken, and is for agents only', function () {
        $this->getJson('/api/v1/listings/page')->assertJsonPath('data.handle', 'james-miller');
        $other = mlsAgent('James Miller', '0700001');
        $this->actingAs($other)->getJson('/api/v1/listings/page')->assertJsonPath('data.handle', 'james-miller-2');

        $this->actingAs(User::factory()->withRole(Role::Candidate, Organisation::platform())->create())
            ->putJson('/api/v1/listings/page', ['published' => true])->assertNotFound();
    });
});
