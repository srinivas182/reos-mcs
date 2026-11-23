<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One nightly import of a regulator's public data (B10.3).
 *
 * @property int $id
 * @property string $integration
 * @property string $source simulator or live
 * @property int $rows
 * @property int $removed
 * @property string|null $error
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 */
final class RegulatorImport extends Model
{
    public $timestamps = false;

    protected $fillable = ['integration', 'source', 'rows', 'removed', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['rows' => 'integer', 'removed' => 'integer', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }
}
