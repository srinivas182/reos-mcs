<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Enums\QuizTopic;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $school)->create([
        'name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01',
        'minutes_per_day' => 20, 'reminder_time' => '19:30', 'onboarded_at' => now(),
    ]);
});

/** A bank big enough for two full simulations: 200 national, 110 Texas. */
function simulationBank(int $national = 200, int $state = 110): void
{
    $topics = [QuizTopic::Agency, QuizTopic::Contracts, QuizTopic::Ownership, QuizTopic::Finance, QuizTopic::Math];
    $rows = [];

    for ($i = 0; $i < $national + $state; $i++) {
        $topic = $i < $national ? $topics[$i % 5] : QuizTopic::StateRules;
        $text = ['question' => "Question {$i}?", 'options' => ['A', 'B', 'C', 'D'], 'explanation' => "Because {$i}."];
        $rows[] = [
            'state_code' => 'TX', 'topic' => $topic->value, 'content' => json_encode(['en' => $text, 'es' => $text]), 'correct_option' => $i % 4,
            'source' => 'Sample source', 'is_sample' => true, 'portion' => $i < $national ? 'national' : 'state', 'in_free_quiz' => false, 'in_diagnostic' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    foreach (array_chunk($rows, 100) as $chunk) {
        QuizQuestion::query()->insert($chunk);
    }
}

/** @param  list<int>  $ids */
function answerAll(object $test, TestAttempt $attempt, array $ids, bool $right): void
{
    foreach (QuizQuestion::query()->whereIn('id', $ids)->get() as $question) {
        $test->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$question->id}", ['choice' => $right ? $question->correct_option : ($question->correct_option + 1) % 4])->assertOk();
    }
}

describe('the full simulation in the real Pearson VUE format (A9.1, decision 1)', function () {
    it('runs National then Texas, each on its own clock, sends only the portion being taken, and scores each against its pass mark', function () {
        simulationBank();
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'simulation'])->assertCreated()
            ->assertJsonPath('data.section', ['index' => 1, 'of' => 2, 'portion' => 'national', 'minutes' => 150, 'questions' => 85])
            ->assertJsonCount(85, 'data.questions')->assertJsonPath('data.seconds_left', 150 * 60)->json('data.id'));

        expect($attempt->total)->toBe(135)->and($attempt->unscored)->toHaveCount(15)->and($attempt->mode)->toBe('exam');
        $national = array_slice($attempt->question_ids, 0, 85);
        $texas = array_slice($attempt->question_ids, 85);

        // A Texas question can't be answered during the National portion.
        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$texas[0]}", ['choice' => 0])->assertUnprocessable();
        answerAll($this, $attempt, $national, true);

        // National's time runs out: Texas starts its own 90 minutes when she's back.
        $this->travel(160)->minutes();
        $this->getJson("/api/v1/practice/attempts/{$attempt->id}")->assertOk()
            ->assertJsonPath('data.section.portion', 'state')->assertJsonPath('data.seconds_left', 90 * 60)->assertJsonCount(50, 'data.questions');
        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$national[0]}", ['choice' => 0])->assertUnprocessable();

        answerAll($this, $attempt, array_slice($texas, 0, 20), true);
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/finish")->assertOk()->assertJsonPath('data.submitted', true);

        $results = $this->getJson("/api/v1/practice/attempts/{$attempt->id}/results")->assertOk()->json('data');
        [$n, $t] = $results['portions'];
        expect($n)->toMatchArray(['portion' => 'national', 'correct' => 80, 'total' => 80, 'pass' => 56, 'passed' => true, 'pace' => 106])
            ->and($t['portion'])->toBe('state')->and($t['total'])->toBe(40)->and($t['pass'])->toBe(28)->and($t['passed'])->toBeFalse()
            ->and($results['passed'])->toBeFalse()
            ->and($results['total'])->toBe(120)
            ->and(collect($results['review'])->where('scored', false))->toHaveCount(15)
            ->and($results['timing']['pace'])->toBeGreaterThan(100);
    });

    it('draws questions she hasn\'t seen first, so a second simulation is new', function () {
        simulationBank();
        $first = $this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'national'])->json('data.id');
        $this->postJson("/api/v1/practice/attempts/{$first}/finish")->assertOk();
        $second = $this->postJson('/api/v1/practice/tests', ['kind' => 'national'])->json('data.id');

        $a = TestAttempt::query()->findOrFail($first)->question_ids;
        $b = TestAttempt::query()->findOrFail($second)->question_ids;
        expect(array_intersect($a, $b))->toBe([])->and($b)->toHaveCount(85);
    });

    it('shows each portion test with its format on the hub', function () {
        simulationBank();
        $hub = collect($this->actingAs($this->sofia)->getJson('/api/v1/practice/tests')->assertOk()->json('data'))->keyBy('kind');

        expect($hub['national'])->toMatchArray(['questions' => 85, 'minutes' => 150])
            ->and($hub['state'])->toMatchArray(['questions' => 50, 'minutes' => 90])
            ->and($hub['simulation'])->toMatchArray(['questions' => 135, 'minutes' => 240]);
    });

    it('scores a short bank fairly: everything scored, the pass mark scaled', function () {
        // A deployment that retired the starter bank: only the diagnostic's own Texas questions are left.
        QuizQuestion::query()->where('key', 'like', 'starter-tx:%')->update(['retired_at' => now()]);
        $available = QuizQuestion::query()->whereNull('retired_at')->get()->filter(fn (QuizQuestion $q) => $q->topic === QuizTopic::StateRules || $q->portion === 'state')->count();
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'state'])->assertCreated()->json('data.id'));

        expect($available)->toBeLessThan(50)->toBeGreaterThan(4)
            ->and($attempt->unscored)->toBe([])
            ->and($attempt->sections[0])->toMatchArray(['count' => $available, 'scored' => $available, 'pass' => (int) ceil(28 * $available / 40)]);
    });
});

describe('Exam mode and Practice mode (screen 40)', function () {
    it('gives no feedback and no pause in exam mode', function () {
        simulationBank();
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'state', 'mode' => 'exam'])->json('data.id'));
        $question = QuizQuestion::query()->findOrFail($attempt->question_ids[0]);

        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$question->id}", ['choice' => $question->correct_option])->assertOk()->assertJsonPath('data.feedback', null);
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/pause", ['paused' => true])->assertUnprocessable();
    });

    it('shows why and lets the clock stop in practice mode, without losing time', function () {
        simulationBank();
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'state', 'mode' => 'practice'])->json('data.id'));
        $question = QuizQuestion::query()->findOrFail($attempt->question_ids[0]);

        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$question->id}", ['choice' => $question->correct_option])->assertOk()->assertJsonPath('data.feedback.correct', true);

        $this->travel(10)->minutes();
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/pause", ['paused' => true])->assertOk()->assertJsonPath('data.paused', true)->assertJsonPath('data.seconds_left', 80 * 60);
        $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$attempt->question_ids[1]}", ['choice' => 0])->assertUnprocessable();

        // Two hours away: nothing ran out.
        $this->travel(120)->minutes();
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/pause", ['paused' => false])->assertOk()
            ->assertJsonPath('data.submitted', false)->assertJsonPath('data.seconds_left', 80 * 60);
    });
});

describe('answer review (screen 45)', function () {
    it('offers a similar question on the same topic, not from the test', function () {
        simulationBank();
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'state'])->json('data.id'));
        $id = $attempt->question_ids[0];

        $this->getJson("/api/v1/practice/attempts/{$attempt->id}/similar/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/finish")->assertOk();
        $similar = $this->getJson("/api/v1/practice/attempts/{$attempt->id}/similar/{$id}")->assertOk()->json('data');

        expect($similar['topic'])->toBe('state_rules')->and($attempt->question_ids)->not->toContain($similar['id']);
    });

    it('keeps another candidate out', function () {
        simulationBank();
        $attempt = $this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'national'])->json('data.id');
        $other = User::factory()->withRole(Role::Candidate)->create(['onboarded_at' => now()]);

        $this->actingAs($other)->postJson("/api/v1/practice/attempts/{$attempt}/finish")->assertNotFound();
        $this->actingAs($other)->postJson("/api/v1/practice/attempts/{$attempt}/pause", ['paused' => true])->assertNotFound();
    });
});
