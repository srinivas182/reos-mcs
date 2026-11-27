<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One agent's ratings of the other side's agent after a verified closing (A13.3b).
 *
 * @property int $id
 * @property string $uuid
 * @property int $deal_id
 * @property int $rater_id
 * @property int $ratee_id
 * @property int $communication 1–5
 * @property int $responsiveness 1–5
 * @property int $professionalism 1–5
 * @property string|null $comment
 * @property string|null $response the rated agent's reply
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable|null $reported_at
 * @property string|null $report_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Deal $deal
 */
final class AgentFeedback extends Model
{
    protected $table = 'agent_feedback';

    protected $fillable = ['uuid', 'deal_id', 'rater_id', 'ratee_id', 'communication', 'responsiveness', 'professionalism', 'comment', 'response', 'responded_at', 'reported_at', 'report_reason'];

    protected function casts(): array
    {
        return [
            'communication' => 'integer', 'responsiveness' => 'integer', 'professionalism' => 'integer',
            'responded_at' => 'immutable_datetime', 'reported_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
