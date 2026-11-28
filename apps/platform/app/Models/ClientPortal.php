<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's private link to their deal (B14.2, ADR 0065).
 *
 * @property int $id
 * @property string $uuid
 * @property int $deal_id
 * @property int|null $organisation_id
 * @property int $user_id the agent
 * @property string $name
 * @property string|null $email
 * @property string $language en or es
 * @property string $token_hash
 * @property string $token encrypted
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Deal $deal
 * @property-read User $user
 */
final class ClientPortal extends Model
{
    protected $fillable = ['uuid', 'deal_id', 'organisation_id', 'user_id', 'name', 'email', 'language', 'token_hash', 'token', 'expires_at', 'revoked_at', 'opened_at', 'sent_at'];

    protected $hidden = ['token_hash', 'token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'opened_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function open(?CarbonImmutable $now = null): bool
    {
        return $this->revoked_at === null && $this->expires_at->greaterThan($now ?? CarbonImmutable::now());
    }
}
