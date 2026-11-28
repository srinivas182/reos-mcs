<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Brokerage\WorkingHours;
use App\Enums\OrganisationType;
use App\Models\AgentPage;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\PolicyPassage;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\ApprovalOverdue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * One approvals queue with office admin fallback (A15.3, ADR 0072).
 */

beforeEach(function () {
    // Monday 30 November 2026, 9 AM in Texas.
    $this->travelTo(CarbonImmutable::parse('2026-11-30 09:00:00', WorkingHours::TIMEZONE));
    Notification::fake();
    Storage::fake(config()->string('reos.content.disk'));
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->okoye = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    // Linda set the brokerage up: its designated broker.
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->ana = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Ana Office']);
    $this->ade = User::factory()->withRole(Role::Broker, $this->okoye)->withTwoFactor()->create(['name' => 'Ade Okoye']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->okoye)->create(['name' => 'Priya Shah']);
});

function waitingPost(User $agent, string $body, CarbonImmutable $at): SocialPost
{
    return SocialPost::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $agent->id, 'body' => $body, 'media' => [], 'attribution' => $agent->name.' · Lone Star Realty', 'status' => 'approval', 'approval_requested_at' => $at->utc()]);
}

function waitingPage(User $agent, string $handle, CarbonImmutable $at): void
{
    AgentPage::query()->create(['user_id' => $agent->id, 'handle' => $handle, 'published' => false, 'bio' => 'Katy homes, first-time buyers.'])->forceFill(['approval_requested_at' => $at->utc()])->save();
}

function waitingQuestion(object $test, User $agent, string $question): string
{
    $id = $test->actingAs($agent)->postJson('/api/v1/virtual-broker/questions', ['question' => $question])->assertCreated()->json('data.id');
    $test->postJson("/api/v1/virtual-broker/questions/{$id}/escalate")->assertOk();

    return $id;
}

describe('the approvals queue (A15.3)', function () {
    it('lets the designated broker clear posts, pages and questions on the web (the done-when)', function () {
        $now = CarbonImmutable::now();
        $post = waitingPost($this->james, 'Open house Saturday at 12 Oak Lane.', $now->subHours(3));
        waitingPage($this->priya, 'priya-shah', $now->subHours(2));
        $this->travel(5)->minutes();
        $question = waitingQuestion($this, $this->james, 'Can I pay a referral fee to my cousin?');

        $queue = $this->actingAs($this->linda)->getJson('/api/v1/organisation/approvals')->assertOk()->json('data');
        expect($queue)->toMatchArray(['brokerage' => 'Lone Star Realty', 'scope' => 'brokerage', 'fallback_hours' => 4, 'can' => ['posts' => true, 'questions' => true, 'settings' => true]])
            ->and($queue['counts'])->toBe(['posts' => 1, 'pages' => 1, 'questions' => 1, 'overdue' => 0])
            // Oldest first; Friday's hours before 8 AM don't count.
            ->and(array_column($queue['items'], 'kind'))->toBe(['post', 'page', 'question'])
            ->and($queue['items'][0]['agent'])->toBe('James Miller')
            ->and($queue['items'][1]['agent'])->toBe('Priya Shah');

        $this->get('/organisation/approvals')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/approvals')->has('queue.items', 3));

        $this->postJson("/organisation/approvals/post/{$post->uuid}", ['action' => 'approve'])->assertOk()->assertJsonCount(2, 'data.items');
        $this->postJson('/organisation/approvals/page/priya-shah', ['action' => 'return'])->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->postJson('/organisation/approvals/page/priya-shah', ['action' => 'return', 'text' => 'Add your license number.'])->assertOk();
        $this->postJson("/organisation/approvals/question/{$question}", ['action' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors('action');
        $done = $this->postJson("/organisation/approvals/question/{$question}", ['action' => 'answer', 'text' => 'No. Referral fees go through the brokerage, never to an unlicensed person.', 'add_to_knowledge' => true])
            ->assertOk()->json('data');

        expect($done['items'])->toBe([])
            ->and(SocialPost::query()->sole()->status)->not->toBe('approval')
            ->and(AgentPage::query()->sole()->approval_note)->toBe('Add your license number.')
            ->and(PolicyPassage::query()->where('kind', PolicyPassage::FAQ)->count())->toBe(1)
            ->and(AuditEvent::query()->whereIn('action', ['social_post.approved', 'agent_page.returned', 'broker_question.answered'])->count())->toBe(3);
    });

    it('clears the queue from the phone through the API', function () {
        $post = waitingPost($this->james, 'Just listed in Katy.', CarbonImmutable::now()->subHour());
        $this->actingAs($this->linda)->postJson("/api/v1/organisation/approvals/post/{$post->uuid}", ['action' => 'return', 'text' => 'Add the brokerage name.'])
            ->assertOk()->assertJsonPath('data.items', []);
        expect($post->refresh()->status)->toBe('draft');
    });

    it('shows office admins only what their duties cover', function () {
        waitingPost($this->james, 'Open house Saturday.', CarbonImmutable::now()->subHour());
        $question = waitingQuestion($this, $this->james, 'Can I pay a referral fee to my cousin?');

        $none = $this->actingAs($this->ana)->getJson('/api/v1/organisation/approvals')->assertOk()->json('data');
        expect($none['items'])->toBe([])->and($none['can'])->toBe(['posts' => false, 'questions' => false, 'settings' => false]);
        $this->postJson("/api/v1/organisation/approvals/question/{$question}", ['action' => 'answer', 'text' => 'No.'])->assertNotFound();

        $this->actingAs($this->linda)->putJson("/api/v1/organisation/staff/{$this->ana->id}", ['duties' => ['answer_escalations']])->assertOk();
        $only = $this->actingAs($this->ana)->getJson('/api/v1/organisation/approvals')->json('data');
        expect(array_column($only['items'], 'kind'))->toBe(['question']);
        $this->putJson('/api/v1/organisation/approvals/settings', ['fallback_hours' => 2])->assertForbidden();
    });

    it('limits a team leader to their team', function () {
        waitingPost($this->james, 'Open house Saturday.', CarbonImmutable::now()->subHour());
        $priyas = waitingPost($this->priya, 'Okoye Team just closed in Sugar Land.', CarbonImmutable::now()->subHour());
        $this->actingAs($this->linda)->putJson("/api/v1/organisation/staff/{$this->ade->id}", ['duties' => ['approve_posts']])->assertOk();

        $team = $this->actingAs($this->ade)->getJson('/api/v1/organisation/approvals')->assertOk()->json('data');
        expect($team)->toMatchArray(['scope' => 'team', 'team' => 'Okoye Team'])
            ->and(array_column($team['items'], 'id'))->toBe([$priyas->uuid]);
        $james = SocialPost::query()->where('user_id', $this->james->id)->sole();
        $this->postJson("/api/v1/organisation/approvals/post/{$james->uuid}", ['action' => 'approve'])->assertNotFound();
    });

    it('lets only the designated broker set the fallback, from 1 to 40 working hours', function () {
        $this->actingAs($this->linda)->putJson('/api/v1/organisation/approvals/settings', ['fallback_hours' => 0])->assertUnprocessable();
        $this->putJson('/api/v1/organisation/approvals/settings', ['fallback_hours' => 2])->assertOk()->assertJsonPath('data.fallback_hours', 2);
        expect($this->lone->refresh()->approval_settings)->toBe(['fallback_hours' => 2])
            ->and(AuditEvent::query()->where('action', 'approvals.settings')->count())->toBe(1);
        $this->actingAs($this->ade)->putJson('/api/v1/organisation/approvals/settings', ['fallback_hours' => 8])->assertNotFound();
    });

    it('is only for brokers', function () {
        $this->actingAs($this->james)->getJson('/api/v1/organisation/approvals')->assertNotFound();
    });
});

describe('working hours and the office admin fallback (A15.3)', function () {
    it('counts only 8 AM to 6 PM Central, Monday to Friday', function () {
        $friday = CarbonImmutable::parse('2026-11-27 17:00:00', WorkingHours::TIMEZONE);
        expect(WorkingHours::between($friday, $friday->addDays(3)->setTime(9, 0)))->toBe(2.0)
            ->and(WorkingHours::between($friday->setTime(9, 0), $friday->setTime(15, 30)))->toBe(6.5)
            ->and(WorkingHours::between($friday, $friday->subHour()))->toBe(0.0);
    });

    it('passes an item that waited past the limit to the office admins with the duty, once', function () {
        // Sent Friday at noon: 6 working hours Friday, 1 on Monday.
        $post = waitingPost($this->james, 'Open house Saturday.', CarbonImmutable::parse('2026-11-27 12:00:00', WorkingHours::TIMEZONE));
        waitingPost($this->james, 'Just listed.', CarbonImmutable::now()->subMinutes(30));
        $this->actingAs($this->linda)->putJson("/api/v1/organisation/staff/{$this->ana->id}", ['duties' => ['approve_posts']])->assertOk();

        $queue = $this->getJson('/api/v1/organisation/approvals')->json('data');
        expect($queue['counts']['overdue'])->toBe(1)->and($queue['items'][0])->toMatchArray(['id' => $post->uuid, 'hours' => 7.0, 'overdue' => true]);

        $this->artisan('reos:approvals:fallback')->assertSuccessful();
        Notification::assertSentTo([$this->linda, $this->ana], ApprovalOverdue::class);
        Notification::assertNotSentTo($this->ade, ApprovalOverdue::class);
        expect(DB::table('approval_fallbacks')->count())->toBe(1)
            ->and(AuditEvent::query()->where('action', 'approvals.fallback')->sole()->subject_id)->toBe($post->uuid);

        $this->artisan('reos:approvals:fallback')->assertSuccessful();
        expect(DB::table('approval_fallbacks')->count())->toBe(1);
        Notification::assertSentToTimes($this->ana, ApprovalOverdue::class, 1);
    });
});
