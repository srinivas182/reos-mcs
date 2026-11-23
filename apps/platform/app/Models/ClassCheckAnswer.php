<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A student's "I'm here" to an attendance check (A10.2b).
 *
 * @property int $id
 * @property int $class_check_id
 * @property int $user_id
 * @property CarbonImmutable $answered_at
 */
final class ClassCheckAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['class_check_id', 'user_id', 'answered_at'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }
}
