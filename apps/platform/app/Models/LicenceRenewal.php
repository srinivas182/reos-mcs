<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Renewing a licence for one expiry (A11.2, agent screen 106): when the
 * agent says they renewed with the regulator, and when REOS saw the new
 * expiry on the regulator's record.
 *
 * @property int $id
 * @property int $licence_id
 * @property string $due_on Y-m-d (kept as text so lookups match on every database)
 * @property CarbonImmutable|null $renewed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property string|null $renewed_until Y-m-d
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class LicenceRenewal extends Model
{
    protected $fillable = ['licence_id', 'due_on', 'renewed_at', 'confirmed_at', 'renewed_until'];

    protected function casts(): array
    {
        return ['renewed_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }
}
