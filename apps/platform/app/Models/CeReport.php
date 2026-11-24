<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school's CE report to the regulator for a date range (A11.3).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property string $state_code
 * @property string $from_on Y-m-d
 * @property string $to_on Y-m-d
 * @property string $method file or reos
 * @property string $status pending_upload, submitted or reported
 * @property string|null $reference The regulator's confirmation reference
 * @property int|null $created_by
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reported_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, CeReportLine> $lines
 */
final class CeReport extends Model
{
    public const METHODS = ['file', 'reos'];

    protected $fillable = ['uuid', 'organisation_id', 'state_code', 'from_on', 'to_on', 'method', 'status', 'reference', 'created_by', 'submitted_at', 'reported_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'reported_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CeReportLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CeReportLine::class);
    }
}
