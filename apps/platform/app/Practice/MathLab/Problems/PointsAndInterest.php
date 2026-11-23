<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;

/** Points and interest: the loan, what the points cost, and the first month's interest. */
final class PointsAndInterest implements ProblemType
{
    public function kind(): string
    {
        return 'points';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $price = $rng->int(180, 600) * 1000;
        $down = $rng->pick([5.0, 10.0, 20.0]);
        $points = $rng->pick([1.0, 1.5, 2.0, 2.5]);
        $rate = $rng->pick([5.25, 5.5, 6.0, 6.25, 6.5, 6.75, 7.0]);
        $loan = $price * (1 - $down / 100);

        return new Problem($this->kind(), $seed, __('practice.math.points.prompt', ['price' => Formats::money($price), 'down' => Formats::percent($down), 'points' => Formats::number($points), 'rate' => Formats::percent($rate)]), [
            new Step(__('practice.math.points.loan'), round($loan, 2), 'dollars', __('practice.math.points.loanHint')),
            new Step(__('practice.math.points.cost'), round($loan * $points / 100, 2), 'dollars', __('practice.math.points.costHint')),
            new Step(__('practice.math.points.interest'), round($loan * $rate / 100 / 12, 2), 'dollars', __('practice.math.points.interestHint')),
        ]);
    }
}
