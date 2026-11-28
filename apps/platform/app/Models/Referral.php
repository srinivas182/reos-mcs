<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client one agent refers to another (B14.3b, ADR 0067): between
 * brokers, with the fee they agreed; REOS takes no fee.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $organisation_id
 * @property int $from_user_id
 * @property int|null $to_user_id
 * @property string $to_name
 * @property string $to_email
 * @property string|null $to_brokerage
 * @property string|null $from_broker
 * @property string|null $to_broker
 * @property string $client_name
 * @property string|null $client_email
 * @property string|null $client_phone
 * @property string $kind buyer or seller
 * @property string|null $area
 * @property string|null $note
 * @property int|null $fee_percent
 * @property string $status sent, emailed, accepted, declined or closed
 * @property int|null $contact_id
 * @property CarbonImmutable|null $answered_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $sender
 * @property-read User|null $receiver
 */
final class Referral extends Model
{
    protected $fillable = ['uuid', 'organisation_id', 'from_user_id', 'to_user_id', 'to_name', 'to_email', 'to_brokerage', 'from_broker', 'to_broker', 'client_name', 'client_email', 'client_phone', 'kind', 'area', 'note', 'fee_percent', 'status', 'contact_id', 'answered_at', 'closed_at'];

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    protected function casts(): array
    {
        return ['fee_percent' => 'integer', 'answered_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }
}
