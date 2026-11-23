<?php

declare(strict_types=1);

namespace App\Practice\MathLab;

/** A math lab problem: its kind and seed (which rebuild it), the question, and the steps. */
final readonly class Problem
{
    /** @param  non-empty-list<Step>  $steps */
    public function __construct(public string $kind, public int $seed, public string $prompt, public array $steps) {}

    /**
     * For the screen: everything but the answers.
     *
     * @return array{kind: string, seed: int, prompt: string, steps: list<array{n: int, label: string, unit: string}>}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind, 'seed' => $this->seed, 'prompt' => $this->prompt,
            'steps' => array_map(static fn (Step $s, int $i): array => ['n' => $i + 1, 'label' => $s->label, 'unit' => $s->unit], $this->steps, array_keys($this->steps)),
        ];
    }
}
