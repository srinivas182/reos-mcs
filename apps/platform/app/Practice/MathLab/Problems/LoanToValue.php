<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;

/** Loan-to-value: the lender uses the lower of price and appraisal; then the loan and the down payment. */
final class LoanToValue implements ProblemType
{
    public function kind(): string
    {
        return 'ltv';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $price = $rng->int(200, 600) * 1000;
        $appraisal = $price + $rng->int(-25, 15) * 1000;
        $ltv = $rng->pick([80.0, 90.0, 95.0, 96.5, 97.0]);
        $value = min($price, $appraisal);
        $loan = $value * $ltv / 100;

        return new Problem($this->kind(), $seed, __('practice.math.ltv.prompt', ['price' => Formats::money($price), 'appraisal' => Formats::money($appraisal), 'ltv' => Formats::percent($ltv)]), [
            new Step(__('practice.math.ltv.value'), $value, 'dollars', __('practice.math.ltv.valueHint'), 0),
            new Step(__('practice.math.ltv.loan'), round($loan, 2), 'dollars', __('practice.math.ltv.loanHint')),
            new Step(__('practice.math.ltv.down'), round($price - $loan, 2), 'dollars', __('practice.math.ltv.downHint')),
        ]);
    }
}
