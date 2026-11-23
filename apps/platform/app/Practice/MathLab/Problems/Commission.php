<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;

/** A commission from the sale price to the agent's share: total, the brokerage's side, the agent's split. */
final class Commission implements ProblemType
{
    public function kind(): string
    {
        return 'commission';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $price = $rng->int(180, 750) * 1000;
        $rate = $rng->pick([5.0, 5.5, 6.0, 6.5, 7.0]);
        $side = $rng->pick([50, 50, 60]);
        $split = $rng->pick([60, 70, 75, 80, 90]);
        $total = $price * $rate / 100;
        $brokerage = $total * $side / 100;

        return new Problem($this->kind(), $seed, __('practice.math.commission.prompt', [
            'price' => Formats::money($price), 'rate' => Formats::percent($rate), 'side' => Formats::percent($side), 'split' => Formats::percent($split),
        ]), [
            new Step(__('practice.math.commission.total'), round($total, 2), 'dollars', __('practice.math.commission.totalHint')),
            new Step(__('practice.math.commission.side'), round($brokerage, 2), 'dollars', __('practice.math.commission.sideHint')),
            new Step(__('practice.math.commission.agent'), round($brokerage * $split / 100, 2), 'dollars', __('practice.math.commission.agentHint')),
        ]);
    }
}
