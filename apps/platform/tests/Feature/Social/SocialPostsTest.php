<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Connections\ConnectionStatus;
use App\Connections\Exceptions\VendorUnavailable;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Jobs\PublishSocialTarget;
use App\Models\Connection;
use App\Models\Licence;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Social\SocialPosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 15:00:00');
    Storage::fake('content');
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create([
        'user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active,
        'holder_name' => 'James Miller', 'sponsor_name' => 'Linda Park Realty', 'verified_by' => 'trec-licence', 'verified_at' => now(),
    ]);
    connectOnWeb($this, $this->james, tool: 'meta')->assertRedirect();
    $this->meta = Connection::query()->where('integration_id', 'meta')->sole();
    $this->facebook = ['connection' => $this->meta->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
    $this->instagram = ['connection' => $this->meta->id, 'channel' => 'instagram', 'id' => 'sim-ig-1'];
});

function photoFor(object $test): string
{
    return $test->postJson('/api/v1/social/media', ['file' => UploadedFile::fake()->image('kitchen.jpg', 1200, 900)])->assertCreated()->json('data.path');
}

describe('the posting engine with Instagram and Facebook (B11.1)', function () {
    it('lists social accounts with the Page and Instagram account they can post to', function () {
        $accounts = collect($this->actingAs($this->james)->getJson('/api/v1/social/accounts')->assertOk()->json('data'))->keyBy('integration');

        expect($accounts['meta'])->toMatchArray(['built' => true, 'available' => true])
            ->and($accounts['meta']['targets'])->toBe([
                ['channel' => 'facebook', 'id' => 'sim-page-1', 'name' => 'James Miller, REALTOR® (demo Page)'],
                ['channel' => 'instagram', 'id' => 'sim-ig-1', 'name' => '@jamesmiller.homes (demo)'],
            ])
            ->and($accounts['meta']['channels']['instagram'])->toBe(['media' => 'required', 'max_text' => 2200])
            ->and($accounts['linkedin'])->toMatchArray(['built' => true, 'connection' => null])
            ->and($accounts['youtube'])->toMatchArray(['built' => false, 'available' => false]);
    });

    it('checks every post: broker name, accounts, Instagram needs media, no misleading title', function () {
        $check = fn (array $body) => $this->actingAs($this->james)->postJson('/api/v1/social/posts/check', [...['media' => [], 'link' => null, 'when' => 'draft'], ...$body])->assertOk()->json('data');

        $failing = $check(['body' => 'As your broker I will sell your home', 'targets' => [$this->instagram]]);
        $brokerage = app(ActiveRole::class)->for($this->james)?->organisation->name;
        expect($failing['attribution'])->toBe('James Miller · '.$brokerage)
            ->and(collect($failing['checks'])->mapWithKeys(fn ($c) => [$c['code'].($c['detail'] ? ':'.$c['detail'] : '') => $c['ok']])->all())
            ->toBe(['broker' => true, 'targets' => true, 'length:instagram' => true, 'media:instagram' => false, 'title' => false])
            ->and($failing['ok'])->toBeFalse();

        $this->postJson('/api/v1/social/posts', ['body' => 'As your broker I will sell your home', 'media' => [], 'targets' => [$this->instagram], 'when' => 'now'])
            ->assertUnprocessable()->assertJsonValidationErrors('checks');

        expect($check(['body' => 'Just listed in Austin', 'media' => [photoFor($this)], 'targets' => [$this->facebook, $this->instagram]])['ok'])->toBeTrue();
    });

    it('publishes to both now, with the attribution, then reads insights back', function () {
        $path = photoFor($this);
        $post = $this->postJson('/api/v1/social/posts', ['body' => 'Just listed: 1420 Pecan St, Austin', 'media' => [$path], 'link' => 'https://reos.com/agent/james-miller', 'targets' => [$this->facebook, $this->instagram], 'when' => 'now'])
            ->assertCreated()->json('data');

        expect($post['status'])->toBe('published')
            ->and(array_column($post['targets'], 'status'))->toBe(['published', 'published']);
        $row = $this->getJson("/api/v1/social/posts/{$post['id']}")->json('data');
        expect($row['status'])->toBe('published')->and(array_column($row['targets'], 'status'))->toBe(['published', 'published'])
            ->and($row['targets'][0]['insights'])->toBe([]);

        $this->travel(3)->hours();
        $this->artisan('reos:social:insights')->expectsOutput('Read insights for 2 posts.')->assertSuccessful();
        $row = $this->getJson("/api/v1/social/posts/{$post['id']}")->json('data');
        expect(array_keys($row['targets'][0]['insights']))->toBe(['reach', 'likes', 'comments', 'clicks'])
            ->and(array_keys($row['targets'][1]['insights']))->toBe(['reach', 'likes', 'comments', 'views'])
            ->and($row['totals']['reach'])->toBeGreaterThan(0);

        $list = $this->getJson('/api/v1/social/posts')->json('data');
        expect($list['stats']['posts_this_month'])->toBe(1)->and($list['posted'])->toHaveCount(1);

        // What each channel received: the text with the attribution, and a signed media link it can fetch.
        $content = SocialPosts::content(SocialPost::query()->sole());
        expect($content['text'])->toStartWith("Just listed: 1420 Pecan St, Austin\n\nJames Miller · ");
        $this->get($content['media'][0]['url'])->assertOk();
        $this->get('/social/media/'.$path)->assertNotFound();
    });

    it('schedules a post, edits it, removes it with Undo and publishes it when due', function () {
        Queue::fake();
        $post = $this->actingAs($this->james)->postJson('/api/v1/social/posts', ['body' => 'Open house Saturday', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => '2026-11-24T15:02:00Z'])
            ->assertUnprocessable()->assertJsonValidationErrors('at');
        $id = $this->postJson('/api/v1/social/posts', ['body' => 'Open house Saturday', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => '2026-11-25T14:00:00Z'])
            ->assertCreated()->assertJsonPath('data.status', 'scheduled')->json('data.id');
        $this->putJson("/api/v1/social/posts/{$id}", ['body' => 'Open house Saturday, 1–3 pm', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => '2026-11-25T15:00:00Z'])
            ->assertOk()->assertJsonPath('data.body', 'Open house Saturday, 1–3 pm');

        $this->deleteJson("/api/v1/social/posts/{$id}")->assertNoContent();
        expect($this->getJson('/api/v1/social/posts')->json('data.scheduled'))->toBe([]);
        $this->postJson("/api/v1/social/posts/{$id}/restore")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->putJson("/api/v1/social/posts/{$id}", ['body' => 'Open house Saturday, 1–3 pm', 'media' => [], 'targets' => [$this->facebook], 'when' => 'later', 'at' => '2026-11-25T15:00:00Z'])->assertOk();

        $this->artisan('reos:social:publish')->expectsOutput('Publishing 0 posts.');
        $this->travelTo('2026-11-25 15:00:30');
        $this->artisan('reos:social:publish')->expectsOutput('Publishing 1 posts.');
        Queue::assertPushed(PublishSocialTarget::class, 1);
        $this->putJson("/api/v1/social/posts/{$id}", ['body' => 'Too late', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->assertUnprocessable();
    });

    it('retries a failing place, then marks it failed; a lapsed connection asks to reconnect', function () {
        Queue::fake();
        $id = $this->actingAs($this->james)->postJson('/api/v1/social/posts', ['body' => 'Market update #fail', 'media' => [photoFor($this)], 'targets' => [$this->facebook, $this->instagram], 'when' => 'now'])->json('data.id');
        $targets = SocialPostTarget::query()->orderBy('id')->get();

        $job = new PublishSocialTarget($targets[0]->id);
        expect(fn () => app()->call([$job, 'handle']))->toThrow(VendorUnavailable::class);
        expect($targets[0]->refresh())->status->toBe('queued')->attempts->toBe(1);
        $job->failed(new RuntimeException('Simulator: the channel is having trouble.'));
        expect($targets[0]->refresh()->status)->toBe('failed');

        $this->meta->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();
        app()->call([new PublishSocialTarget($targets[1]->id), 'handle']);
        expect($targets[1]->refresh())->status->toBe('failed')->last_error->toBe('reconnect')
            ->and(SocialPost::query()->sole()->status)->toBe('failed');

        $copy = $this->postJson("/api/v1/social/posts/{$id}/repost")->assertCreated()->json('data');
        expect($copy)->toMatchArray(['status' => 'draft', 'body' => 'Market update #fail'])->and($copy['targets'])->toHaveCount(2);
    });

    it('keeps posts to their owner and accounts to the agent\'s own connections', function () {
        $id = $this->actingAs($this->james)->postJson('/api/v1/social/posts', ['body' => 'Hello Austin', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->json('data.id');
        $dana = User::factory()->withRole(Role::Agent)->create();

        $this->actingAs($dana)->getJson("/api/v1/social/posts/{$id}")->assertNotFound();
        $this->postJson('/api/v1/social/posts', ['body' => 'Hijack', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->assertUnprocessable()->assertJsonValidationErrors('targets');
        $this->postJson('/api/v1/social/posts', ['body' => 'Hijack', 'media' => ['social/'.$this->james->id.'/x.jpg'], 'targets' => [], 'when' => 'draft'])->assertUnprocessable()->assertJsonValidationErrors('media');
    });

    it('shows the content studio on the web', function () {
        $this->actingAs($this->james)->get('/studio')->assertInertia(fn (Assert $page) => $page->component('studio/index')
            ->has('accounts')->where('posts.stats.posts_this_month', 0)->where('listing', null));
        $this->postJson('/studio/posts', ['body' => 'From the web', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->assertCreated();
    });
});
