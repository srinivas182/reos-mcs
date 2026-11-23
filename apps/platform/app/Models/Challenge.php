<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A challenge (A9.3b, screen 52): 10 questions a candidate sends by link;
 * everyone who accepts answers the same ones.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id Who sent it
 * @property int|null $study_group_id Sent to a whole study group (A9.3c)
 * @property string $state_code
 * @property list<int> $question_ids
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, TestAttempt> $attempts
 */
final class Challenge extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'user_id', 'study_group_id', 'state_code', 'question_ids', 'expires_at'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TestAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['question_ids' => 'array', 'expires_at' => 'datetime'];
    }
}
