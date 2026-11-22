<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person's notification choices (B6.2): channels per kind where they
 * changed the default, quiet hours in their time zone, and digests.
 *
 * @property int $user_id
 * @property bool $quiet_enabled
 * @property string $quiet_start HH:MM
 * @property string $quiet_end HH:MM
 * @property string $timezone
 * @property array<string, array<string, bool>>|null $channels
 * @property array<string, string>|null $digests kind => daily or weekly
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class NotificationSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'quiet_enabled', 'quiet_start', 'quiet_end', 'timezone', 'channels', 'digests'];

    protected $attributes = [
        'quiet_enabled' => true,
        'quiet_start' => '21:00',
        'quiet_end' => '07:00',
        'timezone' => 'America/Chicago',
    ];

    protected function casts(): array
    {
        return ['quiet_enabled' => 'boolean', 'channels' => 'array', 'digests' => 'array'];
    }
}
