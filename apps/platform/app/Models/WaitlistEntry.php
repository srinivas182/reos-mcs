<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
 */
final class WaitlistEntry extends Model
{
    protected $table = 'state_waitlist';

    /** @var list<string> */
    protected $fillable = ['state_code', 'user_id', 'device_id', 'email'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }
}
