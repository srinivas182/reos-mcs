<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A licence in a regulator's public data (B10.3): TREC or TDI, imported nightly.
 *
 * @property int $id
 * @property string $authority TREC or TDI
 * @property string $kind agents, inspectors or insurance
 * @property string $licence_type
 * @property string $number
 * @property string $number_digits
 * @property string $name
 * @property string|null $status
 * @property bool $active
 * @property CarbonImmutable|null $licensed_on
 * @property CarbonImmutable|null $expires_on
 * @property int|null $education_status
 * @property int|null $mce_status
 * @property bool $designated_supervisor
 * @property string|null $sponsor_number
 * @property string|null $sponsor_name
 * @property string|null $npn
 * @property string|null $qualification
 * @property CarbonImmutable $imported_at
 */
final class RegulatorRecord extends Model
{
    public $timestamps = false;

    protected $fillable = ['authority', 'kind', 'licence_type', 'number', 'number_digits', 'name', 'status', 'active', 'licensed_on', 'expires_on', 'education_status', 'mce_status', 'designated_supervisor', 'sponsor_number', 'sponsor_name', 'npn', 'qualification', 'imported_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean', 'licensed_on' => 'immutable_date', 'expires_on' => 'immutable_date', 'education_status' => 'integer',
            'mce_status' => 'integer', 'designated_supervisor' => 'boolean', 'imported_at' => 'immutable_datetime',
        ];
    }
}
