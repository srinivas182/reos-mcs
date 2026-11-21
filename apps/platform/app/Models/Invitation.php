<?php

declare(strict_types=1);

namespace App\Models;

use App\Authorization\Role;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation into a tenant (A4.3a).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $team_id
 * @property Role $role
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $name
 * @property string $locale
 * @property string $token_hash
 * @property string $code_hash
 * @property array{token: string, code: string} $secret
 * @property string $status
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $reminded_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read Organisation|null $team
 * @property-read User|null $inviter
 */
#[Fillable(['organisation_id', 'team_id', 'role', 'email', 'phone', 'name', 'locale', 'token_hash', 'code_hash', 'secret', 'status', 'invited_by', 'expires_at', 'sent_at', 'reminded_at', 'accepted_at', 'accepted_by'])]
#[Hidden(['token_hash', 'code_hash', 'secret'])]
final class Invitation extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const REVOKED = 'revoked';

    /** Pending and not expired. */
    public function open(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isFuture();
    }

    /** What screens show: pending, expired, accepted or revoked. */
    public function state(): string
    {
        return $this->status === self::PENDING && ! $this->expires_at->isFuture() ? 'expired' : $this->status;
    }

    /** Where the role is given. */
    public function target(): Organisation
    {
        return $this->team ?? $this->organisation;
    }

    public function contact(): string
    {
        return (string) ($this->email ?? $this->phone);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'team_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'secret' => 'encrypted:array',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'reminded_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
