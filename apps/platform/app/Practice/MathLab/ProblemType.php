<?php

declare(strict_types=1);

namespace App\Practice\MathLab;

/** A kind of real estate math problem that makes new numbers from a seed (screen 48, A9.2c). */
interface ProblemType
{
    public function kind(): string;

    public function make(int $seed): Problem;
}
