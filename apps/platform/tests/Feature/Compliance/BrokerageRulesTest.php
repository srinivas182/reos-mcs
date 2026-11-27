<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\ComplianceEvent;
use App\Models\Connection;
use App\Models\User;
use App\Studio\AdvertisingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 09:00', 'America/Chicago'));
    Storage::fake('content');
    Notification::fake();
    $this->artisan('reos:mls:sync --now')->assertSuccessful();
    $this->james = mlsAgent('James Miller', '0798765');
    $this->actingAs($this->james)->postJson('/api/v1/listings/members', ['mls' => 'mls-har', 'member_key' => 'HAR-M1'])->assertOk();
    connectOnWeb($this, $this->james, tool: 'meta')->assertRedirect();
    $this->facebook = ['connection' => Connection::query()->where('integration_id', 'meta')->sole()->id, 'channel' => 'facebook', 'id' => 'sim-page-1'];
    $this->brokerage = app(AdvertisingPolicy::class)->brokerage($this->james);
    $this->linda = User::factory()->withRole(Role::Broker, $this->brokerage)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->actingAs($this->linda)->put('/organisation/advertising', ['approval_new_agents' => false, 'approval_all' => false, 'banned' => ['motivated seller']])->assertSessionHasNoErrors();
});

describe('brokerage rules on every channel (B13.2)', function () {
    it('fixes a violation or sends it to the broker with the reason (the done-when)', function () {
        $this->actingAs($this->james);
        $post = fn (array $extra = []) => $this->postJson('/api/v1/social/posts', ['body' => 'Motivated seller on Willow Bend! Make an offer.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'now', ...$extra]);

        $check = $this->postJson('/api/v1/social/posts/check', ['body' => 'Motivated seller on Willow Bend! Make an offer.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'draft'])->json('data');
        expect($check['ok'])->toBeFalse()->and($check['routable'])->toBeTrue();
        $post()->assertUnprocessable();

        // Fixed: the phrase comes out and the post goes.
        $fixed = $this->postJson('/api/v1/social/posts/fix', ['body' => 'Motivated seller on Willow Bend! Make an offer.'])->assertOk()->json('data.body');
        expect($fixed)->toBe('on Willow Bend! Make an offer.');

        // Or sent to the broker with the reason.
        $sent = $post(['ask_broker' => 'The seller asked us to say this; it is the listing remark.'])->assertCreated()->json('data');
        expect($sent['status'])->toBe('approval');
        $this->actingAs($this->linda)->get('/organisation/advertising')->assertInertia(fn (Assert $page) => $page
            ->where('pending.0.reason', 'The seller asked us to say this; it is the listing remark.')->where('pending.0.issues.0.phrase', 'motivated seller'));
        $this->post("/organisation/advertising/posts/{$sent['id']}/approve")->assertSessionHasNoErrors();

        expect(ComplianceEvent::query()->where('user_id', $this->james->id)->orderBy('id')->pluck('action')->all())->toBe(['blocked', 'fixed', 'sent_to_broker', 'approved'])
            ->and(ComplianceEvent::query()->where('action', 'approved')->sole()->actor_id)->toBe($this->linda->id);
    });

    it('only sends wording to the broker; the broker\'s name and the post itself must be right', function () {
        $this->actingAs($this->james)->postJson('/api/v1/social/posts', ['body' => 'The Miller Realty Team says: motivated seller!', 'media' => [], 'targets' => [$this->facebook], 'when' => 'now', 'ask_broker' => 'Please allow it.'])->assertUnprocessable();
        // An independent agent has no broker to send to.
        $solo = User::factory()->create(['name' => 'Sam Lee']);
        expect($this->actingAs($solo)->postJson('/api/v1/social/posts/check', ['body' => 'Guaranteed sale!', 'media' => [], 'targets' => [], 'when' => 'draft'])->json('data.routable'))->toBeFalse();
    });

    it('makes the public page wait for the broker when the brokerage approves ads first', function () {
        $this->actingAs($this->linda)->put('/organisation/advertising', ['approval_new_agents' => false, 'approval_all' => true, 'banned' => []])->assertSessionHasNoErrors();
        $this->actingAs($this->james)->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'Katy homes, twelve years.'])->assertOk()
            ->assertJsonPath('data.published', false)->assertJsonPath('data.waiting', true);
        auth()->forgetGuards();
        $this->get('/agent/james-miller')->assertNotFound();

        $this->actingAs($this->linda)->get('/organisation/advertising')->assertInertia(fn (Assert $page) => $page->where('pages.0.id', 'james-miller'));
        $this->post('/organisation/advertising/pages/james-miller/approve')->assertSessionHasNoErrors();
        auth()->forgetGuards();
        $this->get('/agent/james-miller')->assertOk();
        // An unchanged live page stays live.
        $this->actingAs($this->james)->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'Katy homes, twelve years.'])->assertJsonPath('data.published', true);
        expect(ComplianceEvent::query()->where('channel', 'page')->pluck('action')->all())->toContain('sent_to_broker', 'approved');
    });

    it('logs every channel for the broker, filtered and exported to CSV', function () {
        $this->actingAs($this->james)->postJson('/api/v1/compliance/fix', ['channel' => 'text', 'text' => 'Hi Ana, motivated seller here, great for kids.'])->assertOk();
        $this->postJson('/api/v1/social/posts', ['body' => 'Open house Saturday.', 'media' => [], 'targets' => [$this->facebook], 'when' => 'now'])->assertCreated();

        $this->actingAs($this->linda)->get('/organisation/compliance-log')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/compliance-log')->has('events', 2));
        $this->get('/organisation/compliance-log?channel=text')->assertInertia(fn (Assert $page) => $page->has('events', 1)
            ->where('events.0.action', 'fixed')->where('events.0.agent', 'James Miller')->where('filters.channel', 'text'));

        $csv = $this->get('/organisation/compliance-log/export?channel=text')->assertOk()->streamedContent();
        expect($csv)->toContain('when,agent,by,channel,action,item,rules,phrases,note')->toContain('James Miller')->toContain('great for kids; motivated seller');
        $this->get('/organisation/compliance-log?channel=fax')->assertSessionHasErrors('channel');

        // Agents can't read the log.
        $this->actingAs($this->james)->get('/organisation/compliance-log')->assertForbidden();
    });
});
