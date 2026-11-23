<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Enums\QuizTopic;
use App\Models\AuditEvent;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\StarterBank;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $this->school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->paul = User::factory()->withTwoFactor()->withRole(Role::SchoolAdmin, $this->school)->create(['name' => 'Paul Diaz']);
    $this->rachel = User::factory()->withRole(Role::Instructor, $this->school)->create(['name' => 'Dr. Rachel Hughes']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $this->school)->create([
        'name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01',
        'minutes_per_day' => 20, 'reminder_time' => '19:30', 'onboarded_at' => now(),
    ]);
});

function starter(): Builder
{
    return QuizQuestion::query()->where('key', 'like', StarterBank::PREFIX.'%');
}

describe('the Texas starter bank (A9.1, decision 2)', function () {
    it('loads 300 questions as samples: 200 national by the outline, 100 Texas, each complete in English and Spanish', function () {
        $questions = starter()->get();
        $national = $questions->where('portion', 'national');

        expect($questions)->toHaveCount(300)
            ->and($national)->toHaveCount(200)
            ->and($questions->where('portion', 'state'))->toHaveCount(100)
            ->and($national->countBy(fn (QuizQuestion $q) => $q->topic->value)->all())->toEqual(['agency' => 45, 'contracts' => 45, 'ownership' => 40, 'finance' => 35, 'math' => 35])
            ->and($questions->every(fn (QuizQuestion $q) => $q->is_sample && $q->state_code === 'TX' && $q->organisation_id === null && ! $q->in_diagnostic && $q->source !== ''))->toBeTrue();

        foreach ($questions as $q) {
            foreach (['en', 'es'] as $l) {
                expect($q->content[$l]['options'])->toHaveCount(4)
                    ->and($q->content[$l]['question'])->not->toBe('')
                    ->and($q->content[$l]['explanation'])->not->toBe('');
            }
            expect($q->correct_option)->toBeBetween(0, 3);
        }
    });

    it('is enough for a full simulation in the real format, with pretest items, and a second one with new questions', function () {
        $first = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'simulation'])->assertCreated()->json('data.id'));
        $this->postJson("/api/v1/practice/attempts/{$first->id}/finish")->assertOk();
        $this->postJson("/api/v1/practice/attempts/{$first->id}/finish")->assertOk();
        $second = TestAttempt::query()->findOrFail($this->postJson('/api/v1/practice/tests', ['kind' => 'national'])->json('data.id'));

        expect($first->total)->toBe(135)->and($first->unscored)->toHaveCount(15)
            ->and(array_intersect(array_slice($first->question_ids, 0, 85), $second->question_ids))->toBe([]);
    });

    it('never overwrites a question already loaded, and adds only what is missing', function () {
        $question = starter()->orderBy('id')->firstOrFail();
        $question->forceFill(['content' => ['en' => ['question' => 'Edited by the school?', 'options' => ['A', 'B', 'C', 'D'], 'explanation' => 'x'], 'es' => $question->content['es']]])->save();
        starter()->where('id', '!=', $question->id)->limit(5)->delete();

        expect(StarterBank::load())->toBe(5)
            ->and(starter()->count())->toBe(300)
            ->and($question->fresh()?->content['en']['question'])->toBe('Edited by the school?');
    });
});

describe('the Starter bank tab (screen 45)', function () {
    it('shows a school admin the questions to review by topic, and keeps it from instructors', function () {
        $this->actingAs($this->paul)->get('/question-bank?tab=starter&topic=state_rules')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('builder/questions')->where('tab', 'starter')->where('counts.starter', 300)
                ->where('starter.topic', 'state_rules')->where('starter.counts.topics.math', 35)->has('starter.questions', 100)
                ->where('starter.questions.0.portion', 'state')->has('starter.questions.0.es.options', 4));

        $this->actingAs($this->rachel)->get('/question-bank?tab=starter')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'to_review')->where('starter', null)->missing('counts.starter'));
        $this->actingAs($this->rachel)->post('/question-bank/starter', ['ids' => [1], 'decision' => 'approve'])->assertForbidden();
    });

    it('approves (it then counts in topic quizzes too), retires (it leaves tests), and undoes either', function () {
        [$keep, $drop] = starter()->where('topic', QuizTopic::StateRules->value)->orderBy('id')->limit(2)->pluck('id')->all();

        $this->actingAs($this->paul)->post('/question-bank/starter', ['ids' => [$keep], 'decision' => 'approve'])->assertRedirect();
        $this->actingAs($this->paul)->post('/question-bank/starter', ['ids' => [$drop], 'decision' => 'retire'])->assertRedirect();

        expect(QuizQuestion::query()->findOrFail($keep)->is_sample)->toBeFalse()
            ->and(QuizQuestion::query()->findOrFail($drop)->retired_at)->not->toBeNull()
            ->and(AuditEvent::query()->where('action', 'starter_question.retire')->where('organisation_id', $this->school->id)->exists())->toBeTrue();

        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'state'])->json('data.id'));
        expect($attempt->question_ids)->not->toContain($drop);

        $this->actingAs($this->paul)->post('/question-bank/starter', ['ids' => [$keep, $drop], 'decision' => 'restore'])->assertRedirect();
        expect(starter()->whereIn('id', [$keep, $drop])->where('is_sample', true)->whereNull('retired_at')->count())->toBe(2);
    });

    it('approves every question shown at once, and ignores ids outside the starter bank', function () {
        $math = starter()->where('topic', 'math')->pluck('id')->all();
        $other = QuizQuestion::query()->where('in_diagnostic', true)->value('id');

        $this->actingAs($this->paul)->post('/question-bank/starter', ['ids' => [...$math, $other], 'decision' => 'approve'])->assertRedirect();

        expect(starter()->where('topic', 'math')->where('is_sample', false)->count())->toBe(35)
            ->and(AuditEvent::query()->where('action', 'starter_question.approve')->sole()->properties['ids'])->toHaveCount(35);
    });

    it('edits a starter question in both languages and keeps it to review', function () {
        $question = starter()->where('topic', 'math')->orderBy('id')->firstOrFail();
        $en = ['prompt' => 'A home sells for $300,000 at a 6% commission. What is the total commission?', 'options' => ['$18,000', '$1,800', '$16,000', '$180,000'], 'explanation' => '$300,000 × 0.06 = $18,000.'];
        $es = ['prompt' => 'Una casa se vende en $300,000 con una comisión del 6%. ¿Cuál es la comisión total?', 'options' => ['$18,000', '$1,800', '$16,000', '$180,000'], 'explanation' => '$300,000 × 0.06 = $18,000.'];

        $this->actingAs($this->paul)->put("/question-bank/starter/{$question->id}", ['en' => $en, 'es' => $es, 'correct' => 0, 'topic' => 'math', 'difficulty' => 'easy', 'portion' => 'national'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $question->refresh();

        expect($question->in('es')['question'])->toStartWith('Una casa se vende en $300,000')->and($question->difficulty)->toBe('easy')->and($question->is_sample)->toBeTrue();

        $this->actingAs($this->paul)->put("/question-bank/starter/{$question->id}", ['en' => $en, 'es' => $es, 'correct' => 4, 'topic' => 'math', 'difficulty' => 'easy', 'portion' => 'national'])->assertSessionHasErrors('correct');
        $diagnostic = QuizQuestion::query()->where('in_diagnostic', true)->firstOrFail();
        $this->actingAs($this->paul)->put("/question-bank/starter/{$diagnostic->id}", ['en' => $en, 'es' => $es, 'correct' => 0, 'topic' => 'math', 'difficulty' => 'easy', 'portion' => 'national'])->assertNotFound();
    });
});
