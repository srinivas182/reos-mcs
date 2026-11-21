<?php

declare(strict_types=1);

namespace App\Models;

use App\Authorization\Role;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\SupportAccessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * REOS support's request to work inside a tenant, and the owner's answer
 * (A4.1c, ADR 0015).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $requested_by
 * @property string $reason
 * @property Role $role
 * @property SupportAccessStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read User $requester
 * @property-read User|null $decider
 */
#[Fillable(['organisation_id', 'requested_by', 'reason', 'role', 'status', 'decided_by', 'decided_at', 'started_at', 'ends_at', 'ended_at'])]
final class SupportAccessGrant extends Model
{
    use BelongsToTenant;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => SupportAccessStatus::class,
            'decided_at' => 'datetime',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
