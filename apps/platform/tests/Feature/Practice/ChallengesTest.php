<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\Challenge;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\ChallengeFinished;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $candidate = static fn (string $name) => User::factory()->withRole(Role::Candidate, $school)->create([
        'name' => $name, 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01', 'onboarded_at' => now(),
    ]);
    $this->sofia = $candidate('Sofia Reyes');
    $this->marco = $candidate('Marco Diaz');
    $this->lena = $candidate('Lena Ortiz');
});

/** Answers every question of an attempt, right or wrong, then submits it. */
function chAnswerAll(object $test, User $user, int $attempt, bool $right): void
{
    $found = TestAttempt::query()->findOrFail($attempt);
    $options = QuizQuestion::query()->whereIn('id', $found->question_ids)->pluck('correct_option', 'id');
    foreach ($found->question_ids as $id) {
        $choice = $right ? (int) $options[$id] : ((int) $options[$id] + 1) % 4;
        $test->actingAs($user)->putJson("/api/v1/practice/attempts/{$attempt}/answers/{$id}", ['choice' => $choice, 'flagged' => false])->assertOk();
    }
    $test->postJson("/api/v1/practice/attempts/{$attempt}/submit")->assertOk();
}

describe('challenges (screen 52, A9.3b)', function () {
    it('sends 10 questions by link; whoever accepts answers the same 10, and both see the board by first name', function () {
        Notification::fake();
        $made = $this->actingAs($this->sofia)->postJson('/api/v1/practice/challenges')->assertCreated()->json('data');
        expect($made)->toMatchArray(['from' => 'Sofia', 'mine' => true, 'questions' => 10, 'expired' => false])
            ->and($made['link'])->toEndWith('/challenges/'.$made['uuid']);
        $challenge = Challenge::query()->where('uuid', $made['uuid'])->sole();

        // Someone who hasn't accepted sees who sent it, but not the board.
        $this->actingAs($this->marco)->getJson("/api/v1/practice/challenges/{$made['uuid']}")->assertOk()->assertJsonPath('data.entries', [])->assertJsonPath('data.attempt_id', null);
        $accepted = $this->postJson("/api/v1/practice/challenges/{$made['uuid']}/accept")->assertOk()->json('data');
        $again = $this->postJson("/api/v1/practice/challenges/{$made['uuid']}/accept")->assertOk()->json('data');
        expect($again['attempt_id'])->toBe($accepted['attempt_id']);
        $attempt = TestAttempt::query()->findOrFail($accepted['attempt_id']);
        expect($attempt->kind)->toBe(TestAttempt::CHALLENGE)->and($attempt->question_ids)->toBe($challenge->question_ids)->and($attempt->mode)->toBe('exam');

        // A hint costs 5 points here.
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/questions/{$attempt->question_ids[0]}/hint")->assertOk();
        chAnswerAll($this, $this->marco, $attempt->id, true);
        $this->getJson("/api/v1/practice/attempts/{$attempt->id}/results")->assertOk()->assertJsonPath('data.challenge', $made['uuid']);
        Notification::assertSentTo($this->sofia, ChallengeFinished::class, fn (ChallengeFinished $n): bool => $n->firstName === 'Marco' && $n->points === 95);

        chAnswerAll($this, $this->sofia, $made['attempt_id'], false);
        Notification::assertSentTo($this->marco, ChallengeFinished::class, fn (ChallengeFinished $n): bool => $n->firstName === 'Sofia' && $n->points === 0);

        $board = $this->actingAs($this->sofia)->getJson("/api/v1/practice/challenges/{$made['uuid']}")->json('data.entries');
        expect($board)->toBe([
            ['first_name' => 'Marco', 'correct' => 10, 'total' => 10, 'hints' => 1, 'points' => 95, 'mine' => false, 'finished' => true],
            ['first_name' => 'Sofia', 'correct' => 0, 'total' => 10, 'hints' => 0, 'points' => 0, 'mine' => true, 'finished' => true],
        ])->and(json_encode($board))->not->toContain('Reyes')->not->toContain('Diaz');
        expect(array_column($this->getJson('/api/v1/practice/challenges')->json('data'), 'uuid'))->toBe([$made['uuid']]);
    });

    it('opens the share link for anyone, first name only, and stops accepting after 7 days', function () {
        $uuid = $this->actingAs($this->sofia)->postJson('/api/v1/practice/challenges')->json('data.uuid');
        app('auth')->forgetGuards();

        $this->get("/challenges/{$uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/challenge')->where('from', 'Sofia')->where('questions', 10)->where('expired', false));
        $this->actingAs($this->lena)->get("/challenges/{$uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/challenge')->where('challenge.from', 'Sofia')->where('challenge.entries', []));
        $this->get('/practice/challenges')->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/challenges')->has('challenges', 0));

        $this->travel(8)->days();
        $this->postJson("/api/v1/practice/challenges/{$uuid}/accept")->assertUnprocessable()->assertJsonValidationErrors('challenge');
        $this->get('/challenges/'.fake()->uuid())->assertNotFound();
    });
});
