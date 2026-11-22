<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How a payment gateway is used (B5.2a, screen 66): states, kinds of
 * product, order, tenant-owned accounts and the kill switch.
 *
 * @property string $gateway
 * @property list<string>|null $states
 * @property list<string>|null $used_for
 * @property bool $is_default
 * @property int $position
 * @property bool $tenant_accounts
 * @property bool $paused
 * @property string|null $paused_reason
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class GatewaySetting extends Model
{
    protected $primaryKey = 'gateway';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['gateway', 'states', 'used_for', 'is_default', 'position', 'tenant_accounts', 'paused', 'paused_reason', 'updated_by'];

    protected function casts(): array
    {
        return [
            'states' => 'array',
            'used_for' => 'array',
            'is_default' => 'boolean',
            'position' => 'integer',
            'tenant_accounts' => 'boolean',
            'paused' => 'boolean',
        ];
    }
}
