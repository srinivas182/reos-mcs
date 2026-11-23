<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;

/** The income approach: effective gross income, net operating income, and value at the cap rate. */
final class Capitalization implements ProblemType
{
    public function kind(): string
    {
        return 'cap';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $gross = $rng->int(60, 400) * 1000;
        $vacancy = $rng->pick([3.0, 5.0, 7.0, 8.0, 10.0]);
        $expenses = round($gross * $rng->int(25, 45) / 100, -2);
        $cap = $rng->pick([5.0, 6.0, 6.5, 7.0, 7.5, 8.0, 9.0, 10.0]);
        $effective = $gross * (1 - $vacancy / 100);
        $noi = $effective - $expenses;

        return new Problem($this->kind(), $seed, __('practice.math.cap.prompt', ['gross' => Formats::money($gross), 'vacancy' => Formats::percent($vacancy), 'expenses' => Formats::money($expenses), 'cap' => Formats::percent($cap)]), [
            new Step(__('practice.math.cap.effective'), round($effective, 2), 'dollars', __('practice.math.cap.effectiveHint')),
            new Step(__('practice.math.cap.noi'), round($noi, 2), 'dollars', __('practice.math.cap.noiHint')),
            new Step(__('practice.math.cap.value'), round($noi / ($cap / 100), 2), 'dollars', __('practice.math.cap.valueHint'), 15),
        ]);
    }
}
