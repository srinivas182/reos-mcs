<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A conversation in Ask (A10.1, candidate screen 13).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property string $state_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, AskMessage> $messages
 */
final class AskThread extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'user_id', 'title', 'state_code'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AskMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AskMessage::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
