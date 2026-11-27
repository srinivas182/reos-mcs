<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Features\Flags;
use App\Models\AgentProfile;
use App\Models\AuditEvent;
use App\Models\BrokerageChallenge;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Conversation;
use App\Models\Organisation;
use App\Models\Pod;
use App\Models\User;
use App\Models\VideoSession;
use App\Notifications\PodMatched;
use App\Pods\Pods;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);
    // Monday 23 November 2026, 9:00 in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
});

function podAgent(object $test, string $name, string $goal = 'launch', ?Organisation $at = null): User
{
    $agent = User::factory()->withRole(Role::Agent, $at ?? $test->lone)->create(['name' => $name, 'current_state' => 'TX']);
    AgentProfile::query()->create(['user_id' => $agent->id, 'goal' => $goal]);

    return $agent;
}

/** A logged call or note with one of the agent's contacts. */
function talked(User $agent, int $times, CarbonImmutable $at): void
{
    $contact = Contact::query()->create(['uuid' => (string) Str::uuid(), 'owner_type' => 'user', 'owner_id' => $agent->id, 'first_name' => 'A', 'last_name' => 'Client']);
    foreach (range(1, $times) as $i) {
        (new ContactNote)->forceFill(['contact_id' => $contact->id, 'author_id' => $agent->id, 'body' => "Call {$i}", 'source' => 'reos', 'created_at' => $at, 'updated_at' => $at])->save();
    }
}

describe('accountability pods (A13.2)', function () {
    it('matches five agents with the same goal, who then see each other\'s weekly goals and numbers (the done-when)', function () {
        $flags = app(Flags::class);
        $names = ['Priya Shah', 'Tom Reyes', 'Ana Ortiz', 'Ben Cole', 'Cara Lin'];
        $agents = array_map(fn (string $n): User => podAgent($this, $n), $names);
        $grower = podAgent($this, 'Gus Hale', 'grow');

        foreach (array_slice($agents, 0, 4) as $agent) {
            $this->actingAs($agent)->postJson('/api/v1/pod/join')->assertOk()->assertJsonPath('data.status', 'waiting');
        }
        $this->actingAs($grower)->postJson('/api/v1/pod/join')->assertOk()->assertJsonPath('data.status', 'waiting');
        // The fifth with the same goal makes the pod.
        $view = $this->actingAs($agents[4])->postJson('/api/v1/pod/join')->assertOk()->json('data');
        expect($view['status'])->toBe('member')->and(array_column($view['pod']['members'], 'name'))->toBe($names);
        Notification::assertSentTo($agents[0], PodMatched::class);
        $this->actingAs($grower)->getJson('/api/v1/pod')->assertJsonPath('data.status', 'waiting');

        // The weekly call is booked in the video rooms; the pod has its own conversation.
        $pod = Pod::query()->sole();
        $call = VideoSession::query()->where('pod_id', $pod->id)->sole();
        expect($call->kind)->toBe(VideoSession::POD_CALL)->and($call->participants()->count())->toBe(5)
            ->and($call->starts_at->diffInMinutes($call->ends_at))->toEqual(15)
            ->and(Conversation::query()->whereKey($pod->conversation_id)->sole()->participants()->count())->toBe(5);

        // Goals and numbers: each member's, inside the pod only.
        talked($agents[1], 3, now());
        $this->actingAs($agents[1])->putJson('/api/v1/pod/goal', ['text' => '20 conversations and 2 appointments'])->assertOk();
        $this->actingAs($agents[2])->putJson('/api/v1/pod/share', ['metrics' => ['ce_hours']])->assertOk();
        $members = collect($this->actingAs($agents[0])->getJson('/api/v1/pod')->json('data.pod.members'))->keyBy('name');
        expect($members['Tom Reyes']['goal'])->toBe('20 conversations and 2 appointments')
            ->and(collect($members['Tom Reyes']['metrics'])->firstWhere('key', 'conversations')['done'])->toBe(3)
            ->and(array_column($members['Ana Ortiz']['metrics'], 'key'))->toBe(['ce_hours'])
            ->and($members['Priya Shah']['me'])->toBeTrue();

        // Pod members can message each other; an agent outside the pod can't see it.
        $messages = $flags->flag('messages');
        $messages->forceFill(['stage' => 'everyone'])->save();
        $flags->changed($messages);
        $this->actingAs($agents[0])->postJson('/api/v1/messages', ['people' => [$agents[3]->id], 'body' => 'Ready for Monday?'])->assertCreated();
        $this->actingAs($grower)->postJson('/api/v1/messages', ['people' => [$agents[3]->id], 'body' => 'Hi'])->assertUnprocessable();

        // Leaving is undoable.
        $left = $this->actingAs($agents[4])->postJson('/api/v1/pod/leave')->assertOk()->assertJsonPath('data.status', 'none')->json('left');
        $this->postJson('/api/v1/pod/rejoin', ['pod' => $left])->assertOk()->assertJsonPath('data.status', 'member');
    });

    it('makes a pod of three after a week of waiting, inside the brokerage', function () {
        $three = [podAgent($this, 'Priya Shah'), podAgent($this, 'Tom Reyes'), podAgent($this, 'Ana Ortiz')];
        $elsewhere = podAgent($this, 'Zed Moss', 'launch', Organisation::factory()->create(['name' => 'Gulf Coast Homes']));
        foreach ([...$three, $elsewhere] as $agent) {
            $this->actingAs($agent)->postJson('/api/v1/pod/join')->assertOk();
        }
        expect(app(Pods::class)->run()['matched'])->toBe(0);

        $this->travel(8)->days();
        $this->artisan('reos:pods')->assertSuccessful();
        expect(Pod::query()->sole()->members()->count())->toBe(3);
        $this->actingAs($elsewhere)->getJson('/api/v1/pod')->assertJsonPath('data.status', 'waiting');
        $this->actingAs($three[0])->get('/pod')->assertOk()->assertInertia(fn (Assert $page) => $page->component('pod/index')->where('pod.status', 'member')->has('pod.pod.members', 3));
    });
});

describe('pod and challenges on the web (A13.2)', function () {
    it('joins, sets a goal, leaves and rejoins through the web routes', function () {
        $agents = array_map(fn (string $n): User => podAgent($this, $n), ['Priya Shah', 'Tom Reyes', 'Ana Ortiz', 'Ben Cole', 'Cara Lin']);
        foreach ($agents as $agent) {
            $this->actingAs($agent)->from('/pod')->post('/pod/join')->assertRedirect('/pod');
        }
        $pod = Pod::query()->sole();
        $this->put('/pod/goal', ['text' => 'Two open houses'])->assertRedirect('/pod');
        $this->put('/pod/goal', ['text' => ''])->assertSessionHasErrors('text');
        $this->get('/pod')->assertInertia(fn (Assert $page) => $page->where('pod.pod.members.4.goal', 'Two open houses'));
        $this->post('/pod/leave')->assertRedirect('/pod');
        expect($pod->members()->count())->toBe(4);
        $this->post('/pod/rejoin', ['pod' => $pod->uuid])->assertRedirect('/pod');
        expect($pod->members()->count())->toBe(5);

        $this->actingAs($this->linda)->post('/organisation/challenges', ['title' => 'Posts this week', 'metric' => 'posts', 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-29'])->assertSessionHasNoErrors();
        $id = BrokerageChallenge::query()->sole()->uuid;
        $this->actingAs($agents[0])->from('/pod')->post("/brokerage-challenges/{$id}/join")->assertRedirect('/pod');
        $this->get('/pod')->assertInertia(fn (Assert $page) => $page->where('challenges.0.joined', true)->where('challenges.0.entrants', 1));
        $this->post("/brokerage-challenges/{$id}/leave")->assertRedirect('/pod');
        $this->get('/pod')->assertInertia(fn (Assert $page) => $page->where('challenges.0.entrants', 0));
        // Agents can't open the broker's page.
        $this->get('/organisation/challenges')->assertForbidden();
    });
});

describe('brokerage challenges (A13.2)', function () {
    it('ranks agents who opt in by total and by most improved', function () {
        $priya = podAgent($this, 'Priya Shah');
        $tom = podAgent($this, 'Tom Reyes');
        $quiet = podAgent($this, 'Leo Grant');
        // Tom talked a lot the week before; Priya hardly at all.
        talked($tom, 10, now()->subDays(4));
        talked($priya, 1, now()->subDays(4));

        $this->actingAs($this->linda)->get('/organisation/challenges')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organisation/challenges')->has('challenges', 0));
        $this->post('/organisation/challenges', ['title' => 'Most conversations this week', 'metric' => 'conversations', 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-29'])->assertSessionHasNoErrors();
        $this->post('/organisation/challenges', ['title' => 'Too long', 'metric' => 'conversations', 'starts_on' => '2026-11-23', 'ends_on' => '2027-06-01'])->assertSessionHasErrors('ends_on');
        expect(AuditEvent::query()->where('action', 'challenge.created')->count())->toBe(1);

        $challenge = $this->actingAs($priya)->getJson('/api/v1/challenges')->assertOk()->json('data.0');
        expect($challenge)->toMatchArray(['title' => 'Most conversations this week', 'joined' => false, 'entrants' => 0]);
        $this->postJson("/api/v1/challenges/{$challenge['id']}/join")->assertOk();
        $this->actingAs($tom)->postJson("/api/v1/challenges/{$challenge['id']}/join")->assertOk();

        talked($tom, 6, now()->addHour());
        talked($priya, 5, now()->addHour());
        $this->travel(2)->hours();
        $board = $this->actingAs($priya)->getJson('/api/v1/challenges')->json('data.0');
        expect(array_column($board['total'], 'name'))->toBe(['Tom Reyes', 'Priya Shah'])
            ->and(array_column($board['improved'], 'name'))->toBe(['Priya Shah', 'Tom Reyes'])
            ->and($board['improved'][0])->toMatchArray(['value' => 5, 'improved' => 4, 'personal_best' => true, 'me' => true])
            ->and($board['total'][0]['personal_best'])->toBeFalse()
            ->and(array_column($board['total'], 'name'))->not->toContain('Leo Grant');

        $this->postJson("/api/v1/challenges/{$challenge['id']}/leave")->assertOk()->assertJsonPath('data.0.entrants', 1);
        $this->actingAs($quiet)->getJson('/api/v1/challenges')->assertJsonPath('data.0.joined', false);
        $outsider = podAgent($this, 'Zed Moss', 'launch', Organisation::factory()->create());
        $this->actingAs($outsider)->postJson("/api/v1/challenges/{$challenge['id']}/join")->assertNotFound();
    });
});
