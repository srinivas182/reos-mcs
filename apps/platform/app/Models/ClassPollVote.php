<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One student's answer to a class poll (A10.2b).
 *
 * @property int $id
 * @property int $class_poll_id
 * @property int $user_id
 * @property int $option
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ClassPollVote extends Model
{
    protected $fillable = ['class_poll_id', 'user_id', 'option'];

    protected function casts(): array
    {
        return ['option' => 'integer'];
    }
}
