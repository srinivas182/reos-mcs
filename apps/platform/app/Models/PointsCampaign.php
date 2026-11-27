<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A bonus campaign a brokerage funds (A13.3a), such as double points on CE in November.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property string $title
 * @property string $goal a goal key, or `all`
 * @property int $multiplier
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $budget
 * @property int $spent
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PointsCampaign extends Model
{
    protected $fillable = ['uuid', 'organisation_id', 'title', 'goal', 'multiplier', 'starts_on', 'ends_on', 'budget', 'spent', 'created_by'];

    protected $attributes = ['spent' => 0];

    protected function casts(): array
    {
        return ['multiplier' => 'integer', 'budget' => 'integer', 'spent' => 'integer', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
