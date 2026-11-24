<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\MlsListing;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\PostApprovalDecided;
use App\Notifications\PostApprovalRequested;
use App\Services\Ai\AiCredits;
use App\Studio\AdvertisingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'America/Chicago'));
    Storage::fake('content');
    Notification::fake();
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $this->james = mlsAgent('James Miller', '0798765');
    $this->actingAs($this->james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
    connectOnWeb($this, $this->james, tool: 'meta')->assertRedirect();
    $this->facebook = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
    $this->listing = MlsListing::query()->where('listing_key', 'HAR-L1')->sole();
    $this->brokerage = app(AdvertisingPolicy::class)->brokerage($this->james);
});

function make(object $test, array $source, array $outputs = ['post']): array
{
    return $test->postJson('/api/v1/studio/generations', ['source' => $source, 'outputs' => $outputs])->assertCreated()->json('data');
}

describe('the content studio (B12.1)', function () {
    it('makes a listing post and publishes it in under a minute (the done-when)', function () {
        $started = microtime(true);
        $left = app(AiCredits::class)->left($this->james);
        $home = $this->getJson('/api/v1/studio')->assertOk()->json('data');
        expect($home['cost'])->toBe(5)->and(array_column($home['listings'], 'id'))->toContain($this->listing->uuid);

        $made = make($this, ['type' => 'listing', 'id' => $this->listing->uuid], ['post', 'carousel', 'ad']);
        expect($made['captions'])->toHaveCount(3)
            ->and(array_column($made['captions'], 'tone'))->toBe(['professional', 'warm', 'short'])
            ->and($made['captions'][0]['text'])->toStartWith('Just listed: 2418 Willow Bend Dr')->toContain('#JustListed')
            ->and(array_column($made['designs'], 'layout'))->toBe(['band', 'top', 'split'])
            ->and(count($made['carousel']))->toBeGreaterThan(1)
            ->and($made['ad']['headlines'])->toHaveCount(3)
            ->and($made['listing'])->toBe($this->listing->uuid)
            ->and(app(AiCredits::class)->left($this->james))->toBe($left - 5);

        $png = $this->get($made['designs'][0]['url'])->assertOk();
        expect(substr((string) $png->streamedContent(), 1, 3))->toBe('PNG');

        $post = $this->postJson('/api/v1/social/posts', ['body' => $made['captions'][0]['text'], 'media' => [$made['designs'][0]['path']], 'listing' => $this->listing->uuid, 'targets' => [$this->facebook], 'when' => 'now'])
            ->assertCreated()->json('data');
        expect($post['status'])->toBe('published')
            ->and($post['attribution'])->toContain('James Miller')->toContain('HAR MLS')
            ->and(microtime(true) - $started)->toBeLessThan(60);
    });

    it('changes a caption\'s tone for free, and makes posts from a market update, own words or a sale', function () {
        $made = make($this, ['type' => 'listing', 'id' => $this->listing->uuid]);
        $left = app(AiCredits::class)->left($this->james);
        $short = $this->postJson("/api/v1/studio/generations/{$made['id']}/captions", ['index' => 0, 'tone' => 'shorter'])->assertOk()->json('data.captions.0.text');
        $spanish = $this->postJson("/api/v1/studio/generations/{$made['id']}/captions", ['index' => 1, 'tone' => 'spanish'])->assertOk()->json('data.captions.1.text');
        expect(mb_strlen($short))->toBeLessThan(mb_strlen($made['captions'][0]['text']))
            ->and($spanish)->toContain('2418 Willow Bend Dr')->toContain('#RecienListada')
            ->and(app(AiCredits::class)->left($this->james))->toBe($left);

        expect(make($this, ['type' => 'market', 'text' => 'Median price up 3% this month.', 'city' => 'Katy'])['captions'][0]['text'])->toBe('Katy market update: Median price up 3% this month. Want to know what it means for your home? Message me. #MarketUpdate #KatyRealEstate')
            ->and(make($this, ['type' => 'text', 'text' => 'Open house this Saturday, 1 to 3 pm!'])['designs'])->toHaveCount(3);

        $this->postJson('/api/v1/studio/generations', ['source' => ['type' => 'sale', 'id' => (string) Str::uuid7()], 'outputs' => ['post']])->assertUnprocessable()->assertJsonValidationErrors('source');
        $sold = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'reos', 'property' => '88 Oak Ridge Dr, Katy', 'side' => 'sell', 'stage' => 'closed', 'parties' => [['name' => 'Ana Lopez', 'role' => 'seller', 'email' => null, 'phone' => null]], 'documents' => []]);
        $sale = make($this, ['type' => 'sale', 'id' => $sold->uuid]);
        expect($sale['captions'][0]['text'])->toStartWith('Just sold: 88 Oak Ridge Dr, Katy')->not->toContain('Ana');
    });

    it('checks fair housing wording and claims, and fixes them automatically', function () {
        $check = fn (string $body) => $this->postJson('/api/v1/social/posts/check', ['body' => $body, 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->assertOk()->json('data');
        $failing = $check('Perfect for families, in an exclusive neighborhood. Guaranteed to impress!');

        expect(collect($failing['checks'])->pluck('ok', 'code')->only(['fair_housing', 'claims', 'banned'])->all())->toBe(['fair_housing' => false, 'claims' => false, 'banned' => true])
            ->and(array_column($failing['issues'], 'phrase'))->toBe(['perfect for families', 'exclusive neighborhood', 'guaranteed'])
            ->and($failing['approval'])->toBeFalse();
        $this->postJson('/api/v1/social/posts', ['body' => 'Perfect for families!', 'media' => [], 'targets' => [$this->facebook], 'when' => 'now'])->assertUnprocessable()->assertJsonValidationErrors('checks');

        $fixed = $this->postJson('/api/v1/social/posts/fix', ['body' => 'Perfect for families, in an exclusive neighborhood. Guaranteed to impress!'])->assertOk()->json('data');
        expect($fixed['body'])->toBe('roomy layout, in an established neighborhood. to impress!')
            ->and($check($fixed['body'])['ok'])->toBeTrue();
    });

    it('applies the brokerage\'s banned phrases and approval: the broker approves or sends back', function () {
        $linda = User::factory()->withRole(Role::Broker, $this->brokerage)->withTwoFactor()->create(['name' => 'Linda Park']);
        $this->actingAs($linda)->put('/organisation/advertising', ['approval_new_agents' => false, 'approval_all' => true, 'banned' => ['motivated seller', '']])->assertSessionHasNoErrors();

        $this->actingAs($this->james);
        $check = $this->postJson('/api/v1/social/posts/check', ['body' => 'Motivated seller! Make an offer.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->json('data');
        expect(collect($check['checks'])->firstWhere('code', 'banned'))->toMatchArray(['ok' => false, 'detail' => 'motivated seller'])->and($check['approval'])->toBeTrue();

        $first = $this->postJson('/api/v1/social/posts', ['body' => 'Open house Saturday, 1 to 3 pm.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'now'])->assertCreated()->json('data');
        expect($first['status'])->toBe('approval');
        Notification::assertSentTo($linda, PostApprovalRequested::class);
        $second = $this->postJson('/api/v1/social/posts', ['body' => 'Price improved on Willow Bend.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => '2026-11-26T15:00:00Z'])->json('data');
        expect($this->getJson('/api/v1/social/posts')->json('data.scheduled'))->toHaveCount(2);

        $this->actingAs($linda)->get('/organisation/advertising')->assertInertia(fn (Assert $page) => $page->component('organisation/advertising')
            ->has('pending', 2)->where('rules.banned', ['motivated seller'])->where('rules.approval_all', true)->has('texas', 4));
        $this->post("/organisation/advertising/posts/{$first['id']}/approve")->assertSessionHasNoErrors();
        $this->post("/organisation/advertising/posts/{$second['id']}/return", ['note' => 'Say which home and its price.'])->assertSessionHasNoErrors();
        $this->post("/organisation/advertising/posts/{$first['id']}/approve")->assertSessionHasErrors('post');

        expect(SocialPost::query()->where('uuid', $first['id'])->value('status'))->toBe('published')
            ->and(SocialPost::query()->where('uuid', $second['id'])->first())->status->toBe('draft')->approval_note->toBe('Say which home and its price.');
        Notification::assertSentTo($this->james, PostApprovalDecided::class, fn (PostApprovalDecided $n): bool => ! $n->approved && $n->inbox($this->james)['body'] === 'Say which home and its price.');
    });

    it('checks scheduled posts again as they go out', function () {
        $post = $this->postJson('/api/v1/social/posts', ['body' => 'Price improved, motivated seller!', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => '2026-11-25T15:00:00Z'])->json('data');
        app(AdvertisingPolicy::class)->save($this->brokerage, false, false, ['motivated seller'], $this->james);

        $this->travelTo('2026-11-25 15:01:00');
        $this->artisan('reos:social:publish')->expectsOutput('Publishing 0 posts.');
        expect(SocialPost::query()->where('uuid', $post['id'])->first())->status->toBe('draft')
            ->approval_note->toBe("It didn't go out: it now has motivated seller. Fix it and schedule it again.");
    });

    it('keeps makings to their agent, approvals to their brokerage, and needs the credits', function () {
        $made = make($this, ['type' => 'text', 'text' => 'Happy Thanksgiving from our team!']);
        $other = mlsAgent('Dana Brooks', '0700001');
        $this->actingAs($other)->getJson("/api/v1/studio/generations/{$made['id']}")->assertNotFound();

        $post = $this->actingAs($this->james)->postJson('/api/v1/social/posts', ['body' => 'Hello Katy', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->json('data');
        SocialPost::query()->where('uuid', $post['id'])->update(['status' => 'approval']);
        $stranger = User::factory()->withRole(Role::Broker)->withTwoFactor()->create();
        $this->actingAs($stranger)->post("/organisation/advertising/posts/{$post['id']}/approve")->assertNotFound();

        config(['reos.ai.monthly_credits' => 2]);
        $poor = mlsAgent('Pat Poor', '0700002');
        $this->actingAs($poor)->postJson('/api/v1/studio/generations', ['source' => ['type' => 'text', 'text' => 'Hello'], 'outputs' => ['post']])->assertUnprocessable()->assertJsonValidationErrors('outputs');
    });

    it('shows the maker in the content studio on the web', function () {
        $this->get('/studio')->assertInertia(fn (Assert $page) => $page->component('studio/index')->where('maker.cost', 5)->has('maker.listings'));
        $this->postJson('/studio/generations', ['source' => ['type' => 'text', 'text' => 'New listing coming soon in Katy.'], 'outputs' => ['post']])->assertCreated();
    });
});
