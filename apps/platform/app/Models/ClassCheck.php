<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An attendance check in a live class (A10.2b): "Are you here?", open for
 * a few minutes, sent on the approval's interval or by the instructor.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int|null $sent_by
 * @property CarbonImmutable $opens_at
 * @property CarbonImmutable $closes_at
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ClassCheckAnswer> $answers
 */
final class ClassCheck extends Model
{
    protected $fillable = ['video_session_id', 'sent_by', 'opens_at', 'closes_at', 'reminded_at'];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    /** @return HasMany<ClassCheckAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(ClassCheckAnswer::class);
    }

    public function open(): bool
    {
        return now()->greaterThanOrEqualTo($this->opens_at) && now()->lessThan($this->closes_at);
    }
}
