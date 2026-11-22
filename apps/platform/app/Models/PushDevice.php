<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A phone that gets push notifications (B6.2b): its Expo push token, tied to
 * the phone's sign-in so signing out removes it.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $token
 * @property string $platform ios or android
 * @property string|null $name
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PushDevice extends Model
{
    protected $fillable = ['user_id', 'personal_access_token_id', 'token', 'platform', 'name', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
