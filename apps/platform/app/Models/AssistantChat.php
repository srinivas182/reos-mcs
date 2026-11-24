<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agent's chat with the AI assistant (A12.3, ADR 0049; agent screens 59 and 60).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, AssistantMessage> $messages
 */
final class AssistantChat extends Model
{
    protected $fillable = ['uuid', 'user_id', 'title'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<AssistantMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class)->orderBy('id');
    }
}
