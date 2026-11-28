<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A brokerage's offer to a candidate who opted in (A10.3b, screen 17).
 * The candidate's contact details reach the brokerage only on Accept.
 *
 * @property int $id
 * @property string $uuid
 * @property int $brokerage_id The brokerage making the offer (not a tenant column: the offer spans the brokerage and a candidate)
 * @property int $candidate_id
 * @property int|null $made_by
 * @property string $office_city
 * @property int $split_percent The agent's share, in percent
 * @property int $monthly_fee_cents
 * @property string|null $fees
 * @property string|null $training
 * @property bool $mentoring
 * @property string|null $message
 * @property string $status open, accepted, declined or withdrawn
 * @property int $ai_credits AI credits the brokerage sponsors (A17.2), 0 for none
 * @property CarbonImmutable|null $answered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organisation $organisation
 * @property-read User $candidate
 */
final class BrokerOffer extends Model
{
    public const OPEN = 'open';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const WITHDRAWN = 'withdrawn';

    protected $fillable = ['uuid', 'brokerage_id', 'candidate_id', 'made_by', 'office_city', 'split_percent', 'monthly_fee_cents', 'fees', 'training', 'mentoring', 'message', 'status', 'answered_at', 'ai_credits'];

    protected function casts(): array
    {
        return ['split_percent' => 'integer', 'monthly_fee_cents' => 'integer', 'mentoring' => 'boolean', 'answered_at' => 'datetime', 'ai_credits' => 'integer'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'brokerage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
