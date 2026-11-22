<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Content tagged by state (A5.1): courses, lessons, test prep, knowledge base
 * passages and plans carry `states` (a JSON list of state codes, or null for
 * every state). `forStates()` keeps only what any of the given states may
 * see; every content query for a person goes through it with their current
 * state (`PersonStates::current`).
 *
 * @phpstan-require-extends Model
 */
trait StateTagged
{
    /**
     * @param  Builder<static>  $query
     * @param  list<string>  $codes
     */
    public function scopeForStates(Builder $query, array $codes): void
    {
        $column = $this->qualifyColumn('states');

        $query->where(static function (Builder $inner) use ($column, $codes): void {
            $inner->whereNull($column);

            foreach ($codes as $code) {
                $inner->orWhereJsonContains($column, strtoupper($code));
            }
        });
    }
}
