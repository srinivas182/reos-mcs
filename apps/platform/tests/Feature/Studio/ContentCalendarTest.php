<?php

declare(strict_types=1);

use App\Models\Celebration;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\MlsListing;
use App\Models\SocialPost;
use App\Notifications\CelebrationReady;
use App\Services\Ai\AiCredits;
use App\Studio\Celebrations\Celebrations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday, November 24, 2026, 9 am in Texas.
    $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'America/Chicago'));
    Storage::fake('content');
    Notification::fake();
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $this->james = mlsAgent('James Miller', '0798765');
    $this->actingAs($this->james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
    connectOnWeb($this, $this->james, tool: 'meta')->assertRedirect();
    $this->facebook = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
});

function celebratedClosing(object $test, string $property, int $daysAgo): Deal
{
    return Deal::query()->create([
        'uuid' => (string) Str::uuid7(), 'user_id' => $test->james->id, 'source' => 'reos', 'property' => $property, 'side' => 'sell', 'stage' => 'closed', 'closed_at' => now()->subDays($daysAgo),
        'parties' => [['name' => 'Ana Lopez', 'role' => 'seller', 'email' => null, 'phone' => null]], 'documents' => [],
    ]);
}

describe('the content calendar (B12.3)', function () {
    it('schedules a week of posts and shows them in one view (the done-when)', function () {
        // Sunday, November 29 to Saturday, December 5, at 10 am Texas time.
        foreach (range(0, 6) as $i) {
            $at = CarbonImmutable::parse('2026-11-29 10:00', 'America/Chicago')->addDays($i);
            $this->postJson('/api/v1/social/posts', ['body' => "Tip {$i}: get pre-approved before you shop. #HomeBuying", 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => $at->toIso8601String()])->assertCreated();
        }

        $week = $this->getJson('/api/v1/social/calendar?view=week&date=2026-12-01')->assertOk()->json('data');
        expect($week['from'])->toBe('2026-11-29')->and($week['to'])->toBe('2026-12-05')
            ->and($week['scheduled'])->toBe(7)
            ->and(array_map(static fn (array $d): int => count($d['posts']), $week['days']))->toBe([1, 1, 1, 1, 1, 1, 1])
            ->and($week['days'][0]['posts'][0])->toMatchArray(['status' => 'scheduled', 'time' => '10:00', 'channels' => ['facebook'], 'movable' => true])
            ->and($week['previous'])->toBe('2026-11-24')->and($week['next'])->toBe('2026-12-08');

        $this->actingAs($this->james)->get('/studio/calendar?date=2026-12-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('studio/calendar')->where('calendar.scheduled', 7)->has('calendar.days', 7)->has('celebrations.waiting'));

        $month = $this->getJson('/api/v1/social/calendar?view=month&date=2026-12-01')->assertOk()->json('data');
        expect($month['days'])->toHaveCount(31)->and($month['scheduled'])->toBe(5);
    });

    it('moves a scheduled post to another day at the same time, and plans and reuses drafts', function () {
        $post = $this->postJson('/api/v1/social/posts', ['body' => 'Open house Saturday, 1 to 3 pm.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => CarbonImmutable::parse('2026-11-30 18:30', 'America/Chicago')->toIso8601String()])->assertCreated()->json('data');

        $moved = $this->postJson("/api/v1/social/posts/{$post['id']}/move", ['date' => '2026-12-02'])->assertOk()->json('data');
        expect($moved['time'])->toBe('18:30')
            ->and(SocialPost::query()->where('uuid', $post['id'])->sole()->scheduled_at?->setTimezone('America/Chicago')->format('Y-m-d H:i'))->toBe('2026-12-02 18:30');
        $this->postJson("/api/v1/social/posts/{$post['id']}/move", ['date' => '2026-11-20'])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson("/api/v1/social/posts/{$post['id']}/move", ['date' => '2026-02-30'])->assertUnprocessable();

        // A post that went out can't move; it can be reused as a draft planned for a day.
        $sent = $this->postJson('/api/v1/social/posts', ['body' => 'Market update: homes are selling in 21 days.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'now'])->assertCreated()->json('data');
        $this->postJson("/api/v1/social/posts/{$sent['id']}/move", ['date' => '2026-12-01'])->assertUnprocessable()->assertJsonValidationErrors('post');
        $reused = $this->postJson("/api/v1/social/posts/{$sent['id']}/reuse", ['date' => '2026-12-03'])->assertCreated()->json('data');
        expect($reused)->toMatchArray(['status' => 'draft', 'time' => null, 'movable' => true, 'channels' => ['facebook']]);

        $this->postJson("/api/v1/social/posts/{$reused['id']}/move", ['date' => '2026-12-04'])->assertOk();
        $week = $this->getJson('/api/v1/social/calendar?date=2026-12-01')->json('data');
        $days = array_column($week['days'], 'posts', 'date');
        expect(array_column($days['2026-12-02'], 'id'))->toBe([$post['id']])
            ->and(array_column($days['2026-12-04'], 'id'))->toBe([$reused['id']]);

        // Scheduling the planned draft takes it off the plan.
        $this->putJson("/api/v1/social/posts/{$reused['id']}", ['body' => 'Market update: homes are selling in 21 days.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => CarbonImmutable::parse('2026-12-04 09:00', 'America/Chicago')->toIso8601String()])->assertOk();
        expect(SocialPost::query()->where('uuid', $reused['id'])->sole()->planned_on)->toBeNull();

        // Someone else's post is not found.
        $other = mlsAgent('Maria Garcia', '0712345');
        $this->actingAs($other)->postJson("/api/v1/social/posts/{$post['id']}/move", ['date' => '2026-12-03'])->assertNotFound();
    });
});

describe('celebration moments (B12.3, screen 163)', function () {
    it('makes milestones into free posts, shows one a day, and never names clients', function () {
        $left = app(AiCredits::class)->left($this->james);
        celebratedClosing($this, '88 Oak Ridge Dr, Katy', 2);
        MlsListing::query()->where('listing_key', 'HAR-L1')->update(['status' => 'active', 'listed_on' => now()->subDays(3)->toDateString()]);
        Licence::query()->where('user_id', $this->james->id)->update(['licensed_on' => '2023-11-20']);

        $made = app(Celebrations::class)->scan($this->james);
        expect(array_map(static fn (Celebration $c): string => $c->milestone, $made))->toEqualCanonicalizing(['just_listed', 'first_closing', 'anniversary']);

        $first = collect($made)->firstWhere('milestone', 'first_closing');
        expect($first->label)->toBe('88 Oak Ridge Dr, Katy')
            ->and($first->generation->credits)->toBe(0)
            ->and($first->generation->designs)->toHaveCount(3)
            ->and($first->generation->captions[0]['text'])->toBe('My first closing: 88 Oak Ridge Dr, Katy. Thank you to my clients, my broker and everyone who helped along the way. #FirstClosing #TexasRealEstate')
            ->and(implode(' ', array_column($first->generation->captions, 'text')))->not->toContain('Ana')
            ->and($first->post->status)->toBe('draft')
            ->and($first->post->attribution)->toContain('James Miller')
            ->and(collect($made)->firstWhere('milestone', 'anniversary')->label)->toBe('3 years')
            ->and(app(AiCredits::class)->left($this->james))->toBe($left);

        // Made once.
        expect(app(Celebrations::class)->scan($this->james))->toBe([]);

        // At most one shown a day; the rest wait in My posts.
        $this->artisan('reos:celebrations')->assertSuccessful();
        $this->artisan('reos:celebrations')->assertSuccessful();
        Notification::assertSentToTimes($this->james, CelebrationReady::class, 1);
        $list = $this->getJson('/api/v1/studio/celebrations')->assertOk()->json('data');
        expect($list['today']['milestone'])->toBe($made[0]->milestone)
            ->and($list['waiting'])->toHaveCount(2)
            ->and($list['today']['generation']['captions'])->toHaveCount(3)
            ->and($list['today']['post'])->not->toBeNull();
        $this->getJson("/api/v1/studio/celebrations/{$list['today']['id']}")->assertOk()->assertJsonPath('data.id', $list['today']['id']);

        $this->travel(1)->day();
        $this->artisan('reos:celebrations')->assertSuccessful();
        Notification::assertSentToTimes($this->james, CelebrationReady::class, 2);

        // A second closing is "just sold"; tones are free on celebrations too.
        celebratedClosing($this, '15 Cypress Hollow Ln, Cypress', 1);
        $sold = app(Celebrations::class)->scan($this->james);
        expect($sold[0]->milestone)->toBe('just_sold');
        $spanish = $this->postJson("/api/v1/studio/generations/{$sold[0]->generation->uuid}/captions", ['index' => 0, 'tone' => 'spanish'])->assertOk()->json('data.captions.0.text');
        expect($spanish)->toStartWith('Recién vendida: 15 Cypress Hollow Ln, Cypress')->and(app(AiCredits::class)->left($this->james))->toBe($left);

        $other = mlsAgent('Maria Garcia', '0712345');
        $this->actingAs($other)->getJson("/api/v1/studio/celebrations/{$list['today']['id']}")->assertNotFound();
    });

    it('keeps a profile photo with a quality check, for the designs (screen 162)', function () {
        $kit = $this->getJson('/api/v1/me/brand')->assertOk()->json('data');
        expect($kit)->toMatchArray(['photo' => null, 'name' => 'James Miller', 'licence' => '0798765'])->and($kit['colour'])->toStartWith('#');

        $small = $this->post('/api/v1/me/brand/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)], ['Accept' => 'application/json'])->assertOk()->json('data');
        expect($small['photo']['path'])->toStartWith("social/{$this->james->id}/studio/profile-")
            ->and($small['check']['issues'])->toBe(['small', 'dark']);
        $this->get($small['photo']['url'])->assertOk();

        $big = $this->post('/api/v1/me/brand/photo', ['photo' => UploadedFile::fake()->image('me.png', 900, 1200)], ['Accept' => 'application/json'])->assertOk()->json('data');
        expect(Storage::disk('content')->exists($small['photo']['path']))->toBeFalse()
            ->and(getimagesizefromstring((string) Storage::disk('content')->get($big['photo']['path']))[0])->toBe(800);

        celebratedClosing($this, '88 Oak Ridge Dr, Katy', 2);
        expect(array_map(static fn (Celebration $c): string => $c->milestone, app(Celebrations::class)->scan($this->james)))->toContain('first_closing');

        $this->post('/api/v1/me/brand/photo', ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        expect($this->deleteJson('/api/v1/me/brand/photo')->assertOk()->json('data.photo'))->toBeNull();
    });
});
