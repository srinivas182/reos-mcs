<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A brokerage's own daily targets for one mode (A13.3a).
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $mode launch, grow, lead or own
 * @property array<string, int> $targets
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class DailyGoalSetting extends Model
{
    protected $fillable = ['organisation_id', 'mode', 'targets', 'updated_by'];

    protected function casts(): array
    {
        return ['targets' => 'array', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
