<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\MathSolve;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Practice\MathLab\MathLab;
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

/**
 * The numbers in a problem's wording, in order: "$425,000", "6%", "120".
 *
 * @return list<float>
 */
function mathNumbers(string $prompt): array
{
    preg_match_all('/\$?(\d[\d,]*(?:\.\d+)?)%?/', $prompt, $m);

    return array_map(static fn (string $n): float => (float) str_replace(',', '', $n), $m[1]);
}

/** @return list<float> */
function mathAnswers(string $kind, int $seed): array
{
    return array_map(static fn ($s): float => $s->answer, app(MathLab::class)->problem($kind, $seed)->steps);
}

describe('math lab generators against worked answers (A9.2c done-when)', function () {
    it('works every kind out the way the exam does, for many seeds', function () {
        $lab = app(MathLab::class);

        foreach (range(1, 60) as $seed) {
            $seed *= 7919;

            // Proration: daily tax, Jan 1 through closing inclusive, the seller's share.
            $p = $lab->problem('proration', $seed);
            [$tax] = mathNumbers($p->prompt);
            preg_match('/closes on (\w+ \d+)/', $p->prompt, $date);
            $days = CarbonImmutable::parse($date[1].' 2027')->dayOfYear;
            expect(mathAnswers('proration', $seed))->toEqualWithDelta([round($tax / 365, 2), $days, round($tax / 365 * $days, 2)], 0.01);

            // Commission: total, the listing brokerage's side, the agent's split.
            [$price, $rate, $side, $split] = mathNumbers($lab->problem('commission', $seed)->prompt);
            $total = $price * $rate / 100;
            expect(mathAnswers('commission', $seed))->toEqualWithDelta([$total, $total * $side / 100, $total * $side / 100 * $split / 100], 0.01);

            // Loan-to-value: the lower of price and appraisal, the loan, the down payment.
            [$price, $appraisal, $ltv] = mathNumbers($lab->problem('ltv', $seed)->prompt);
            $loan = min($price, $appraisal) * $ltv / 100;
            expect(mathAnswers('ltv', $seed))->toEqualWithDelta([min($price, $appraisal), $loan, $price - $loan], 0.01);

            // Area: square feet, acres (43,560 sq ft), the price per acre.
            [$front, $depth, $perAcre] = mathNumbers($lab->problem('area', $seed)->prompt);
            expect(mathAnswers('area', $seed))->toEqualWithDelta([$front * $depth, round($front * $depth / 43560, 2), $front * $depth / 43560 * $perAcre], 0.01);

            // Capitalization: effective gross, NOI, value = NOI / rate.
            [$gross, $vacancy, $expenses, $cap] = mathNumbers($lab->problem('cap', $seed)->prompt);
            $noi = $gross * (1 - $vacancy / 100) - $expenses;
            expect(mathAnswers('cap', $seed))->toEqualWithDelta([$gross * (1 - $vacancy / 100), $noi, $noi / ($cap / 100)], 0.01);

            // Net to seller: commission, total selling costs, the net after the payoff.
            [$price, $rate, $costs, $payoff] = mathNumbers($lab->problem('net', $seed)->prompt);
            expect(mathAnswers('net', $seed))->toEqualWithDelta([$price * $rate / 100, $price * $rate / 100 + $costs, $price - $price * $rate / 100 - $costs - $payoff], 0.01);

            // Points and interest: the loan, the points (1% each), a month's interest.
            [$price, $down, $points, $rate] = mathNumbers($lab->problem('points', $seed)->prompt);
            $loan = $price * (1 - $down / 100);
            expect(mathAnswers('points', $seed))->toEqualWithDelta([$loan, $loan * $points / 100, $loan * $rate / 100 / 12], 0.01);
        }
    });

    it('gives the same problem for the same seed and new numbers for a new one', function () {
        $lab = app(MathLab::class);

        expect($lab->problem('commission', 42)->prompt)->toBe($lab->problem('commission', 42)->prompt)
            ->and($lab->problem('commission', 42)->prompt)->not->toBe($lab->problem('commission', 43)->prompt);
    });

    it('matches a missed math question to its kind', function () {
        $question = fn (string $text): QuizQuestion => new QuizQuestion(['topic' => 'math', 'content' => ['en' => ['question' => $text, 'options' => [], 'explanation' => '']]]);

        expect(MathLab::kindFor($question('Annual taxes of $3,650 are prorated at closing. How much does the seller owe?')))->toBe('proration')
            ->and(MathLab::kindFor($question('A $300,000 sale pays 6%, split 50/50 between brokerages. How much does the agent earn?')))->toBe('commission')
            ->and(MathLab::kindFor($question('A parcel is 660 feet by 330 feet. About how many acres is it?')))->toBe('area')
            ->and(MathLab::kindFor($question('A building nets $54,000 a year. At a 9% capitalization rate, what is its value?')))->toBe('cap')
            ->and(MathLab::kindFor(new QuizQuestion(['topic' => 'agency', 'content' => ['en' => ['question' => 'Commission?', 'options' => [], 'explanation' => '']]])))->toBeNull();
    });
});

describe('solving step by step (screen 48)', function () {
    it('checks each step on the server, hints when wrong, shows when asked, and records the solve', function () {
        $problem = $this->actingAs($this->sofia)->postJson('/api/v1/practice/math/problems', ['kind' => 'ltv'])->assertCreated()
            ->assertJsonCount(3, 'data.steps')->assertJsonMissingPath('data.steps.0.answer')->json('data');
        $answers = mathAnswers('ltv', $problem['seed']);
        $check = fn (int $step, array $body) => $this->postJson('/api/v1/practice/math/check', ['kind' => 'ltv', 'seed' => $problem['seed'], 'step' => $step, ...$body]);

        // Out of order.
        $check(2, ['value' => $answers[1]])->assertUnprocessable();
        // Wrong: the hint, no answer.
        $check(1, ['value' => $answers[0] + 5000])->assertOk()->assertJsonPath('data.correct', false)
            ->assertJsonPath('data.hint', 'Lenders use the lower of the price and the appraisal.')->assertJsonPath('data.answer', null);
        // Right, written the way people write money.
        $check(1, ['value' => '$'.number_format($answers[0])])->assertOk()->assertJsonPath('data.correct', true)->assertJsonPath('data.answer', '$'.number_format($answers[0]));
        $check(2, ['value' => $answers[1]])->assertOk()->assertJsonPath('data.correct', true)->assertJsonPath('data.solved', false);
        $check(3, ['reveal' => true])->assertOk()->assertJsonPath('data.correct', false)->assertJsonPath('data.solved', true)->assertJsonPath('data.answer', fn (string $a) => str_starts_with($a, '$'));
        $check(1, ['value' => 'a lot'])->assertUnprocessable()->assertJsonValidationErrors('value');

        $solve = MathSolve::query()->sole();
        expect($solve->solved_at)->not->toBeNull()->and($solve->steps[1])->toBe(['tries' => 2, 'right' => true, 'shown' => false])->and($solve->steps[3]['shown'])->toBeTrue();
        $menu = collect($this->getJson('/api/v1/practice/math')->assertOk()->assertJsonCount(7, 'data')->json('data'))->keyBy('kind');
        expect($menu['ltv']['solved'])->toBe(1)->and($menu['cap']['solved'])->toBe(0);
    });

    it('opens on the web, straight into a kind from a missed question, for candidates only', function () {
        $this->actingAs($this->sofia)->get('/practice/math?kind=proration')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('practice/math')->has('kinds', 7)->where('problem.kind', 'proration')->has('problem.steps', 3));
        $this->postJson('/practice/math/problems', ['kind' => 'area'])->assertCreated();

        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->getJson('/api/v1/practice/math')->assertNotFound();
    });

    it('links a missed math question in the results to its kind', function () {
        $attempt = $this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'topic', 'topic' => 'math'])->json('data.id');
        $this->postJson("/api/v1/practice/attempts/{$attempt}/submit")->assertOk();
        $review = collect($this->getJson("/api/v1/practice/attempts/{$attempt}/results")->json('data.review'));

        expect($review->pluck('math')->filter()->isNotEmpty())->toBeTrue()
            ->and($review->pluck('math')->filter()->every(fn ($k) => in_array($k, app(MathLab::class)->kinds(), true)))->toBeTrue();
    });
});
