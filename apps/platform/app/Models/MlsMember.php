<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An MLS member record (B10.1): agents link to theirs by TREC licence.
 *
 * @property int $id
 * @property string $integration
 * @property string $member_key
 * @property string|null $member_mls_id
 * @property string $name
 * @property string|null $licence
 * @property string|null $licence_digits
 * @property string|null $office_key
 * @property bool $active
 * @property CarbonImmutable|null $modified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read MlsOffice|null $office
 */
final class MlsMember extends Model
{
    protected $fillable = ['integration', 'member_key', 'member_mls_id', 'name', 'licence', 'licence_digits', 'office_key', 'active', 'modified_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'modified_at' => 'datetime'];
    }

    /** @return BelongsTo<MlsOffice, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(MlsOffice::class, 'office_key', 'office_key')->whereColumn('mls_offices.integration', 'mls_members.integration');
    }
}
