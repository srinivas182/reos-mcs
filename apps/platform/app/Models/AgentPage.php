<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's public page (B10.2b): reos.com/agent/{handle}.
 *
 * @property int $id
 * @property int $user_id
 * @property string $handle
 * @property bool $published
 * @property string|null $bio
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class AgentPage extends Model
{
    protected $fillable = ['user_id', 'handle', 'published', 'bio'];

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
