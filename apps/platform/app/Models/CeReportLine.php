<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One completion in a CE report (A11.3): included, reported or rejected.
 *
 * @property int $id
 * @property int $ce_report_id
 * @property int $ce_completion_id
 * @property string|null $licence_number
 * @property string|null $course_number
 * @property string $status included, reported or rejected
 * @property string|null $reason Why the regulator rejected it
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CeReport $report
 * @property-read CeCompletion $completion
 */
final class CeReportLine extends Model
{
    protected $fillable = ['ce_report_id', 'ce_completion_id', 'licence_number', 'course_number', 'status', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<CeReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(CeReport::class, 'ce_report_id');
    }

    /** @return BelongsTo<CeCompletion, $this> */
    public function completion(): BelongsTo
    {
        return $this->belongsTo(CeCompletion::class, 'ce_completion_id');
    }
}
