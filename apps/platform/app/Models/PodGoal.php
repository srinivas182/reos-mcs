<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A pod member's goal for the week (A13.2: "a pod of five sees each other's weekly goals").
 *
 * @property int $id
 * @property int $pod_id
 * @property int $user_id
 * @property string $week_of The week's Monday, Y-m-d (kept as text so every database compares it the same way)
 * @property string $text
 */
final class PodGoal extends Model
{
    protected $fillable = ['pod_id', 'user_id', 'week_of', 'text'];
}
