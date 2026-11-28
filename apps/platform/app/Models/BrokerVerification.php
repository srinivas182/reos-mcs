<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A broker licence checked against TREC before a brokerage is created
 * (A15.1a, ADR 0069): whose licence, which brokerage it's the designated
 * broker of, and how many agents TREC lists under it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $licence_number
 * @property string $licence_name
 * @property string $brokerage_number
 * @property string $brokerage_name
 * @property string $brokerage_kind entity or individual
 * @property int $sponsored
 * @property string $role designated_broker
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class BrokerVerification extends Model
{
    protected $fillable = ['uuid', 'user_id', 'licence_number', 'licence_name', 'brokerage_number', 'brokerage_name', 'brokerage_kind', 'sponsored', 'role', 'used_at'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['sponsored' => 'integer', 'used_at' => 'immutable_datetime'];
    }
}
