<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A CE renewal reminder sent for one licence period (A11.2): 60, 30 or 7
 * days before while hours are missing, or "Renewal ready" once complete.
 *
 * @property int $id
 * @property int $licence_id
 * @property string $due_on Y-m-d (kept as text so lookups match on every database)
 * @property string $kind days_60, days_30, days_7 or ready
 * @property CarbonImmutable $sent_at
 */
final class CeReminder extends Model
{
    public $timestamps = false;

    protected $fillable = ['licence_id', 'due_on', 'kind', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime'];
    }
}
