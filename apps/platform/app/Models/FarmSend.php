<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One market update sent for an area (B14.3c): what it said, the figures
 * behind it, and how many contacts it went to.
 *
 * @property int $id
 * @property string $uuid
 * @property int $farm_area_id
 * @property int|null $organisation_id
 * @property string $subject
 * @property string $body
 * @property array<string, int|float|null> $stats
 * @property int $recipients
 * @property CarbonImmutable $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class FarmSend extends Model
{
    protected $fillable = ['uuid', 'farm_area_id', 'organisation_id', 'subject', 'body', 'stats', 'recipients', 'sent_at'];

    protected function casts(): array
    {
        return ['stats' => 'array', 'recipients' => 'integer', 'sent_at' => 'immutable_datetime'];
    }
}
