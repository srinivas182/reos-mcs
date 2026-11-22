<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BrokerRole;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person's real estate licence in one state (A5.1; agent journey screens
 * 148 and 149). A person can hold licences in several states; each is
 * checked with that state's regulator, or approved by a reviewer, before it
 * counts.
 *
 * @property int $id
 * @property int $user_id
 * @property string $state_code
 * @property LicenceType $type
 * @property string $number
 * @property LicenceStatus $status
 * @property BrokerRole|null $broker_role
 * @property string|null $holder_name The name on the regulator's record
 * @property Carbon|null $licensed_on
 * @property Carbon|null $expires_on
 * @property string|null $sponsor_name
 * @property bool $first_renewal
 * @property string|null $verified_by `trec-licence`, `simulator` or `reviewer`
 * @property Carbon|null $verified_at
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read State $state
 * @property-read User $user
 */
final class Licence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'state_code', 'type', 'number', 'status', 'broker_role', 'holder_name', 'licensed_on', 'expires_on',
        'sponsor_name', 'first_renewal', 'verified_by', 'verified_at', 'reviewed_by', 'review_note',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['first_renewal' => false];

    protected function casts(): array
    {
        return [
            'type' => LicenceType::class,
            'status' => LicenceStatus::class,
            'broker_role' => BrokerRole::class,
            'licensed_on' => 'date',
            'expires_on' => 'date',
            'first_renewal' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_code', 'code');
    }
}
