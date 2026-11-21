<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The Simulator's copy of a tool's own data for one demo connection (ADR
 * 0009, B4.1): what "Google" holds, so REOS can write to it and a tester can
 * change it "in the tool" (`reos:connections:simulate --change-event`).
 *
 * @property int $id
 * @property int $connection_id
 * @property string $stream
 * @property string $container For calendars, the calendar id
 * @property string $external_id
 * @property array<string, mixed> $data
 * @property string $etag
 * @property bool $deleted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SimulatorRecord extends Model
{
    protected $fillable = ['connection_id', 'stream', 'container', 'external_id', 'data', 'etag', 'deleted'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'deleted' => 'boolean',
        ];
    }
}
