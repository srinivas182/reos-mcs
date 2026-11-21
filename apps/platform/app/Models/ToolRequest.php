<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "My tool isn't listed": a tool someone wants REOS to connect to.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $note
 * @property Carbon $created_at
 */
final class ToolRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'name', 'note'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
