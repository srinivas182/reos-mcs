<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An announcement (B6.1b) from a brokerage, school or association admin to
 * all members or a group (roles, teams, states), with read receipts.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property int|null $author_id
 * @property string $title
 * @property string $body
 * @property array{roles?: list<string>, teams?: list<int>, states?: list<string>} $audience
 * @property int $recipients_count
 * @property Carbon $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read Organisation $organisation
 */
final class Announcement extends Model
{
    protected $fillable = ['uuid', 'organisation_id', 'author_id', 'title', 'body', 'audience', 'recipients_count', 'sent_at'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'recipients_count' => 'integer', 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<AnnouncementRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
