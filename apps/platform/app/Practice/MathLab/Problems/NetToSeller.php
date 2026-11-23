<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;

/** The seller's net sheet: commission, total selling costs, and what the seller walks away with after the payoff. */
final class NetToSeller implements ProblemType
{
    public function kind(): string
    {
        return 'net';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $price = $rng->int(200, 650) * 1000;
        $rate = $rng->pick([5.0, 5.5, 6.0]);
        $costs = $rng->int(25, 90) * 100;
        $payoff = round($price * $rng->int(20, 75) / 100, -3);
        $commission = $price * $rate / 100;

        return new Problem($this->kind(), $seed, __('practice.math.net.prompt', ['price' => Formats::money($price), 'rate' => Formats::percent($rate), 'costs' => Formats::money($costs), 'payoff' => Formats::money($payoff)]), [
            new Step(__('practice.math.net.commission'), round($commission, 2), 'dollars', __('practice.math.net.commissionHint')),
            new Step(__('practice.math.net.selling'), round($commission + $costs, 2), 'dollars', __('practice.math.net.sellingHint')),
            new Step(__('practice.math.net.net'), round($price - $commission - $costs - $payoff, 2), 'dollars', __('practice.math.net.netHint')),
        ]);
    }
}
