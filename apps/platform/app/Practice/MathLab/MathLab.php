<?php

declare(strict_types=1);

namespace App\Practice\MathLab;

use App\Enums\QuizTopic;
use App\Models\MathSolve;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Practice\MathLab\Problems\Area;
use App\Practice\MathLab\Problems\Capitalization;
use App\Practice\MathLab\Problems\Commission;
use App\Practice\MathLab\Problems\LoanToValue;
use App\Practice\MathLab\Problems\NetToSeller;
use App\Practice\MathLab\Problems\PointsAndInterest;
use App\Practice\MathLab\Problems\Proration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Math lab (candidate screen 48, A9.2c, ADR 0035): step-by-step real estate
 * math with new numbers every time.
 *
 * Each kind is a generator that makes a problem from a seed, so the server
 * rebuilds it to check every step and the answers never leave it until a
 * step is right or the candidate asks to see it. A wrong value brings the
 * step's hint. A solved problem is followed by a variant of the same kind,
 * and a missed math question opens its matching kind (`kindFor`).
 */
final readonly class MathLab
{
    /** @var array<string, ProblemType> */
    private array $types;

    public function __construct()
    {
        $types = [new Proration, new Commission, new LoanToValue, new Area, new Capitalization, new NetToSeller, new PointsAndInterest];
        $this->types = array_combine(array_map(static fn (ProblemType $t): string => $t->kind(), $types), $types);
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return array_keys($this->types);
    }

    /**
     * The lab's menu: every kind with how many the candidate has solved.
     *
     * @return list<array{kind: string, solved: int}>
     */
    public function menu(User $user): array
    {
        $solved = MathSolve::query()->where('user_id', $user->id)->whereNotNull('solved_at')->select('kind', DB::raw('count(*) as n'))->groupBy('kind')->pluck('n', 'kind');

        return array_map(static fn (string $kind): array => ['kind' => $kind, 'solved' => is_numeric($solved[$kind] ?? null) ? (int) $solved[$kind] : 0], $this->kinds());
    }

    /** A new problem of this kind (new numbers each time). */
    public function problem(string $kind, ?int $seed = null): Problem
    {
        $type = $this->types[$kind] ?? throw ValidationException::withMessages(['kind' => __('practice.errors.noQuestions')]);

        return $type->make($seed ?? random_int(1, 2_000_000_000));
    }

    /**
     * Check one step's value, or show its answer. Steps go in order; the
     * problem is solved once its last step is right or shown.
     *
     * @return array{correct: bool, hint: string|null, answer: string|null, solved: bool}
     *
     * @throws ValidationException for a step out of order
     */
    public function check(User $user, string $kind, int $seed, int $step, ?float $value, bool $reveal): array
    {
        $problem = $this->problem($kind, $seed);
        $current = $problem->steps[$step - 1] ?? throw ValidationException::withMessages(['step' => __('practice.math.errors.step')]);

        return DB::transaction(function () use ($user, $problem, $current, $step, $value, $reveal): array {
            $solve = MathSolve::query()->lockForUpdate()->firstOrCreate(['user_id' => $user->id, 'kind' => $problem->kind, 'seed' => $problem->seed], ['steps' => []]);
            $steps = $solve->steps;
            $done = static fn (int $n): bool => ($steps[$n]['right'] ?? false) || ($steps[$n]['shown'] ?? false);

            for ($n = 1; $n < $step; $n++) {
                if (! $done($n)) {
                    throw ValidationException::withMessages(['step' => __('practice.math.errors.step')]);
                }
            }

            $record = $steps[$step] ?? ['tries' => 0, 'right' => false, 'shown' => false];
            $correct = ! $reveal && $value !== null && $current->accepts($value);
            $record = ['tries' => $record['tries'] + ($reveal ? 0 : 1), 'right' => $record['right'] || $correct, 'shown' => $record['shown'] || $reveal];
            $steps[$step] = $record;
            $solved = $step === count($problem->steps) && ($record['right'] || $record['shown']);
            $solve->forceFill(['steps' => $steps, 'solved_at' => $solved ? ($solve->solved_at ?? now()) : $solve->solved_at])->save();

            return [
                'correct' => $correct,
                'hint' => $correct || $reveal ? null : $current->hint,
                'answer' => $correct || $reveal ? $current->shown() : null,
                'solved' => $solved,
            ];
        });
    }

    /** The math lab kind that practises a math question, from its wording; null if none fits. */
    public static function kindFor(QuizQuestion $question): ?string
    {
        if ($question->topic !== QuizTopic::Math) {
            return null;
        }

        return self::kindForText($question->in('en')['question']);
    }

    /** The math lab kind for a question in words (Ask, A10.1: "Try a similar one"); null if it isn't real estate math. */
    public static function kindForText(string $question): ?string
    {
        $text = mb_strtolower($question);

        if (preg_match('/\d/', $text) !== 1) {
            return null;
        }

        return match (true) {
            str_contains($text, 'prorat') || str_contains($text, 'taxes are paid') => 'proration',
            str_contains($text, 'net') && str_contains($text, 'seller') => 'net',
            str_contains($text, 'commission') || (str_contains($text, 'split') && str_contains($text, 'earn')) => 'commission',
            str_contains($text, 'loan-to-value') || str_contains($text, 'lend') || str_contains($text, 'down payment') => 'ltv',
            str_contains($text, 'acre') || str_contains($text, 'square') || str_contains($text, 'front foot') => 'area',
            str_contains($text, 'capitaliz') || str_contains($text, 'net operating income') || str_contains($text, 'cap rate') => 'cap',
            str_contains($text, 'point') || str_contains($text, 'interest') => 'points',
            default => null,
        };
    }
}
