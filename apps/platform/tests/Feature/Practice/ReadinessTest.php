<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OnboardingPath;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\ReadinessSnapshot;
use App\Models\StudyPlan;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\Readiness;
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
 * A finished test with these shares right per topic.
 *
 * @param  array<string, array{0: int, 1: int}>  $topics  [correct, total]
 * @param  array<string, bool>|null  $portions  passed per portion, for a simulation
 */
function readinessTest(User $user, array $topics, int $daysAgo = 0, string $kind = 'topic', ?array $portions = null): TestAttempt
{
    $at = now()->subDays($daysAgo);

    return TestAttempt::query()->create([
        'user_id' => $user->id, 'kind' => $kind, 'mode' => 'exam', 'state_code' => 'TX', 'question_ids' => [], 'total' => 0,
        'started_at' => $at->copy()->subHour(), 'submitted_at' => $at,
        'by_topic' => array_map(static fn (array $s): array => ['correct' => $s[0], 'total' => $s[1]], $topics),
        'by_portion' => $portions === null ? null : array_map(static fn (bool $p): array => ['correct' => $p ? 70 : 10, 'total' => 80, 'pass' => 56, 'passed' => $p], $portions),
    ]);
}

/** @return array<string, array{0: int, 1: int}> */
function readinessShare(float $share, int $per = 20): array
{
    return array_map(static fn (): array => [(int) round($share * $per), $per], ['agency' => 1, 'contracts' => 1, 'ownership' => 1, 'finance' => 1, 'math' => 1, 'state_rules' => 1]);
}

describe('the predicted pass chance (A9.2, ADR 0035)', function () {
    it('is the chance of reaching the pass mark on a new set of questions', function () {
        // National: 80 scored, pass at 56.
        expect(Readiness::chance(0.70, 0, 80, 56))->toBe(55)
            ->and(Readiness::chance(0.75, 0, 80, 56))->toBe(88)
            ->and(Readiness::chance(0.85, 0, 80, 56))->toBe(100)
            // Being unsure of the estimate pulls a strong candidate down.
            ->and(Readiness::chance(0.75, 0.004, 80, 56))->toBeLessThan(80)
            ->and(Readiness::chance(0.50, 0, 40, 28))->toBe(1);
    });

    it('rises with more good answers, gives each portion its own chance, and is the weaker of the two', function () {
        readinessTest($this->sofia, readinessShare(0.8, 6));
        $few = app(Readiness::class)->of($this->sofia);
        readinessTest($this->sofia, readinessShare(0.8, 40));
        readinessTest($this->sofia, ['state_rules' => [20, 40]]);
        $many = app(Readiness::class)->of($this->sofia);

        expect($few['portions']['national'])->toBeLessThan(90)
            ->and($many['portions']['national'])->toBeGreaterThan($few['portions']['national'])->toBeGreaterThanOrEqual(90)
            ->and($many['portions']['state'])->toBeLessThan(50)
            ->and($many['percent'])->toBe($many['portions']['state'])
            ->and($many['weakest'])->toBe(['state_rules']);
    });

    it('counts older answers less', function () {
        readinessTest($this->sofia, ['finance' => [0, 20]], daysAgo: 63);
        readinessTest($this->sofia, ['finance' => [20, 20]]);

        // Three half-lives old: the miss weighs an eighth.
        expect(app(Readiness::class)->of($this->sofia)['topics']['finance'])->toBe(89);
    });
});

describe('the Readiness map and What you\'re good at (screens 54 and 44)', function () {
    it('unlocks booking at 90% on both portions with two recent simulations passed, and says where to book', function () {
        readinessTest($this->sofia, readinessShare(0.9, 60));
        $map = app(Readiness::class)->map($this->sofia);

        expect($map['line'])->toBe(90)
            ->and($map['booking'])->toMatchArray(['ready' => false, 'national' => true, 'state' => true, 'simulations' => 0, 'needed' => 2])
            ->and($map['booking']['url'])->toStartWith('https://www.pearsonvue.com/');

        readinessTest($this->sofia, readinessShare(0.9, 10), kind: 'simulation', portions: ['national' => true, 'state' => false]);
        readinessTest($this->sofia, readinessShare(0.9, 10), kind: 'simulation', portions: ['national' => true, 'state' => true]);
        expect(app(Readiness::class)->map($this->sofia)['booking'])->toMatchArray(['ready' => false, 'simulations' => 1]);

        $this->travel(1)->minutes();
        readinessTest($this->sofia, readinessShare(0.9, 10), kind: 'simulation', portions: ['national' => true, 'state' => true]);
        expect(app(Readiness::class)->map($this->sofia)['booking'])->toMatchArray(['ready' => true, 'simulations' => 2])
            ->and(app(Readiness::class)->of($this->sofia)['ready_to_book'])->toBeTrue();
    });

    it('ranks topics weakest first, shows a share and trend only from 10 answers, and the gain from fixing the weakest', function () {
        readinessTest($this->sofia, ['agency' => [9, 10], 'contracts' => [8, 10], 'ownership' => [8, 10], 'finance' => [4, 10], 'math' => [3, 4], 'state_rules' => [9, 10]], daysAgo: 8);
        app(Readiness::class)->record($this->sofia);
        ReadinessSnapshot::query()->update(['day' => now()->subDays(8)->toDateString()]);
        readinessTest($this->sofia, ['finance' => [8, 10]]);

        $map = app(Readiness::class)->map($this->sofia);
        $topics = collect($map['topics'])->keyBy('topic');

        expect(collect($map['topics'])->pluck('topic')->take(2)->all())->toBe(['finance', 'math'])
            ->and($topics['math'])->toMatchArray(['answers' => 4, 'shown' => false, 'trend' => null, 'weak' => false])
            ->and($topics['finance']['shown'])->toBeTrue()
            ->and($topics['finance']['trend'])->toBeGreaterThan(10)
            ->and($topics['state_rules']['portion'])->toBe('state')
            ->and($map['gain'])->toMatchArray(['topic' => 'finance', 'portion' => 'national'])
            ->and($map['gain']['points'])->toBeGreaterThan(0);
    });

    it('is on the phone and the web for candidates only', function () {
        readinessTest($this->sofia, readinessShare(0.7));
        $this->actingAs($this->sofia)->getJson('/api/v1/practice/readiness')->assertOk()
            ->assertJsonPath('data.line', 90)->assertJsonCount(6, 'data.topics')->assertJsonStructure(['data' => ['portions' => ['national', 'state'], 'booking' => ['ready', 'url'], 'gain']]);
        $this->get('/readiness')->assertOk()->assertInertia(fn (Assert $page) => $page->component('practice/readiness')->where('map.line', 90)->has('map.topics', 6));
        $this->getJson('/api/v1/me/study')->assertOk()->assertJsonStructure(['data' => ['portions' => ['national', 'state'], 'ready_to_book']]);

        $agent = User::factory()->withRole(Role::Agent)->create();
        $this->actingAs($agent)->getJson('/api/v1/practice/readiness')->assertNotFound();
    });
});

describe('kept nightly and after every test; the plan follows it', function () {
    it('keeps the day\'s readiness when a test is finished', function () {
        $attempt = TestAttempt::query()->findOrFail($this->actingAs($this->sofia)->postJson('/api/v1/practice/tests', ['kind' => 'topic', 'topic' => 'math'])->json('data.id'));
        foreach (QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get() as $q) {
            $this->putJson("/api/v1/practice/attempts/{$attempt->id}/answers/{$q->id}", ['choice' => $q->correct_option]);
        }
        $this->postJson("/api/v1/practice/attempts/{$attempt->id}/submit")->assertOk();

        expect(ReadinessSnapshot::query()->sole())->day->toDateString()->toBe('2026-11-23')->topics->math->toBe(100);
    });

    it('records every active candidate after midnight and rebuilds a plan when the weak topics changed', function () {
        readinessTest($this->sofia, ['finance' => [2, 10], 'math' => [9, 10]]);
        $this->actingAs($this->sofia)->getJson('/api/v1/me/study')->assertOk();
        expect(StudyPlan::query()->where('active', true)->sole()->weak_topics)->toBe(['finance']);

        // That night: nothing changed.
        $this->travelTo(CarbonImmutable::parse('2026-11-24 00:10', StudyPlans::TIMEZONE));
        $this->artisan('reos:readiness:nightly')->expectsOutputToContain('Recorded readiness for 1 candidates; rebuilt 0 plans.')->assertSuccessful();

        // A math test goes badly; the next night the plan follows.
        readinessTest($this->sofia, ['math' => [0, 30]]);
        $this->travelTo(CarbonImmutable::parse('2026-11-25 00:10', StudyPlans::TIMEZONE));
        $this->artisan('reos:readiness:nightly')->expectsOutputToContain('rebuilt 1 plans.')->assertSuccessful();

        $plan = StudyPlan::query()->where('active', true)->sole();
        expect($plan->reason)->toBe('readiness')->and($plan->weak_topics)->toBe(['finance', 'math'])
            ->and(ReadinessSnapshot::query()->where('user_id', $this->sofia->id)->pluck('day')->map->toDateString()->all())->toBe(['2026-11-24', '2026-11-25']);
    });
});
