<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A poll the instructor launched in a live class (A10.2b).
 *
 * @property int $id
 * @property int $video_session_id
 * @property string $question
 * @property list<string> $options
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ClassPollVote> $votes
 */
final class ClassPoll extends Model
{
    protected $fillable = ['video_session_id', 'question', 'options', 'closed_at'];

    protected function casts(): array
    {
        return ['options' => 'array', 'closed_at' => 'datetime'];
    }

    /** @return HasMany<ClassPollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ClassPollVote::class);
    }
}
