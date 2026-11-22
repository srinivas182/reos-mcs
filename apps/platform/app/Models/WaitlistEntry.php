<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone waiting for a state to open (mobile journey screen 3).
 *
 * @property int $id
 * @property string $state_code
 * @property int|null $user_id
 * @property string|null $device_id
 * @property string|null $email
 * @property Carbon|null $notified_at
 * @property-read User|null $user
 */
final class WaitlistEntry extends Model
{
    protected $table = 'state_waitlist';

    /** @var list<string> */
    protected $fillable = ['state_code', 'user_id', 'device_id', 'email'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }
}
