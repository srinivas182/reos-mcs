<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A service provider's professional licence (B10.3).
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind trec_inspector, tdi, nmls, or business_id (a photo ID, B15.2)
 * @property string $number
 * @property string $status verified, inactive, review or rejected
 * @property string|null $name_on_record
 * @property string|null $licence_type
 * @property CarbonImmutable|null $expires_on
 * @property string|null $checked_via
 * @property string|null $review_note
 * @property string|null $document_path The photo ID for business_id
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class ProviderLicence extends Model
{
    public const VERIFIED = 'verified';

    public const INACTIVE = 'inactive';

    public const REVIEW = 'review';

    public const REJECTED = 'rejected';

    protected $fillable = ['user_id', 'kind', 'number', 'status', 'name_on_record', 'licence_type', 'expires_on', 'checked_via', 'review_note', 'reviewed_by', 'reviewed_at', 'document_path'];

    protected function casts(): array
    {
        return ['expires_on' => 'immutable_date', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
