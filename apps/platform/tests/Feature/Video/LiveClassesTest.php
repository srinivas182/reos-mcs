<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\Delivery;
use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Integrations\IntegrationMode;
use App\Models\AuditEvent;
use App\Models\ClassCheck;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\IntegrationSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Notifications\ClassCheckReminder;
use App\Notifications\ClassReportReady;
use App\Notifications\SessionBooked;
use App\Notifications\SessionStarting;
use App\Services\Content\Courses;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $flags = app(Flags::class);
    $flag = $flags->flag('video_rooms');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);
    IntegrationSetting::query()->create(['integration_id' => 'livekit', 'mode' => IntegrationMode::Simulator]);

    // Monday 23 November 2026, 9:00 in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', 'America/Chicago'));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->nelle = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Nelle Hart']);
    $this->ana = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ana Ruiz']);
    $this->ben = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Ben Ortiz']);

    // A live online CE course of Nelle's school, approved in Texas: a check every 15 minutes, 90% of seat time.
    $ops = User::factory()->superAdmin()->create();
    $this->course = app(Courses::class)->create(['title' => 'Texas Contract Update', 'type' => 'ce', 'states' => ['TX'], 'hours' => 2], $ops);
    $this->course->forceFill(['status' => CourseStatus::Live, 'organisation_id' => $this->school->id, 'delivery' => Delivery::LiveOnline])->save();
    CourseApproval::query()->create(['course_id' => $this->course->id, 'state_code' => 'TX', 'delivery' => 'live_online', 'number' => 'CE-7781', 'hours' => 2,
        'status' => 'approved', 'checkin_minutes' => 15, 'presence_percent' => 90]);
});

/** Nelle schedules the class for 10:30 to 12:30 today. */
function scheduleClass(object $test, int $capacity = 30): array
{
    return $test->actingAs($test->nelle)->postJson('/api/v1/classes', [
        'course_id' => $test->course->id, 'title' => 'Contract update: live review', 'agenda' => 'The new amendments',
        'starts_at' => '2026-11-23T16:30:00Z', 'minutes' => 120, 'capacity' => $capacity,
    ])->assertCreated()->json('data');
}

describe('scheduling and registering (A10.2b)', function () {
    it('schedules a CE class with the approval\'s rules, and students of the course register up to the capacity', function () {
        $this->actingAs($this->nelle)->getJson('/api/v1/classes')->assertOk()
            ->assertJsonPath('can_schedule', true)->assertJsonPath('courses.0.title', 'Texas Contract Update')->assertJsonPath('courses.0.ce', true);

        $class = scheduleClass($this, capacity: 1);
        expect($class)->toMatchArray(['ce' => true, 'ce_hours' => 2.0, 'checkin_minutes' => 15, 'registered' => 0, 'capacity' => 1, 'me' => 'host', 'minutes' => 120]);

        $this->actingAs($this->ana)->getJson('/api/v1/classes')->assertOk()->assertJsonPath('can_schedule', false)
            ->assertJsonPath('data.0.id', $class['id'])->assertJsonPath('data.0.can_register', true)->assertJsonPath('data.0.me', null);
        $this->postJson("/api/v1/classes/{$class['id']}/registration")->assertOk()->assertJsonPath('data.me', 'registered')->assertJsonPath('data.registered', 1);
        Notification::assertSentTo($this->ana, SessionBooked::class);

        // Full.
        $this->actingAs($this->ben)->postJson("/api/v1/classes/{$class['id']}/registration")->assertUnprocessable()->assertJsonValidationErrors('class');
        $this->getJson('/api/v1/classes')->assertJsonPath('data.0.full', true)->assertJsonPath('data.0.can_register', false);

        // Ana changes her mind before it starts; the seat frees up.
        $this->actingAs($this->ana)->deleteJson("/api/v1/classes/{$class['id']}/registration")->assertOk()->assertJsonPath('data.me', null);
        $this->actingAs($this->ben)->postJson("/api/v1/classes/{$class['id']}/registration")->assertOk();
    });

    it('only lets instructors schedule for their own courses, and only the course\'s students see the class', function () {
        $other = app(Courses::class)->create(['title' => 'Someone else\'s course', 'type' => 'ce', 'states' => ['FL']], User::factory()->superAdmin()->create());
        $other->forceFill(['status' => CourseStatus::Live])->save();
        $this->actingAs($this->nelle)->postJson('/api/v1/classes', ['course_id' => $other->id, 'title' => 'Nope', 'starts_at' => '2026-11-23T16:30:00Z', 'minutes' => 60, 'capacity' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->actingAs($this->ana)->postJson('/api/v1/classes', ['course_id' => $this->course->id, 'title' => 'Nope', 'starts_at' => '2026-11-23T16:30:00Z', 'minutes' => 60, 'capacity' => 10])
            ->assertUnprocessable();

        $class = scheduleClass($this);
        // The course moves to Florida only: Texas students don't see its classes.
        $this->course->forceFill(['states' => ['FL']])->save();
        $this->actingAs($this->ben)->getJson('/api/v1/classes')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/classes/{$class['id']}/registration")->assertNotFound();
        $this->getJson("/api/v1/classes/{$class['id']}/live")->assertNotFound();
    });

    it('reminds registered students an hour before', function () {
        $class = scheduleClass($this);
        $this->actingAs($this->ana)->postJson("/api/v1/classes/{$class['id']}/registration")->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-23 09:35', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        Notification::assertSentToTimes($this->ana, SessionStarting::class, 1);
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        Notification::assertSentToTimes($this->ana, SessionStarting::class, 1);
    });
});

describe('in class (A10.2b: L12 and i12)', function () {
    beforeEach(function () {
        $this->class = scheduleClass($this);
        $this->actingAs($this->ana)->postJson("/api/v1/classes/{$this->class['id']}/registration")->assertOk();
        $this->actingAs($this->ben)->postJson("/api/v1/classes/{$this->class['id']}/registration")->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:30', 'America/Chicago'));
        foreach ([$this->nelle, $this->ana, $this->ben] as $person) {
            $this->actingAs($person)->postJson("/api/v1/sessions/{$this->class['id']}/join")->assertOk();
        }
    });

    it('runs polls, raised hands, questions with likes and chat', function () {
        $id = $this->class['id'];
        $poll = $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/polls", ['question' => 'Who signs the amendment?', 'options' => ['Buyer', 'Seller', 'Both']])
            ->assertOk()->assertJsonPath('data.polls.0.total', 0)->json('data.polls.0.id');
        $this->actingAs($this->ana)->postJson("/api/v1/classes/{$id}/polls", ['question' => 'Mine?', 'options' => ['A', 'B']])->assertUnprocessable();

        // Results appear once she's answered; once only.
        $this->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.polls.0.options.0.votes', null)->assertJsonPath('data.viewers', 2);
        $this->postJson("/api/v1/classes/{$id}/polls/{$poll}/votes", ['option' => 2])->assertOk()
            ->assertJsonPath('data.polls.0.my_vote', 2)->assertJsonPath('data.polls.0.options.2.votes', 1)->assertJsonPath('data.polls.0.total', 1);
        $this->postJson("/api/v1/classes/{$id}/polls/{$poll}/votes", ['option' => 0])->assertUnprocessable();
        $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/polls/{$poll}/close")->assertOk()->assertJsonPath('data.polls.0.closed', true);
        $this->actingAs($this->ben)->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.polls.0.options.2.votes', 1);
        $this->postJson("/api/v1/classes/{$id}/polls/{$poll}/votes", ['option' => 1])->assertUnprocessable();

        // Ben raises his hand; Nelle brings him on.
        $this->postJson("/api/v1/classes/{$id}/hand", ['raised' => true])->assertOk()->assertJsonPath('data.me.hand', true)->assertJsonPath('data.hands', []);
        $this->actingAs($this->nelle)->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.hands.0.name', 'Ben Ortiz');
        $this->postJson("/api/v1/classes/{$id}/stage", ['user' => $this->ben->id, 'on' => true])->assertOk()->assertJsonPath('data.hands.0.on_stage', true);
        $this->actingAs($this->ben)->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.me.on_stage', true)->assertJsonPath('data.me.hand', false);

        // Ana asks; Ben likes it; it tops the queue; Nelle answers it.
        $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/questions", ['body' => 'Hosts don\'t ask'])->assertUnprocessable();
        $this->actingAs($this->ben)->postJson("/api/v1/classes/{$id}/questions", ['body' => 'Is the old form still valid?'])->assertOk();
        $q = $this->actingAs($this->ana)->postJson("/api/v1/classes/{$id}/questions", ['body' => 'Does the buyer initial every page?'])->assertOk()->json('data.questions.1.id');
        $this->actingAs($this->ben)->postJson("/api/v1/classes/{$id}/questions/{$q}/like", ['like' => true])->assertOk()
            ->assertJsonPath('data.questions.0.id', $q)->assertJsonPath('data.questions.0.likes', 1)->assertJsonPath('data.questions.0.liked', true);
        $this->postJson("/api/v1/classes/{$id}/questions/{$q}/like", ['like' => true])->assertJsonPath('data.questions.0.likes', 1);
        $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/questions/{$q}/answered", ['answered' => true])->assertOk()
            ->assertJsonPath('data.questions.1.id', $q)->assertJsonPath('data.questions.1.answered', true);

        $this->actingAs($this->ana)->postJson("/api/v1/classes/{$id}/messages", ['body' => 'Thanks!'])->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Thanks!')->assertJsonPath('data.messages.0.mine', true);
    });

    it('sends attendance checks on the approval\'s interval with one reminder, and counts seat time', function () {
        $id = $this->class['id'];
        $this->actingAs($this->nelle)->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.ce', true)
            ->assertJsonPath('data.next_check_at', '2026-11-23T16:45:00+00:00');

        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:45', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        $check = ClassCheck::query()->sole();
        $this->actingAs($this->ana)->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.check.id', $check->id)->assertJsonPath('data.check.answered', false);
        $this->postJson("/api/v1/classes/{$id}/checks/{$check->id}/answer")->assertOk()->assertJsonPath('data.check.answered', true);
        // Only one open at a time.
        $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/checks")->assertUnprocessable();

        // A minute on, Ben (who hasn't answered) gets the one reminder.
        $this->travel(61)->seconds();
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        Notification::assertSentTo($this->ben, ClassCheckReminder::class);
        Notification::assertNotSentTo($this->ana, ClassCheckReminder::class);
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        Notification::assertSentToTimes($this->ben, ClassCheckReminder::class, 1);

        $this->actingAs($this->nelle)->getJson("/api/v1/classes/{$id}/live")->assertJsonPath('data.checks.0.answered', 1)->assertJsonPath('data.checks.0.of', 2);

        // After it closes, it's too late; Nelle can send another.
        $this->travel(3)->minutes();
        $this->actingAs($this->ben)->postJson("/api/v1/classes/{$id}/checks/{$check->id}/answer")->assertUnprocessable();
        $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/checks")->assertOk()->assertJsonCount(2, 'data.checks');

        // Ana leaves at 11:00 (30 minutes in); the class ends at 12:30 with Ben still there.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 11:00', 'America/Chicago'));
        $this->actingAs($this->ana)->postJson("/api/v1/sessions/{$id}/leave")->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:31', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        $session = VideoSession::query()->where('uuid', $id)->sole();
        $seconds = $session->participants()->pluck('seconds_present', 'user_id');
        expect($session->status)->toBe(VideoSession::ENDED)
            ->and($seconds[$this->ana->id])->toBe(1800)->and($seconds[$this->ben->id])->toBe(7200);
        expect(VideoSessionParticipant::query()->whereNotNull('hand_raised_at')->count())->toBe(0);
    });

    it('shows the class on the session for everyone in it', function () {
        $this->actingAs($this->ana)->getJson("/api/v1/sessions/{$this->class['id']}")->assertOk()
            ->assertJsonPath('class.course', 'Texas Contract Update')->assertJsonPath('class.ce', true)->assertJsonPath('class.registered', 2);
    });
});

describe('the CE class report (A10.2c: i13)', function () {
    beforeEach(function () {
        $this->cara = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Cara Diaz']);
        $this->dan = User::factory()->withRole(Role::Candidate, Organisation::platform())->create(['name' => 'Dan Lee']);
        $this->paul = User::factory()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
        $this->class = scheduleClass($this);
        $id = $this->class['id'];
        foreach ([$this->ana, $this->ben, $this->cara, $this->dan] as $student) {
            $this->actingAs($student)->postJson("/api/v1/classes/{$id}/registration")->assertOk();
        }

        // 10:30 to 12:30; 90% means 108 minutes. Cara never comes.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:30', 'America/Chicago'));
        foreach ([$this->nelle, $this->ana, $this->ben, $this->dan] as $person) {
            $this->actingAs($person)->postJson("/api/v1/sessions/{$id}/join")->assertOk();
        }

        // Two checks: Ana answers both, Ben only the first, Dan the first.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:45', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        $first = ClassCheck::query()->latest('id')->firstOrFail();
        foreach ([$this->ana, $this->ben, $this->dan] as $student) {
            $this->actingAs($student)->postJson("/api/v1/classes/{$id}/checks/{$first->id}/answer")->assertOk();
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-23 11:00', 'America/Chicago'));
        $this->actingAs($this->dan)->postJson("/api/v1/sessions/{$id}/leave")->assertOk();
        $this->artisan('reos:sessions:tick')->assertSuccessful();
        $second = ClassCheck::query()->latest('id')->firstOrFail();
        $this->actingAs($this->ana)->postJson("/api/v1/classes/{$id}/checks/{$second->id}/answer")->assertOk();

        // The class ends; checks at 11:15 onward aren't sent (no tick ran), so two in all.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:31', 'America/Chicago'));
        $this->artisan('reos:sessions:tick')->assertSuccessful();
    });

    it('measures everyone against the approval when the class ends, and tells the instructor', function () {
        Notification::assertSentTo($this->nelle, ClassReportReady::class, fn (ClassReportReady $n) => $n->change === ClassReportReady::READY);
        $this->actingAs($this->nelle)->getJson('/api/v1/classes')->assertJsonPath('data.0.report', 'draft');

        $report = $this->getJson("/api/v1/classes/{$this->class['id']}/report")->assertOk()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.rules.required_minutes', 108)->assertJsonPath('data.rules.checks', 2)
            ->assertJsonPath('data.class.approval', 'TX CE-7781')
            ->assertJsonPath('data.counts', ['joined' => 3, 'earned' => 1, 'not_earned' => 2, 'review' => 1, 'undecided' => 1])
            ->assertJsonPath('data.can_send', false)->json('data.students');

        $by = collect($report)->keyBy('name');
        expect($by['Ana Ruiz'])->toMatchArray(['outcome' => 'earned', 'minutes_present' => 120, 'checks_answered' => 2, 'checks_total' => 2])
            ->and($by['Ben Ortiz'])->toMatchArray(['outcome' => 'review', 'reasons' => ['checks'], 'checks_answered' => 1])
            ->and($by['Dan Lee'])->toMatchArray(['outcome' => 'not_earned', 'reasons' => ['seat_time', 'checks'], 'minutes_present' => 30])
            ->and($by['Cara Diaz'])->toMatchArray(['outcome' => 'not_earned', 'reasons' => ['not_joined']])
            // Borderline cases first.
            ->and($report[0]['name'])->toBe('Ben Ortiz');
    });

    it('accepts or rejects borderline cases with a reason, audited, then confirms and sends to the school', function () {
        $id = $this->class['id'];
        $this->actingAs($this->nelle)->postJson("/api/v1/classes/{$id}/report/send")->assertUnprocessable()->assertJsonValidationErrors('report');
        $this->postJson("/api/v1/classes/{$id}/report/decisions", ['user' => $this->dan->id, 'decision' => 'accepted', 'reason' => 'Left early'])->assertUnprocessable();
        $this->postJson("/api/v1/classes/{$id}/report/decisions", ['user' => $this->ben->id, 'decision' => 'accepted'])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/classes/{$id}/report/decisions", ['user' => $this->ben->id, 'decision' => 'accepted', 'reason' => 'Answered the second check in chat; connection dropped.'])
            ->assertOk()->assertJsonPath('data.counts.earned', 2)->assertJsonPath('data.counts.undecided', 0)->assertJsonPath('data.can_send', true);
        $audit = AuditEvent::query()->where('action', 'class_report.decided')->sole();
        expect($audit->properties)->toMatchArray(['student' => $this->ben->id, 'decision' => 'accepted', 'checks' => '1/2']);

        $this->postJson("/api/v1/classes/{$id}/report/send")->assertOk()->assertJsonPath('data.status', 'sent')->assertJsonPath('data.sent_by', 'Nelle Hart')->assertJsonPath('data.can_decide', false);
        expect(AuditEvent::query()->where('action', 'class_report.sent')->sole()->properties['earned'])->toEqualCanonicalizing([$this->ana->id, $this->ben->id]);
        Notification::assertSentTo($this->paul, ClassReportReady::class, fn (ClassReportReady $n) => $n->change === ClassReportReady::SENT);

        // Sent is final; the school's admin can open it; nobody else can.
        $this->postJson("/api/v1/classes/{$id}/report/decisions", ['user' => $this->ben->id, 'decision' => 'rejected', 'reason' => 'Changed my mind'])->assertUnprocessable();
        $this->actingAs($this->paul)->get("/classes/{$id}/report")->assertOk()
            ->assertInertia(fn ($page) => $page->component('classes/report')->where('report.status', 'sent')->where('report.can_decide', false));
        $this->actingAs($this->ana)->getJson("/api/v1/classes/{$id}/report")->assertNotFound();
    });
});

describe('recording a live class (A10.2c)', function () {
    it('records a CE class only when its approval allows it', function () {
        $class = scheduleClass($this);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:30', 'America/Chicago'));
        $this->actingAs($this->nelle)->postJson("/api/v1/sessions/{$class['id']}/join")->assertOk();

        $this->postJson("/api/v1/sessions/{$class['id']}/recordings")->assertUnprocessable()->assertJsonValidationErrors('recording');
        CourseApproval::query()->update(['records_live' => true]);
        $this->postJson("/api/v1/sessions/{$class['id']}/recordings")->assertSuccessful();
    });
});
