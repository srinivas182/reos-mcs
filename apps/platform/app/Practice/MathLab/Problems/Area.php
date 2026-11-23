<?php

declare(strict_types=1);

namespace App\Practice\MathLab\Problems;

use App\Practice\MathLab\Formats;
use App\Practice\MathLab\Problem;
use App\Practice\MathLab\ProblemType;
use App\Practice\MathLab\Rng;
use App\Practice\MathLab\Step;

/** Area: a lot's square feet, its acres (43,560 square feet each), and its price at so much an acre. */
final class Area implements ProblemType
{
    public const SQFT_PER_ACRE = 43560;

    public function kind(): string
    {
        return 'area';
    }

    public function make(int $seed): Problem
    {
        $rng = new Rng($seed);
        $front = $rng->int(12, 66) * 10;
        $depth = $rng->int(15, 90) * 10;
        $perAcre = $rng->int(8, 60) * 1000;
        $sqft = $front * $depth;
        $acres = round($sqft / self::SQFT_PER_ACRE, 2);

        return new Problem($this->kind(), $seed, __('practice.math.area.prompt', ['front' => Formats::number($front), 'depth' => Formats::number($depth), 'per_acre' => Formats::money($perAcre)]), [
            new Step(__('practice.math.area.sqft'), $sqft, 'sqft', __('practice.math.area.sqftHint'), 0),
            new Step(__('practice.math.area.acres'), $acres, 'acres', __('practice.math.area.acresHint'), 0.01),
            new Step(__('practice.math.area.price'), round($sqft / self::SQFT_PER_ACRE * $perAcre, 2), 'dollars', __('practice.math.area.priceHint'), max(1.0, $perAcre * 0.005)),
        ]);
    }
}
