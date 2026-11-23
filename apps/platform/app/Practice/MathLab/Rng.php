<?php

declare(strict_types=1);

namespace App\Practice\MathLab;

/**
 * A small seeded random number generator (xorshift32), so a problem's
 * numbers come back the same from its seed without any global state.
 */
final class Rng
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = ($seed & 0xFFFFFFFF) ?: 0x9E3779B9;
    }

    /** A whole number from $min to $max, both included. */
    public function int(int $min, int $max): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return $min + ($this->state % ($max - $min + 1));
    }

    /**
     * One of these.
     *
     * @template T
     *
     * @param  non-empty-list<T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }
}
