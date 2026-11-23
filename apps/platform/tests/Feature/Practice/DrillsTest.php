<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\TopicPlan;
use App\Models\User;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $school)->create([
        'name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01',
        'minutes_per_day' => 20, 'reminder_time' => '19:30', 'onboarded_at' => now(),
    ]);
});

/** A submitted test answering these questions right or wrong. @param array<int, bool> $right */
function drillHistory(User $user, array $right, int $daysAgo = 1): TestAttempt
{
    $questions = QuizQuestion::query()->whereIn('id', array_keys($right))->get()->keyBy('id');
    $answers = [];
    $byTopic = [];
    foreach ($right as $id => $ok) {
        $q = $questions[$id];
        $answers[$id] = $ok ? $q->correct_option : ($q->correct_option + 1) % 4;
        $byTopic[$q->topic->value] ??= ['correct' => 0, 'total' => 0];
        $byTopic[$q->topic->value]['total']++;
        $byTopic[$q->topic->value]['correct'] += $ok ? 1 : 0;
    }

    return TestAttempt::query()->create([
        'user_id' => $user->id, 'kind' => 'topic', 'mode' => 'practice', 'state_code' => 'TX', 'question_ids' => array_keys($right), 'answers' => $answers, 'total' => count($right),
        'started_at' => now()->subDays($daysAgo)->subHour(), 'submitted_at' => now()->subDays($daysAgo), 'by_topic' => $byTopic,
    ]);
}

/** @return list<int> */
function topicIds(string $topic, int $count, int $skip = 0): array
{
    return QuizQuestion::query()->where('topic', $topic)->whereNull('retired_at')->orderBy('id')->skip($skip)->take($count)->pluck('id')->all();
}

describe('the smart drill (screen 47, A9.2b)', function () {
    it('asks 15 questions from the three weakest topics, recent misses first in the mix, with why after each answer', function () {
        // Finance and math missed, agency, contracts and ownership strong, Texas rules middling.
        $finance = topicIds('finance', 10);
        $math = topicIds('math', 10);
        drillHistory($this->sofia, array_fill_keys($finance, false) + array_fill_keys($math, false) + array_fill_keys(topicIds('state_rules', 10), true)
            + array_fill_keys([...topicIds('agency', 10), ...topicIds('contracts', 10), ...topicIds('ownership', 10)], true));

        $hub = $this->actingAs($this->sofia)->getJson('/api/v1/practice/hub')->assertOk()->json('data');
        expect($hub['drill']['topics'])->toBe(['finance', 'math', 'state_rules'])->and($hub['drill']['misses'])->toBe(5);

        $attempt = TestAttempt::query()->findOrFail($this->postJson('/api/v1/practice/tests', ['kind' => 'drill'])->assertCreated()->assertJsonPath('data.mode', 'practice')->assertJsonCount(15, 'data.questions')->json('data.id'));
        $topics = QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get()->countBy(fn (QuizQuestion $q) => $q->topic->value);
        expect($topics->keys()->sort()->values()->all())->toBe(['finance', 'math', 'state_rules'])
            ->and(count(array_intersect($attempt->question_ids, [...$finance, ...$math])))->toBeGreaterThanOrEqual(5);

        $q = QuizQuestion::query()->findOrFail($attempt->question_ids[0]);
        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$q->id}", ['choice' => $q->correct_option])->assertOk()->assertJsonPath('data.feedback.correct', true);
        // The same drill carries on until it's done.
        $this->postJson('/api/v1/practice/tests', ['kind' => 'drill'])->assertOk()->assertJsonPath('data.id', $attempt->id);
    });

    it('gives a hint that strikes out a wrong option, never the answer, and only before answering', function () {
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'drill'])->json('data.id'));
        [$first, $second] = $attempt->question_ids;
        $question = QuizQuestion::query()->findOrFail($first);

        $hint = $this->postJson("/api/v1/practice/attempts/{$attempt->id}/questions/{$first}/hint")->assertOk()->json('data.eliminated');
        expect($hint)->not->toBe($question->correct_option)->toBeBetween(0, 3)
            ->and($this->postJson("/api/v1/practice/attempts/{$attempt->id}/questions/{$first}/hint")->json('data.eliminated'))->toBe($hint)
            ->and($attempt->fresh()?->hints)->toBe([$first]);

        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$second}", ['choice' => 0])->assertOk();
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/questions/{$second}/hint")->assertUnprocessable();

        $topic = TestAttempt::query()->findOrFail($this->postJson('/api/v1/practice/tests', ['kind' => 'topic', 'topic' => 'math'])->json('data.id'));
        $this->postJson("/api/v1/practice/attempts/{$topic->id}/questions/{$topic->question_ids[0]}/hint")->assertUnprocessable();
    });
});

describe('Fix a weak topic (screen 46, A9.2b)', function () {
    it('builds 4 to 6 steps from the candidate\'s own misses, walks them in order, and completes with a retest on new questions', function () {
        $missed = topicIds('finance', 8);
        drillHistory($this->sofia, array_fill_keys($missed, false));

        $plan = $this->actingAs($this->sofia)->postJson('/api/v1/practice/topic-plans', ['topic' => 'finance'])->assertCreated()
            ->assertJsonPath('data.topic', 'finance')->assertJsonPath('data.now', 0)->assertJsonPath('data.goal', 70)->assertJsonPath('data.weak', true)
            ->assertJsonCount(8, 'data.misses')->json('data');
        $kinds = array_column($plan['steps'], 'kind');

        expect(count($kinds))->toBeBetween(4, 6)->and($kinds[array_key_last($kinds)])->toBe('retest')
            ->and($kinds)->toContain('review')->toContain('practice')
            ->and($plan['steps'][0]['status'])->toBe('current')
            ->and($plan['minutes'])->toBe(array_sum(array_column($plan['steps'], 'minutes')));
        // Asking again returns the same plan.
        $this->postJson('/api/v1/practice/topic-plans', ['topic' => 'finance'])->assertOk()->assertJsonPath('data.id', $plan['id']);

        foreach ($plan['steps'] as $step) {
            $next = $this->postJson("/api/v1/practice/topic-plans/{$plan['id']}/continue")->assertOk()->assertJsonPath('data.kind', $step['kind'])->json('data');

            if ($next['attempt_id'] !== null) {
                $attempt = TestAttempt::query()->findOrFail($next['attempt_id']);
                expect($attempt->kind)->toBe($step['kind'] === 'retest' ? 'retest' : 'drill')->and($attempt->topic)->toBe('finance');
                if ($step['kind'] === 'retest') {
                    expect(array_intersect($attempt->question_ids, $missed))->toBe([]);
                }
                foreach (QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get() as $q) {
                    $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$q->id}", ['choice' => $q->correct_option]);
                }
                $this->postJson("/api/v1/practice/attempts/{$attempt->id}/submit")->assertOk();
            }
        }

        $done = $this->getJson("/api/v1/practice/topic-plans/{$plan['id']}")->assertOk()->assertJsonPath('data.completed', true)->json('data');
        expect(collect($done['steps'])->every(fn (array $s) => $s['status'] === 'done'))->toBeTrue()
            ->and($done['now'])->toBeGreaterThan(0)
            ->and($done['steps'][array_key_last($done['steps'])]['results_id'])->not->toBeNull();
        $this->postJson("/api/v1/practice/topic-plans/{$plan['id']}/continue")->assertUnprocessable();
    });

    it('opens the lesson behind a miss at the right second, and keeps plans to their owner', function () {
        $plan = TopicPlan::query()->create(['user_id' => $this->sofia->id, 'topic' => 'math', 'start_percent' => 40, 'steps' => [
            ['kind' => 'lesson', 'minutes' => 5, 'lesson_id' => 77, 'title' => 'Prorations', 'seconds' => 125, 'done_at' => null],
            ['kind' => 'practice', 'minutes' => 10, 'attempt_id' => null, 'done_at' => null],
            ['kind' => 'practice', 'minutes' => 10, 'attempt_id' => null, 'done_at' => null],
            ['kind' => 'retest', 'minutes' => 8, 'attempt_id' => null, 'done_at' => null],
        ]]);

        $this->actingAs($this->sofia)->postJson("/api/v1/practice/topic-plans/{$plan->id}/continue")->assertOk()
            ->assertJsonPath('data.lesson', ['id' => 77, 'seconds' => 125]);
        $this->post("/practice/fix/{$plan->id}/continue")->assertRedirect('/practice/attempts/'.TopicPlan::query()->findOrFail($plan->id)->steps[1]['attempt_id']);
        $this->get("/practice/fix/{$plan->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/fix')->where('plan.steps.0.status', 'done')->where('plan.steps.1.status', 'current'));

        $other = User::factory()->withRole(Role::Candidate)->create(['onboarded_at' => now()]);
        $this->actingAs($other)->getJson("/api/v1/practice/topic-plans/{$plan->id}")->assertNotFound();
    });
});

describe('the practice hub (screen 38)', function () {
    it('ranks what helps most: the diagnostic first, then plans under way, the drill and fixing the weakest', function () {
        $first = $this->actingAs($this->sofia)->getJson('/api/v1/practice/hub')->json('data.ranked');
        expect(array_column($first, 'mode'))->toBe(['diagnostic', 'drill', 'speed', 'math', 'readiness']);

        TestAttempt::query()->create(['user_id' => $this->sofia->id, 'kind' => 'diagnostic', 'mode' => 'exam', 'state_code' => 'TX', 'question_ids' => [], 'total' => 0,
            'started_at' => now()->subDay(), 'submitted_at' => now()->subDay(), 'by_topic' => ['finance' => ['correct' => 1, 'total' => 7], 'agency' => ['correct' => 9, 'total' => 9]]]);
        $ranked = $this->getJson('/api/v1/practice/hub')->json('data.ranked');
        expect(array_column($ranked, 'mode'))->toBe(['drill', 'fix', 'speed', 'math', 'readiness'])->and($ranked[1]['topic'])->toBe('finance');

        $this->postJson('/api/v1/practice/topic-plans', ['topic' => 'finance'])->assertCreated();
        $hub = $this->getJson('/api/v1/practice/hub')->json('data');
        expect(array_column($hub['ranked'], 'mode'))->toBe(['plan', 'drill', 'speed', 'math', 'readiness'])->and($hub['plans'][0])->toMatchArray(['topic' => 'finance', 'done' => 0]);

        $this->get('/practice')->assertInertia(fn (Assert $page) => $page->where('coach.ranked.0.mode', 'plan'));
    });
});
