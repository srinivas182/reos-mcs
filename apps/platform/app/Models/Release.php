<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A release (A6.2, screen 70): its version and date, what changed in plain
 * words for each role ("What's new"), and the flags it rolls out.
 *
 * @property int $id
 * @property string $version
 * @property Carbon $released_on
 * @property array<string, string> $notes role value => note
 * @property list<string>|null $flags
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 */
final class Release extends Model
{
    protected $fillable = ['version', 'released_on', 'notes', 'flags', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['released_on' => 'date', 'notes' => 'array', 'flags' => 'array', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ReleaseRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(ReleaseRead::class);
    }

    public function noteFor(string $role): ?string
    {
        $note = $this->notes[$role] ?? null;

        return is_string($note) && trim($note) !== '' ? $note : null;
    }
}
