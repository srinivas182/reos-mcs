<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\HostnameState;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tenant's own domain, for example learn.lonestarrealty.com (A4.2b).
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $hostname
 * @property string $provider_id
 * @property string $status
 * @property string $ssl
 * @property list<array{type: string, name: string, value: string}> $records
 * @property string|null $last_error
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $verified_at
 * @property int|null $added_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 */
#[Fillable(['organisation_id', 'hostname', 'provider_id', 'status', 'ssl', 'records', 'last_error', 'last_checked_at', 'verified_at', 'added_by'])]
final class TenantDomain extends Model
{
    use BelongsToTenant;

    public function live(): bool
    {
        return $this->status === HostnameState::ACTIVE && $this->ssl === HostnameState::ACTIVE;
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['records' => 'array', 'last_checked_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
