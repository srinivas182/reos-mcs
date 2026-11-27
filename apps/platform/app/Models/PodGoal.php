<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A pod member's goal for the week (A13.2: "a pod of five sees each other's weekly goals").
 *
 * @property int $id
 * @property int $pod_id
 * @property int $user_id
 * @property CarbonImmutable $week_of
 * @property string $text
 */
final class PodGoal extends Model
{
    protected $fillable = ['pod_id', 'user_id', 'week_of', 'text'];

    protected function casts(): array
    {
        return ['week_of' => 'immutable_date'];
    }
}
