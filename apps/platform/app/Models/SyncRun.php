<?php

declare(strict_types=1);

namespace App\Models;

use App\Connections\SyncTrigger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * One sync job's work on one stream: pages fetched, records written, outcome.
 * Kept for 30 days for support and health.
 *
 * @property int $id
 * @property int $connection_id
 * @property string $stream
 * @property SyncTrigger $trigger
 * @property string|null $outcome
 * @property int $pages
 * @property int $upserted
 * @property int $removed
 * @property string|null $error
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
final class SyncRun extends Model
{
    use Prunable;

    public const OUTCOME_SYNCED = 'synced';

    public const OUTCOME_CONTINUED = 'continued';

    public const OUTCOME_FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = ['connection_id', 'stream', 'trigger', 'outcome', 'pages', 'upserted', 'removed', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'trigger' => SyncTrigger::class,
            'pages' => 'integer',
            'upserted' => 'integer',
            'removed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('started_at', '<', now()->subDays(30));
    }
}
