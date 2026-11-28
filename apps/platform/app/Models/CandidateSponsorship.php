<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI help a brokerage sponsors for a candidate through an accepted offer (A17.2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $brokerage_id
 * @property int $candidate_id
 * @property int|null $broker_offer_id
 * @property int $cap
 * @property int $used
 * @property Carbon|null $ended_at
 * @property string|null $end_reason passed, other_sponsor or withdrawn
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $brokerage
 * @property-read User $candidate
 */
final class CandidateSponsorship extends Model
{
    protected $fillable = ['uuid', 'brokerage_id', 'candidate_id', 'broker_offer_id', 'cap', 'used', 'ended_at', 'end_reason'];

    protected function casts(): array
    {
        return ['cap' => 'integer', 'used' => 'integer', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function brokerage(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'brokerage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
