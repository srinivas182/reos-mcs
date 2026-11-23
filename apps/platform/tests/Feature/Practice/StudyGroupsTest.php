<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\StudyGroup;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\GroupChallenge;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', StudyPlans::TIMEZONE));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $candidate = fn (string $name) => User::factory()->withRole(Role::Candidate, $this->school)->create([
        'name' => $name, 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01', 'onboarded_at' => now(),
    ]);
    $this->sofia = $candidate('Sofia Reyes');
    $this->marco = $candidate('Marco Diaz');
    $this->lena = $candidate('Lena Ortiz');
});

/** A test submitted this week with these answers right out of total. */
function sgWeek(User $user, int $correct, int $total): void
{
    TestAttempt::query()->create(['user_id' => $user->id, 'kind' => 'topic', 'mode' => 'practice', 'state_code' => 'TX', 'question_ids' => [], 'total' => $total,
        'started_at' => now()->subHour(), 'submitted_at' => now()->subMinutes(30), 'by_topic' => ['finance' => ['correct' => $correct, 'total' => $total]]]);
}

describe('study groups (screen 52, A9.3c)', function () {
    it('needs opting in from Profile; members join by invite link and see a weekly leaderboard with first names only', function () {
        $this->actingAs($this->sofia)->postJson('/api/v1/practice/groups', ['name' => 'Night owls'])->assertUnprocessable()->assertJsonValidationErrors('group');
        $this->putJson('/api/v1/practice/groups/opt-in', ['opt_in' => true])->assertOk()->assertJsonPath('data.opt_in', true);
        $group = $this->postJson('/api/v1/practice/groups', ['name' => 'Night owls'])->assertCreated()->json('data');
        expect($group)->toMatchArray(['name' => 'Night owls', 'members' => 1, 'max' => 20, 'mine' => true, 'school' => null])->and($group['link'])->toEndWith('/groups/'.$group['uuid']);

        // Marco hasn't opted in: he sees the group but can't join yet, and no leaderboard.
        $this->actingAs($this->marco)->getJson("/api/v1/practice/groups/{$group['uuid']}")->assertOk()->assertJsonPath('data.leaderboard', [])->assertJsonPath('data.mine', false);
        $this->postJson("/api/v1/practice/groups/{$group['uuid']}/join")->assertUnprocessable();
        $this->putJson('/api/v1/practice/groups/opt-in', ['opt_in' => true])->assertOk();
        $this->postJson("/api/v1/practice/groups/{$group['uuid']}/join")->assertOk()->assertJsonPath('data.members', 2);
        $this->postJson("/api/v1/practice/groups/{$group['uuid']}/join")->assertOk()->assertJsonPath('data.members', 2);

        sgWeek($this->marco, 18, 20);
        sgWeek($this->sofia, 7, 10);
        $this->travel(-8)->days();
        sgWeek($this->sofia, 50, 50); // last week: not counted
        $this->travel(8)->days();

        $board = $this->actingAs($this->sofia)->getJson('/api/v1/practice/groups')->json('data.groups.0.leaderboard');
        expect($board)->toBe([
            ['first_name' => 'Marco', 'questions' => 20, 'accuracy' => 90, 'mine' => false],
            ['first_name' => 'Sofia', 'questions' => 10, 'accuracy' => 70, 'mine' => true],
        ])->and(json_encode($board))->not->toContain('Reyes');

        // Turning study groups off leaves every group.
        $this->actingAs($this->marco)->putJson('/api/v1/practice/groups/opt-in', ['opt_in' => false])->assertOk()->assertJsonPath('data.groups', []);
        expect(StudyGroup::query()->sole()->members()->count())->toBe(1);
    });

    it('sends a member\'s challenge to the whole group, and stops at 20 members', function () {
        Notification::fake();
        foreach ([$this->sofia, $this->marco, $this->lena] as $u) {
            $u->forceFill(['study_groups_opt_in' => true])->save();
        }
        $uuid = $this->actingAs($this->sofia)->postJson('/api/v1/practice/groups', ['name' => 'Night owls'])->json('data.uuid');
        $this->actingAs($this->marco)->postJson("/api/v1/practice/groups/{$uuid}/join")->assertOk();

        $this->actingAs($this->lena)->postJson("/api/v1/practice/groups/{$uuid}/challenge")->assertUnprocessable();
        $made = $this->actingAs($this->sofia)->postJson("/api/v1/practice/groups/{$uuid}/challenge")->assertCreated()->json('data');
        Notification::assertSentTo($this->marco, GroupChallenge::class, fn (GroupChallenge $n): bool => $n->firstName === 'Sofia' && $n->group === 'Night owls' && $n->uuid === $made['uuid']);
        Notification::assertNotSentTo($this->sofia, GroupChallenge::class);
        // It's waiting in Marco's challenges until he accepts.
        expect(array_column($this->actingAs($this->marco)->getJson('/api/v1/practice/challenges')->json('data'), 'uuid'))->toBe([$made['uuid']]);

        $group = StudyGroup::query()->sole();
        $others = User::factory()->count(18)->withRole(Role::Candidate, $this->school)->create(['study_groups_opt_in' => true]);
        $group->members()->attach($others->pluck('id')->all(), ['joined_at' => now()]);
        $this->actingAs($this->lena)->postJson("/api/v1/practice/groups/{$uuid}/join")->assertUnprocessable()->assertJsonValidationErrors('group');
    });

    it('lets a school make a class group; the school sees how many joined, never scores', function () {
        $paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
        $this->actingAs($paul)->post('/school/groups', ['name' => 'January evening class'])->assertRedirect();
        $group = StudyGroup::query()->sole();
        expect($group->organisation_id)->toBe($this->school->id);

        $this->sofia->forceFill(['study_groups_opt_in' => true])->save();
        $this->actingAs($this->sofia)->get("/groups/{$group->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/group')->where('group.school', 'Texas Realty School')->where('group.mine', false));
        $this->postJson("/api/v1/practice/groups/{$group->uuid}/join")->assertOk();
        $this->get('/practice/challenges')->assertInertia(fn (Assert $page) => $page->where('opt_in', true)->where('groups.0.name', 'January evening class'));

        $this->actingAs($paul)->get('/school/students')->assertInertia(fn (Assert $page) => $page->where('groups.0', fn ($g) => $g['members'] === 1 && ! isset($g['leaderboard'])));

        app('auth')->forgetGuards();
        $this->get("/groups/{$group->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/group')->where('name', 'January evening class')->where('members', 1));
    });
});
