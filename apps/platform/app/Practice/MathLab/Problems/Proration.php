<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;
use Carbon\CarbonImmutable;

/**
 * Prorating property taxes, Texas style: taxes are paid at year end, so
 * the seller credits the buyer for January 1 through the closing date
 * (365-day year).
 */
final class Proration implements ProblemType
{
    public function kind(): string
    {
        return 'proration';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $daily = $rng->int(800, 2800) / 100;
        $tax = round($daily * 365, 2);
        $closing = CarbonImmutable::parse('2027-01-01')->addDays($rng->int(40, 320));
        $days = $closing->dayOfYear;
        $share = round($tax / 365 * $days, 2);
        $local = $closing->locale(app()->getLocale());
        $date = $local instanceof CarbonImmutable ? $local->translatedFormat('F j') : $closing->format('F j');

        return new Problem($this->kind(), $seed, __('practice.math.proration.prompt', ['tax' => Formats::money($tax), 'date' => $date]), [
            new Step(__('practice.math.proration.daily'), round($tax / 365, 2), 'dollars', __('practice.math.proration.dailyHint'), 0.01),
            new Step(__('practice.math.proration.days'), $days, 'days', __('practice.math.proration.daysHint'), 0),
            new Step(__('practice.math.proration.share'), $share, 'dollars', __('practice.math.proration.shareHint'), 2),
        ]);
    }
}
