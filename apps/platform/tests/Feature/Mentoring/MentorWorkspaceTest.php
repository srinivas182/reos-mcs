<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Features\Flags;
use App\Mentoring\MentorProgram;
use App\Models\AgentProfile;
use App\Models\MenteeNote;
use App\Models\Organisation;
use App\Models\SessionBooking;
use App\Models\Todo;
use App\Models\User;
use App\Models\VideoSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
    $this->dana = User::factory()->withRole(Role::Mentor, $this->lone)->create(['name' => 'Dana Brooks']);
    $this->priya = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Priya Shah', 'last_active_at' => now()->subDays(9)]);
    $this->tom = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Tom Reyes', 'last_active_at' => now()->subDay()]);
    $this->leo = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'Leo Grant', 'last_active_at' => now()]);
    foreach ([$this->priya, $this->tom, $this->leo] as $agent) {
        AgentProfile::query()->create(['user_id' => $agent->id, 'goal' => 'launch']);
    }

    $this->actingAs($this->dana)->putJson('/api/v1/bookings/offer', [
        'bio' => 'Twelve years in Katy.', 'languages' => ['en'], 'topics' => [], 'hours' => ['mon' => [['08:00', '17:00']], 'tue' => [['08:00', '17:00']]],
        'timezone' => 'America/Chicago', 'price_30_cents' => 0, 'price_60_cents' => 0, 'active' => true,
    ])->assertOk();
    app(MentorProgram::class)->save($this->lone, $this->dana, ['topics' => ['first_deals'], 'price_30_cents' => 4000, 'price_60_cents' => 7500, 'points' => true, 'max_mentees' => 5, 'who' => 'everyone'], $this->linda);
    app(MentorProgram::class)->assign($this->lone, $this->priya, $this->dana, $this->linda);
});

describe('the mentor workspace (A13.1b)', function () {
    it('shows Today, mentees with who needs help, and a mentee with private notes and goals set together', function () {
        $booked = $this->actingAs($this->tom)->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-24T17:00:00Z', 'minutes' => 30, 'topic' => 'Pricing my first listing', 'pay_with' => 'card'])->assertCreated()->json('data');
        SessionBooking::query()->where('uuid', $booked['uuid'])->update(['status' => 'confirmed']);

        $today = $this->actingAs($this->dana)->getJson('/api/v1/mentoring/today')->assertOk()->json('data');
        expect($today)->toMatchArray(['brokerage' => 'Lone Star Realty', 'mentees' => 2])
            ->and(array_column($today['needs'], 'signal', 'name'))->toBe(['Priya Shah' => 'quiet', 'Tom Reyes' => 'behind'])
            ->and($today['needs'][0]['assigned'])->toBeTrue()
            ->and(array_column($today['upcoming'], 'agent'))->toBe(['Tom Reyes']);

        expect(array_column($this->getJson('/api/v1/mentoring/mentees?filter=assigned')->json('data'), 'name'))->toBe(['Priya Shah'])
            ->and(array_column($this->getJson('/api/v1/mentoring/mentees?filter=booked')->json('data'), 'name'))->toBe(['Tom Reyes']);

        // Notes are the mentor's only; goals become Priya's to-dos.
        $note = $this->postJson("/api/v1/mentoring/mentees/{$this->priya->id}/notes", ['body' => 'Nervous about her first open house.'])->assertCreated()->json('data');
        $this->postJson("/api/v1/mentoring/mentees/{$this->priya->id}/goals", ['title' => 'Host an open house', 'due_date' => '2026-11-29'])->assertCreated();
        $this->postJson("/api/v1/mentoring/mentees/{$this->priya->id}/goals", ['title' => 'Late', 'due_date' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('due_date');
        $detail = $this->getJson("/api/v1/mentoring/mentees/{$this->priya->id}")->assertOk()->json('data');
        expect($detail['mentee'])->toMatchArray(['name' => 'Priya Shah', 'assigned' => true])
            ->and($detail['plan'])->toHaveKeys(['week', 'weeks', 'done', 'total'])
            ->and(array_column($detail['scorecard'], 'key'))->toBe(['conversations', 'appointments', 'clients', 'ce_hours'])
            ->and(array_column($detail['notes'], 'body'))->toBe(['Nervous about her first open house.'])
            ->and($detail['goals'][0])->toMatchArray(['title' => 'Host an open house', 'due_date' => '2026-11-29', 'done' => false])
            ->and($detail)->not->toHaveKey('contacts');
        expect(Todo::query()->where('user_id', $this->priya->id)->where('source', 'mentor')->sole()->related_label)->toBe('Dana Brooks');

        // Removing a note can be undone.
        $this->deleteJson("/api/v1/mentoring/notes/{$note['id']}")->assertOk();
        expect($this->getJson("/api/v1/mentoring/mentees/{$this->priya->id}")->json('data.notes'))->toBe([]);
        $this->postJson("/api/v1/mentoring/notes/{$note['id']}/restore")->assertOk();
        expect(MenteeNote::query()->count())->toBe(1);

        // Leo isn't her mentee; Priya can't open the workspace; another brokerage's mentor sees nothing.
        $this->getJson("/api/v1/mentoring/mentees/{$this->leo->id}")->assertNotFound();
        $this->postJson("/api/v1/mentoring/mentees/{$this->leo->id}/notes", ['body' => 'Hello'])->assertNotFound();
        $this->actingAs($this->priya)->getJson('/api/v1/mentoring/today')->assertNotFound();
        $this->actingAs($this->priya)->deleteJson("/api/v1/mentoring/notes/{$note['id']}")->assertNotFound();
        $other = User::factory()->withRole(Role::Mentor, Organisation::factory()->create())->create();
        $this->actingAs($other)->getJson("/api/v1/mentoring/mentees/{$this->priya->id}")->assertNotFound();

        // The web workspace (screen 48).
        $this->actingAs($this->dana)->get('/mentoring')->assertOk()->assertInertia(fn (Assert $page) => $page->component('mentoring/index')->where('today.mentees', 2)->has('mentees', 2));
        $this->get("/mentoring/{$this->priya->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('mentoring/show')->where('detail.mentee.name', 'Priya Shah')->has('detail.notes', 1));
        $this->post("/mentoring/{$this->priya->id}/notes", ['body' => 'Called her on Friday.'], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($this->priya)->get('/mentoring')->assertNotFound();
    });

    it('wraps a session up into the mentee\'s to-dos, and holds a low rating for the brokerage\'s review (the done-when)', function () {
        $booked = $this->actingAs($this->priya)->postJson('/api/v1/bookings', ['instructor' => $this->dana->id, 'starts_at' => '2026-11-23T17:00:00Z', 'minutes' => 30, 'topic' => 'Pricing my first listing'])->assertCreated()->assertJsonPath('data.paid_with', 'free')->json('data');
        $booking = SessionBooking::query()->where('uuid', $booked['uuid'])->sole();
        VideoSession::query()->whereKey($booking->video_session_id)->update(['notes' => "We priced 2418 Willow Bend from three comps.\n- Pull two more comps from this month\n- Call the seller about the price"]);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 11:45', 'America/Chicago'));
        $booking->forceFill(['status' => 'completed'])->save();

        // Screen 27: a summary and dated action items, edited and sent.
        $draft = $this->actingAs($this->dana)->getJson("/api/v1/bookings/{$booking->uuid}/wrap-up")->assertOk()->json('data');
        expect($draft['engine'])->toBe('template')
            ->and($draft['summary'])->toBe('Pricing my first listing. We priced 2418 Willow Bend from three comps.')
            ->and($draft['tasks'])->toBe([['title' => 'Pull two more comps from this month', 'due_date' => '2026-11-30'], ['title' => 'Call the seller about the price', 'due_date' => '2026-11-30']]);
        $this->getJson('/api/v1/mentoring/today')->assertJsonPath('data.wrap_ups.0.uuid', $booking->uuid);
        $this->postJson("/api/v1/bookings/{$booking->uuid}/tasks", ['tasks' => $draft['tasks'], 'summary' => $draft['summary']])->assertOk()->assertJsonPath('data.sent', 2);
        $todos = Todo::query()->where('user_id', $this->priya->id)->where('source', 'session')->get();
        expect($todos)->toHaveCount(2)->and($todos[0]->notes)->toBe($draft['summary'])->and($todos[0]->due_date?->toDateString())->toBe('2026-11-30');
        $this->getJson('/api/v1/mentoring/today')->assertJsonPath('data.wrap_ups', []);
        $this->getJson("/api/v1/mentoring/mentees/{$this->priya->id}")->assertJsonPath('data.sessions.0.summary', $draft['summary']);
        $this->actingAs($this->tom)->getJson("/api/v1/bookings/{$booking->uuid}/wrap-up")->assertNotFound();

        // Screen 119: a 1-star rating with a comment waits for the brokerage; it never counts until kept.
        $this->actingAs($this->priya)->postJson("/api/v1/bookings/{$booking->uuid}/rating", ['stars' => 1, 'comment' => 'She was 20 minutes late.'])->assertOk();
        $this->travel(2)->days();
        $this->getJson('/api/v1/mentors')->assertJsonPath('data.mentors.0.rating', null);
        $this->actingAs($this->linda)->get('/organisation/mentors')->assertInertia(fn (Assert $page) => $page->has('reviews', 1)->where('reviews.0.comment', 'She was 20 minutes late.'));
        $this->post("/organisation/mentors/reviews/{$booking->uuid}", ['decision' => 'keep'])->assertSessionHasNoErrors();
        $this->actingAs($this->priya)->getJson('/api/v1/mentors')->assertJsonPath('data.mentors.0.rating', 1)->assertJsonPath('data.mentors.0.ratings', 1);
        $this->actingAs($this->linda)->post("/organisation/mentors/reviews/{$booking->uuid}", ['decision' => 'hide'])->assertNotFound();
    });
});
