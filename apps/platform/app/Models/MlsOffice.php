<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An MLS office (B10.1).
 *
 * @property int $id
 * @property string $integration
 * @property string $office_key
 * @property string|null $office_mls_id
 * @property string $name
 * @property string|null $city
 * @property CarbonImmutable|null $modified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MlsOffice extends Model
{
    protected $fillable = ['integration', 'office_key', 'office_mls_id', 'name', 'city', 'modified_at'];

    protected function casts(): array
    {
        return ['modified_at' => 'datetime'];
    }
}
