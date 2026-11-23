<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Replication state of one MLS feed (B10.1): platform-wide, one row per MLS.
 *
 * @property int $id
 * @property string $integration
 * @property string|null $source simulator or reso
 * @property array<string, string> $cursors RESO resource => last ModificationTimestamp seen
 * @property CarbonImmutable|null $reconciled_at
 * @property CarbonImmutable|null $synced_at
 * @property string|null $last_error
 * @property int $failures
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MlsFeed extends Model
{
    protected $fillable = ['integration', 'source', 'cursors', 'reconciled_at', 'synced_at', 'last_error', 'failures'];

    protected $attributes = ['cursors' => '{}', 'failures' => 0];

    protected function casts(): array
    {
        return ['cursors' => 'array', 'reconciled_at' => 'datetime', 'synced_at' => 'datetime', 'failures' => 'integer'];
    }
}
