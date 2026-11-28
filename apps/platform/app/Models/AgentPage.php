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
 * @property string|null $phone shown on the page and card only when the agent adds it (B14.1)
 * @property string|null $email
 * @property list<string>|null $areas the areas the agent serves
 * @property CarbonImmutable|null $approval_requested_at waiting for the broker (B13.2)
 * @property string|null $approval_note what the broker said when sending it back
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class AgentPage extends Model
{
    protected $fillable = ['user_id', 'handle', 'published', 'bio', 'phone', 'email', 'areas'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'approval_requested_at' => 'immutable_datetime', 'areas' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
