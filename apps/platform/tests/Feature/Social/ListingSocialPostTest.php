<?php

declare(strict_types=1);

use App\Models\Connection;
use App\Models\MlsListing;
use App\Models\SocialPost;
use App\Social\SocialPosts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes a listing post with the MLS\'s photos and attribution, never others (B11.1 with B10.2)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $james = mlsAgent('James Miller', '0798765');
    $this->actingAs($james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
    connectOnWeb($this, $james, tool: 'meta')->assertRedirect();
    $meta = Connection::query()->where('integration_id', 'meta')->sole();
    $listing = MlsListing::query()->where('listing_key', 'HAR-L1')->sole();

    $this->get("/studio?listing={$listing->uuid}")->assertInertia(fn (Assert $page) => $page->component('studio/index')
        ->where('listing.listing', $listing->uuid)->where('listing.attribution', 'Listing courtesy of Lone Star Realty. Source: HAR MLS.'));

    $photos = array_column($this->getJson("/api/v1/listings/{$listing->uuid}/post")->json('data.photos'), 'url');
    $base = ['body' => 'Just listed!', 'link' => null, 'targets' => [['connection' => $meta->id, 'channel' => 'instagram', 'id' => 'sim-ig-1']], 'when' => 'now', 'listing' => $listing->uuid];

    $this->postJson('/api/v1/social/posts', [...$base, 'media' => ['https://elsewhere.example/a.jpg']])->assertUnprocessable()->assertJsonValidationErrors('media');
    $this->postJson('/api/v1/social/posts', [...$base, 'media' => [$photos[0]]])->assertCreated()->assertJsonPath('data.status', 'published');

    expect(SocialPost::query()->sole()->attribution)->toEndWith("\nListing courtesy of Lone Star Realty. Source: HAR MLS.")
        ->and(SocialPosts::content(SocialPost::query()->sole())['media'][0]['url'])->toBe($photos[0]);
});
