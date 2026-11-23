<?php

declare(strict_types=1);

namespace App\Practice;

use App\Enums\QuizTopic;
use App\Services\StateRules\StateRules;
use Carbon\CarbonImmutable;

/**
 * The licensing exam's format per state (A9.1, ADR 0035): questions,
 * scored questions, minutes and the pass mark for each portion, read from
 * the state's rules in force (screen 62), so another state or a format
 * change needs no code. Texas: National 85 (80 scored) in 150 minutes, pass
 * at 56; Texas 50 (40 scored) in 90 minutes, pass at 28.
 *
 * @phpstan-type Portion array{portion: string, questions: int, scored: int, minutes: int, pass: int}
 */
final readonly class ExamFormats
{
    public const NATIONAL = 'national';

    public const STATE = 'state';

    /** Used when a state has no exam rules yet: the Texas format. */
    private const DEFAULTS = [
        self::NATIONAL => ['questions' => 85, 'scored' => 80, 'minutes' => 150, 'pass' => 56],
        self::STATE => ['questions' => 50, 'scored' => 40, 'minutes' => 90, 'pass' => 28],
    ];

    public function __construct(private StateRules $rules) {}

    /** @return Portion */
    public function portion(string $state, string $portion): array
    {
        $inForce = $this->rules->inForce($state, CarbonImmutable::now());
        $number = static function (string $key, int $default) use ($inForce): int {
            $value = $inForce[$key]->value['number'] ?? null;

            return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
        };
        $defaults = self::DEFAULTS[$portion] ?? self::DEFAULTS[self::NATIONAL];
        $questions = $number("exam.{$portion}.questions", $defaults['questions']);
        $scored = min($questions, $number("exam.{$portion}.scored", $defaults['scored']));

        return [
            'portion' => $portion, 'questions' => $questions, 'scored' => $scored,
            'minutes' => $number("exam.{$portion}.minutes", $defaults['minutes']),
            'pass' => min($scored, $number("exam.{$portion}.pass", $defaults['pass'])),
        ];
    }

    /** The portion a question belongs to: its own, else the state portion for state rules and national for the rest. */
    public static function portionOf(QuizTopic $topic, ?string $portion): string
    {
        return in_array($portion, [self::NATIONAL, self::STATE], true) ? $portion : ($topic === QuizTopic::StateRules ? self::STATE : self::NATIONAL);
    }
}
