<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * A signed-in device (one token per installed app). Shown in "Your devices".
 *
 * @property int $id
 * @property string $name
 * @property string|null $device_id
 * @property string|null $platform
 * @property string|null $app_version
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 */
final class PersonalAccessToken extends SanctumToken
{
    /** Signed out after this many days unused. */
    public const IDLE_DAYS = 30;

    /** @var list<string> */
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'device_id', 'platform', 'app_version'];

    public function isIdle(): bool
    {
        return ($this->last_used_at ?? $this->created_at ?? now())->lt(now()->subDays(self::IDLE_DAYS));
    }
}
