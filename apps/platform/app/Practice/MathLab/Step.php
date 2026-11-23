<?php

declare(strict_types=1);

namespace App\Practice\MathLab;

/**
 * One step of a math lab problem: what to work out, its answer (kept on
 * the server), the unit, how close counts as right, and the hint.
 */
final readonly class Step
{
    /** @param  'dollars'|'percent'|'days'|'sqft'|'acres'|'number'  $unit */
    public function __construct(
        public string $label,
        public float $answer,
        public string $unit,
        public string $hint,
        public float $tolerance = 1.0,
    ) {}

    public function accepts(float $value): bool
    {
        return abs($value - $this->answer) <= $this->tolerance + 1e-9;
    }

    /** The answer as shown: $12,345, 80%, 120 days, 2.45 acres. */
    public function shown(): string
    {
        return match ($this->unit) {
            'dollars' => '$'.number_format($this->answer, fmod($this->answer, 1.0) === 0.0 ? 0 : 2),
            'percent' => rtrim(rtrim(number_format($this->answer, 2), '0'), '.').'%',
            'acres' => number_format($this->answer, 2).' '.__('practice.math.units.acres'),
            'sqft' => number_format($this->answer).' '.__('practice.math.units.sqft'),
            'days' => number_format($this->answer).' '.__('practice.math.units.days'),
            default => rtrim(rtrim(number_format($this->answer, 2), '0'), '.'),
        };
    }
}
