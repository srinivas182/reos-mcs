<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone an announcement went to, and when they read it (B6.1b).
 *
 * @property int $id
 * @property int $announcement_id
 * @property int $organisation_id
 * @property int $user_id
 * @property Carbon|null $read_at
 * @property-read Announcement $announcement
 */
final class AnnouncementRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'organisation_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<Announcement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
