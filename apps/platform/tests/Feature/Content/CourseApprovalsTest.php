<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\Approvals\CourseApprovals;
use App\Content\CourseStatus;
use App\Content\Delivery;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Notifications\ApprovalExpiring;
use App\Services\StateRules\StateRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-01 09:00', 'America/Chicago'));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->course = Course::query()->create([
        'organisation_id' => $this->school->id, 'title' => 'Principles of Real Estate II', 'slug' => 'principles-ii',
        'type' => 'qualifying', 'states' => ['TX'], 'hours' => 30, 'status' => CourseStatus::Live,
    ]);
});

/** Adds an approval through screen 50. */
function addApproval(object $test, array $data = []): CourseApproval
{
    $test->actingAs($test->paul)->post('/school/approvals', [
        'course_id' => $test->course->id, 'state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC 0451',
        'approved_on' => '2024-09-01', 'expires_on' => '2028-08-31', ...$data,
    ])->assertSessionHasNoErrors()->assertRedirect();

    return CourseApproval::query()->latest('id')->firstOrFail();
}

describe('state approvals (screen 50)', function () {
    it('records an approval with the state\'s seat-time rules, audited', function () {
        $approval = addApproval($this);

        expect($approval->status)->toBe(CourseApproval::APPROVED)
            ->and((float) $approval->hours)->toBe(30.0)
            // Texas: no daily segment over 12 hours, present for the hours credited, makeup after two-thirds within 90 days (22 TAC §535.65).
            ->and((float) $approval->max_hours_per_day)->toBe(12.0)
            ->and($approval->presence_percent)->toBe(100)
            ->and($approval->makeup_min_percent)->toBe(67)
            ->and($approval->makeup_days)->toBe(90)
            ->and($approval->checkin_minutes)->toBe(CourseApprovals::CHECKIN_MINUTES);

        $audit = AuditEvent::query()->where('action', 'course.approval_added')->sole();
        expect($audit->actor_id)->toBe($this->paul->id)->and($audit->organisation_id)->toBe($this->school->id);

        $this->get('/school/approvals')->assertInertia(fn (Assert $page) => $page
            ->component('school/approvals')
            ->where('approvals.0.number', 'TREC 0451')
            ->where('approvals.0.shown_status', 'approved')
            ->where('approvals.0.labels_course', true)
            ->where('defaults.TX.max_hours_per_day', 12));
    });

    it('adds the state to the course, and refuses duplicates and approvals without a number', function () {
        State::query()->firstOrCreate(['code' => 'OK'], ['name' => 'Oklahoma', 'status' => 'planned']);
        addApproval($this, ['state_code' => 'ok', 'number' => 'OREC 77']);
        expect($this->course->refresh()->states)->toBe(['OK', 'TX']);

        $this->post('/school/approvals', ['course_id' => $this->course->id, 'state_code' => 'OK', 'delivery' => 'self_paced', 'number' => 'OREC 78'])->assertSessionHasErrors('state_code');
        $this->post('/school/approvals', ['course_id' => $this->course->id, 'state_code' => 'TX', 'delivery' => 'ai_avatar', 'status' => 'approved'])->assertSessionHasErrors('number');
        $this->post('/school/approvals', ['course_id' => $this->course->id, 'state_code' => 'TX', 'delivery' => 'ai_avatar', 'number' => 'X', 'approved_on' => '2027-01-01', 'expires_on' => '2026-01-01'])->assertSessionHasErrors('expires_on');
        // Filed with the regulator and waiting: no number yet is fine.
        $this->post('/school/approvals', ['course_id' => $this->course->id, 'state_code' => 'TX', 'delivery' => 'ai_avatar', 'status' => 'pending'])->assertSessionHasNoErrors();
    });

    it('is the school\'s own: other schools and other roles get nothing', function () {
        $approval = addApproval($this);
        $other = Organisation::factory()->ofType(OrganisationType::School)->create();
        $admin = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $other)->create();

        $this->actingAs($admin)->get('/school/approvals')->assertInertia(fn (Assert $page) => $page->has('approvals', 0));
        $this->actingAs($admin)->patch("/school/approvals/{$approval->id}", ['number' => 'stolen'])->assertNotFound();
        $this->actingAs($admin)->post('/school/approvals', ['course_id' => $this->course->id, 'state_code' => 'TX', 'delivery' => 'classroom', 'number' => 'X'])->assertNotFound();

        $instructor = User::factory()->withRole(Role::Instructor, $this->school)->create();
        $this->actingAs($instructor)->get('/school/approvals')->assertForbidden();
        expect($approval->refresh()->number)->toBe('TREC 0451');
    });

    it('withdraws an approval and puts it back with Undo', function () {
        $approval = addApproval($this);

        $this->put("/school/approvals/{$approval->id}/status", ['status' => 'withdrawn'])->assertRedirect();
        expect($this->course->refresh()->load('approvals')->creditableIn('TX'))->toBeFalse();

        $this->put("/school/approvals/{$approval->id}/status", ['status' => 'approved'])->assertRedirect();
        expect($this->course->refresh()->load('approvals')->creditableIn('TX'))->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'course.approval_withdrawn')->count())->toBe(1);
    });
});

describe('no CE or qualifying label without an approval', function () {
    it('labels a course only with an approval in force for its state and delivery', function () {
        $candidate = User::factory()->withRole(Role::Candidate)->create(['target_state' => 'TX']);
        $credit = fn () => collect($this->actingAs($candidate)->getJson('/api/v1/learn/courses')->assertOk()->json('data'))->firstWhere('title', 'Principles of Real Estate II')['credit'];

        expect($credit())->toBeNull();

        $approval = addApproval($this, ['status' => 'pending', 'number' => null]);
        expect($credit())->toBeNull();

        $this->actingAs($this->paul)->patch("/school/approvals/{$approval->id}", ['status' => 'approved', 'number' => 'TREC 0451'])->assertSessionHasNoErrors();
        expect($credit())->toBe(['number' => 'TREC 0451', 'hours' => 30]);

        // Approved for self-paced delivery only: an AI avatar version needs its own approval.
        $this->course->forceFill(['delivery' => Delivery::AiAvatar])->save();
        expect($credit())->toBeNull();
        $this->course->forceFill(['delivery' => Delivery::SelfPaced])->save();

        // Past its expiry date: no label, on the web too.
        $this->travelTo(CarbonImmutable::parse('2028-09-01 09:00', 'America/Chicago'));
        expect($credit())->toBeNull();
        $this->actingAs($candidate)->get('/learn')->assertInertia(fn (Assert $page) => $page->where('courses.0.credit', null)->where('courses.0.type', 'qualifying'));
    });

    it('keeps a CE or qualifying course from going live without an approval in force', function () {
        $draft = Course::query()->create(['organisation_id' => $this->school->id, 'title' => 'Legal Update I', 'slug' => 'legal-1', 'type' => 'ce', 'states' => ['TX']]);
        $draft->approvals()->create(['state_code' => 'TX', 'delivery' => 'self_paced', 'number' => 'TREC CE 1', 'status' => 'approved', 'expires_on' => '2027-02-01']);

        expect($draft->load('approvals')->missingApprovals())->toBe(['TX']);
    });
});

describe('expiry', function () {
    it('reminds the school 60 and 30 days before, once each, and expires the label', function () {
        Notification::fake();
        $approval = addApproval($this, ['expires_on' => '2027-04-25']);
        $approvals = app(CourseApprovals::class);

        // 55 days left: the 60-day reminder.
        expect($approvals->check())->toBe(['reminded' => 1, 'expired' => 0])
            ->and($approvals->check())->toBe(['reminded' => 0, 'expired' => 0]);
        Notification::assertSentTo($this->paul, ApprovalExpiring::class, fn (ApprovalExpiring $n) => $n->daysLeft === 55);

        $this->travelTo(CarbonImmutable::parse('2027-03-27 09:00', 'America/Chicago'));
        expect($approvals->check())->toBe(['reminded' => 1, 'expired' => 0]);

        $this->travelTo(CarbonImmutable::parse('2027-04-26 09:00', 'America/Chicago'));
        expect($approvals->check())->toBe(['reminded' => 0, 'expired' => 1])
            ->and($approval->refresh()->expired_at)->not->toBeNull()
            ->and($approval->shownStatus())->toBe(CourseApproval::EXPIRED)
            ->and(AuditEvent::query()->where('action', 'course.approval_expired')->count())->toBe(1);
        Notification::assertSentToTimes($this->paul, ApprovalExpiring::class, 3);

        // Renewed with a new date: reminders start again, and the label comes back.
        $this->actingAs($this->paul)->patch("/school/approvals/{$approval->id}", ['expires_on' => '2029-04-25'])->assertSessionHasNoErrors();
        expect($approval->refresh()->expired_at)->toBeNull()
            ->and($approval->reminded_days)->toBeNull()
            ->and($this->course->refresh()->load('approvals')->creditableIn('TX'))->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'course.approval_renewed')->count())->toBe(1);
    });

    it('shows what needs attention on the school dashboard', function () {
        addApproval($this, ['expires_on' => '2027-03-26']);
        addApproval($this, ['delivery' => 'ai_avatar', 'status' => 'pending', 'number' => null, 'expires_on' => null]);

        $this->actingAs($this->paul)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('approvals.courses', 1)
            ->where('approvals.in_force', 1)
            ->where('approvals.attention.0.days_left', 25)
            ->where('approvals.attention.1.status', 'pending'));

        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('approvals', null));
    });
});

describe('seat time (screen 62)', function () {
    it('is a state rule super admins edit like any other', function () {
        $ops = User::factory()->superAdmin()->create();
        $texas = State::query()->findOrFail('TX');
        $rules = app(StateRules::class);

        expect($rules->inForce('TX', now())['courses.seat_time']->value)->toBe(['max_hours_per_day' => 12, 'presence_percent' => 100, 'makeup_min_percent' => 67, 'makeup_days' => 90]);

        expect(fn () => $rules->draft($texas, 'courses.seat_time', ['value' => ['max_hours_per_day' => 30], 'citation' => '22 TAC §535.65', 'effective_from' => '2027-06-01'], $ops))
            ->toThrow(ValidationException::class);
        $draft = $rules->draft($texas, 'courses.seat_time', ['value' => ['max_hours_per_day' => 10, 'presence_percent' => 100, 'makeup_min_percent' => 67, 'makeup_days' => 90], 'citation' => '22 TAC §535.65', 'effective_from' => '2027-06-01'], $ops);
        expect($draft->value['max_hours_per_day'])->toBe(10);
    });
});
