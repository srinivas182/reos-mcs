<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Someone dismissed a release's "What's new" (A6.2): it doesn't show again
 * on the web or the phone.
 *
 * @property int $id
 * @property int $release_id
 * @property int $user_id
 * @property Carbon $dismissed_at
 */
final class ReleaseRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['release_id', 'user_id', 'dismissed_at'];

    protected function casts(): array
    {
        return ['dismissed_at' => 'datetime'];
    }
}
