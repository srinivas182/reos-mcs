<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A short congrats on someone's pass (A10.3b, f06), once per person.
 *
 * @property int $id
 * @property int $pass_announcement_id
 * @property int $user_id
 * @property string $message
 * @property CarbonImmutable $created_at
 */
final class PassCongrats extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pass_congrats';

    protected $fillable = ['pass_announcement_id', 'user_id', 'message'];
}
