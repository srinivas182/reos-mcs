<?php

declare(strict_types=1);

namespace App\Models;

use App\Integrations\IntegrationMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How one integration runs. No row means the default (demo data).
 *
 * @property string $integration_id
 * @property IntegrationMode $mode
 * @property int $consecutive_failures
 * @property Carbon|null $paused_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['integration_id', 'mode', 'consecutive_failures', 'paused_at', 'updated_by'])]
final class IntegrationSetting extends Model
{
    protected $primaryKey = 'integration_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var array<string, mixed> */
    protected $attributes = ['consecutive_failures' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mode' => IntegrationMode::class, 'paused_at' => 'datetime'];
    }
}
