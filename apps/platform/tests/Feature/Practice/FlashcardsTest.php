<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\Flashcard;
use App\Models\LessonProgress;
use App\Models\LessonVersion;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\ReviewItem;
use App\Models\SpeedRound;
use App\Models\TestAttempt;
use App\Models\TopicPlan;
use App\Models\User;
use App\Practice\Flashcards\Fsrs;
use App\Practice\Readiness;
use App\Practice\StudyPlans;
use App\Services\Content\Courses;
use App\Services\Content\Lessons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00', StudyPlans::TIMEZONE));
    $school = Organisation::factory()->ofType(OrganisationType::School)->create(['name' => 'Texas Realty School']);
    $this->sofia = User::factory()->withRole(Role::Candidate, $school)->create([
        'name' => 'Sofia Reyes', 'onboarding_path' => OnboardingPath::BecomeAgent->value, 'exam_month' => '2027-03-01',
        'minutes_per_day' => 20, 'reminder_time' => '19:30', 'onboarded_at' => now(),
    ]);
});

/** A rating sequence's schedule, day by day: [rating, days after the last review]. */
function fsrsRun(array $steps): array
{
    $card = ['stability' => null, 'difficulty' => null, 'reps' => 0, 'lapses' => 0, 'last_reviewed_at' => null];
    $at = CarbonImmutable::parse('2027-01-01 10:00');
    $out = [];

    foreach ($steps as [$rating, $after]) {
        $at = $at->addDays($after);
        $card = Fsrs::review($card, $rating, $at);
        $out[] = $card;
    }

    return $out;
}

describe('FSRS scheduling (A9.2d done-when)', function () {
    it('schedules a new card by its first rating with the FSRS-5 defaults', function () {
        [$again] = fsrsRun([[Fsrs::AGAIN, 0]]);
        [$hard] = fsrsRun([[Fsrs::HARD, 0]]);
        [$good] = fsrsRun([[Fsrs::GOOD, 0]]);

        expect($again['stability'])->toBe(0.4026)->and($again['due_at']->toDateTimeString())->toBe('2027-01-01 10:10:00')->and($again['interval_days'])->toBe(0)
            ->and($hard['stability'])->toBe(1.1839)->and($hard['interval_days'])->toBe(1)
            ->and($good['stability'])->toBe(3.173)->and($good['interval_days'])->toBe(3)
            ->and($good['difficulty'])->toBe(round(7.1949 - exp(0.5345 * 2) + 1, 4));
    });

    it('grows the interval with each Got it on time, and shrinks it after a lapse', function () {
        $run = fsrsRun([[Fsrs::GOOD, 0], [Fsrs::GOOD, 3], [Fsrs::GOOD, 10], [Fsrs::AGAIN, 30], [Fsrs::GOOD, 0]]);
        $days = array_column($run, 'interval_days');

        expect($days[1])->toBeGreaterThan($days[0])->and($days[2])->toBeGreaterThan($days[1])
            ->and($run[3]['interval_days'])->toBe(0)->and($run[3]['lapses'])->toBe(1)
            ->and($run[3]['stability'])->toBeLessThan($run[2]['stability'])
            ->and($run[3]['difficulty'])->toBeGreaterThan($run[2]['difficulty'])
            ->and($run[4]['interval_days'])->toBeLessThan($days[2]);
        // Recall falls to 90% at the interval.
        expect(round(Fsrs::retrievability($run[2]['stability'], $run[2]['stability']), 3))->toBe(0.9);
    });
});

describe('the deck (screen 31)', function () {
    it('adds the course\'s approved flashcards for lessons started, in the candidate\'s language', function () {
        $ops = User::factory()->superAdmin()->create();
        $course = app(Courses::class)->create(['title' => 'Principles of Real Estate I', 'type' => 'qualifying', 'states' => ['TX']], $ops);
        $lesson = app(Lessons::class)->create($course, ['title' => 'Agency basics'], $ops);
        $other = app(Lessons::class)->create($course, ['title' => 'Disclosures'], $ops);
        foreach ([$lesson, $other] as $l) {
            $version = LessonVersion::query()->create(['lesson_id' => $l->id, 'version' => 1, 'status' => 'ready', 'sources' => [], 'presenter' => 'stock', 'vendor' => 'simulated']);
            ReviewItem::query()->create(['lesson_id' => $l->id, 'lesson_version_id' => $version->id, 'kind' => 'flashcard', 'topic' => 'agency', 'status' => ReviewItem::APPROVED,
                'content' => ['en' => ['front' => "Who does {$l->title} cover?", 'back' => 'The client.'], 'es' => ['front' => "¿A quién cubre {$l->title}?", 'back' => 'Al cliente.']]]);
        }
        ReviewItem::query()->where('lesson_id', $other->id)->update(['status' => ReviewItem::TO_REVIEW]);
        LessonProgress::query()->create(['user_id' => $this->sofia->id, 'lesson_id' => $lesson->id, 'counted_seconds' => 60, 'required_seconds' => 600, 'position' => 60]);
        LessonProgress::query()->create(['user_id' => $this->sofia->id, 'lesson_id' => $other->id, 'counted_seconds' => 60, 'required_seconds' => 600, 'position' => 60]);

        $this->actingAs($this->sofia)->getJson('/api/v1/practice/flashcards')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.new_left', 1)->assertJsonPath('data.cards.0.front', 'Who does Agency basics cover?')->assertJsonPath('data.cards.0.source', 'course');
        $this->sofia->forceFill(['locale' => 'es'])->save();
        $this->getJson('/api/v1/practice/flashcards')->assertJsonPath('data.cards.0.back', 'Al cliente.')->assertJsonPath('data.total', 1);
    });

    it('saves a card from answer review once per question, and from Ask', function () {
        $question = QuizQuestion::query()->where('topic', 'finance')->firstOrFail();
        $body = ['source' => 'review', 'front' => $question->in('en')['question'], 'back' => 'The right answer, and why.', 'question_id' => $question->id];

        $first = $this->actingAs($this->sofia)->postJson('/api/v1/practice/flashcards', $body)->assertCreated()->assertJsonPath('data.topic', 'finance')->assertJsonPath('data.new', true)->json('data.id');
        $this->postJson('/api/v1/practice/flashcards', $body)->assertCreated()->assertJsonPath('data.id', $first);
        $this->postJson('/api/v1/practice/flashcards', ['source' => 'ask', 'front' => 'What does IABS stand for?', 'back' => 'Information About Brokerage Services.'])->assertCreated();
        $this->postJson('/api/v1/practice/flashcards', ['source' => 'speed', 'front' => 'x?', 'back' => 'y'])->assertUnprocessable();

        expect(Flashcard::query()->where('user_id', $this->sofia->id)->count())->toBe(2);
    });

    it('syncs the phone\'s offline reviews in order, each once, with the same schedule as the server', function () {
        $card = Flashcard::query()->create(['user_id' => $this->sofia->id, 'source' => 'ask', 'content' => ['en' => ['front' => 'Q', 'back' => 'A']], 'due_at' => now()]);
        $first = ['card_id' => $card->id, 'rating' => Fsrs::GOOD, 'reviewed_at' => now()->subDays(4)->toIso8601String(), 'client_id' => (string) Str::uuid()];
        $second = ['card_id' => $card->id, 'rating' => Fsrs::GOOD, 'reviewed_at' => now()->subDay()->toIso8601String(), 'client_id' => (string) Str::uuid()];

        // Sent out of order, and the queue sent twice: each counts once, oldest first.
        $this->actingAs($this->sofia)->postJson('/api/v1/practice/flashcards/reviews', ['reviews' => [$second, $first]])->assertOk()->assertJsonPath('data.applied', 2);
        $this->postJson('/api/v1/practice/flashcards/reviews', ['reviews' => [$first, $second]])->assertOk()->assertJsonPath('data.applied', 0);

        $expected = fsrsRun([[Fsrs::GOOD, 0], [Fsrs::GOOD, 3]])[1];
        $card->refresh();
        expect($card->reps)->toBe(2)->and($card->stability)->toEqualWithDelta($expected['stability'], 0.0001)
            ->and($card->due_at?->toDateString())->toBe(CarbonImmutable::parse($second['reviewed_at'])->addDays($expected['interval_days'])->toDateString());

        $someone = User::factory()->withRole(Role::Candidate)->create(['onboarded_at' => now()]);
        $this->actingAs($someone)->postJson('/api/v1/practice/flashcards/reviews', ['reviews' => [['card_id' => $card->id, 'rating' => 1, 'reviewed_at' => now()->toIso8601String(), 'client_id' => (string) Str::uuid()]]])
            ->assertOk()->assertJsonPath('data.applied', 0);
    });

    it('gives up to 15 new cards a day', function () {
        foreach (range(1, 20) as $i) {
            Flashcard::query()->create(['user_id' => $this->sofia->id, 'source' => 'ask', 'content' => ['en' => ['front' => "Q{$i}", 'back' => 'A']], 'due_at' => now()]);
        }
        $cards = Flashcard::query()->orderBy('id')->limit(5)->pluck('id');
        $this->actingAs($this->sofia)->postJson('/api/v1/practice/flashcards/reviews', ['reviews' => $cards->map(fn ($id) => ['card_id' => $id, 'rating' => 3, 'reviewed_at' => now()->toIso8601String(), 'client_id' => (string) Str::uuid()])->all()])->assertOk();

        $this->getJson('/api/v1/practice/flashcards')->assertJsonPath('data.new_left', 10)->assertJsonPath('data.due', 0)->assertJsonPath('data.total', 20);
        $this->travel(4)->days();
        $this->getJson('/api/v1/practice/flashcards')->assertJsonPath('data.new_left', 15)->assertJsonPath('data.due', 5);
        $this->get('/practice/flashcards')->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/flashcards')->where('deck.due', 5));
    });
});

describe('the speed round (screen 50)', function () {
    it('scores the taps on the server, keeps the best, turns misses into flashcards, and counts lightly towards readiness', function () {
        $round = $this->actingAs($this->sofia)->postJson('/api/v1/practice/speed')->assertCreated()->assertJsonPath('data.seconds', 60)->assertJsonPath('data.best', null)
            ->assertJsonCount(40, 'data.statements')->json('data');
        $stored = SpeedRound::query()->findOrFail($round['id']);
        $taps = [];
        foreach (array_slice($stored->statements, 0, 12) as $i => $s) {
            // Ten right, two wrong.
            $taps[] = ['id' => $s['id'], 'true' => $i < 10 ? $s['truth'] : ! $s['truth']];
        }

        $this->postJson("/api/v1/practice/speed/{$round['id']}/finish", ['taps' => $taps])->assertOk()
            ->assertJson(['data' => ['score' => 10, 'answered' => 12, 'best' => 10, 'record' => true, 'misses' => 2]]);
        $this->postJson("/api/v1/practice/speed/{$round['id']}/finish", ['taps' => $taps])->assertUnprocessable();
        expect(Flashcard::query()->where('source', 'speed')->count())->toBe(2);

        $next = $this->postJson('/api/v1/practice/speed')->assertJsonPath('data.best', 10)->json('data.id');
        $this->postJson("/api/v1/practice/speed/{$next}/finish", ['taps' => []])->assertOk()->assertJsonPath('data.record', false)->assertJsonPath('data.best', 10);

        // A test answer counts four times a speed-round answer.
        $topic = $stored->statements[0]['topic'];
        $score = collect(array_slice($stored->statements, 0, 12))->where('topic', $topic)->count();
        expect(app(Readiness::class)->of($this->sofia)['answers'][$topic])->toBe(0)
            ->and(app(Readiness::class)->of($this->sofia)['topics'][$topic])->not->toBeNull()
            ->and($score)->toBeGreaterThan(0);

        $this->get('/practice/speed')->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/speed')->where('best', 10));
    });
});

describe('flashcards in fix plans and the hub', function () {
    it('adds a flashcard step to a fix plan when the topic has cards, and ranks cards due', function () {
        Flashcard::query()->create(['user_id' => $this->sofia->id, 'source' => 'review', 'topic' => 'finance', 'content' => ['en' => ['front' => 'Q', 'back' => 'A']],
            'reps' => 2, 'stability' => 3, 'difficulty' => 5, 'last_reviewed_at' => now()->subDays(5), 'due_at' => now()->subDay()]);
        TestAttempt::query()->create(['user_id' => $this->sofia->id, 'kind' => 'diagnostic', 'mode' => 'exam', 'state_code' => 'TX', 'question_ids' => [], 'total' => 0,
            'started_at' => now()->subDay(), 'submitted_at' => now()->subDay(), 'by_topic' => ['finance' => ['correct' => 1, 'total' => 7]]]);

        $plan = $this->actingAs($this->sofia)->postJson('/api/v1/practice/topic-plans', ['topic' => 'finance'])->assertCreated()->json('data');
        $kinds = array_column($plan['steps'], 'kind');
        expect($kinds[count($kinds) - 2])->toBe('flashcards');

        $hub = collect($this->getJson('/api/v1/practice/hub')->json('data.ranked'))->keyBy('mode');
        expect($hub['flashcards'])->toMatchArray(['why' => 'due', 'count' => 1])->and($hub->keys()->all())->toContain('speed');

        // Continue on the web goes to the topic's flashcards.
        $model = TopicPlan::query()->findOrFail($plan['id']);
        $steps = $model->steps;
        foreach ($steps as $n => $s) {
            $steps[$n]['done_at'] = $s['kind'] === 'flashcards' || $s['kind'] === 'retest' ? null : now()->toIso8601String();
        }
        $model->forceFill(['steps' => $steps])->save();
        $this->post("/practice/fix/{$plan['id']}/continue")->assertRedirect('/practice/flashcards?topic=finance');
    });
});
