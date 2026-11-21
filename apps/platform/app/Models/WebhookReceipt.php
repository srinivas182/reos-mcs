<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * Each webhook delivery seen, so a repeat (tools retry) is handled once.
 *
 * @property int $id
 * @property string $integration_id
 * @property string $delivery_key
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
final class WebhookReceipt extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $fillable = ['integration_id', 'delivery_key', 'received_at', 'processed_at'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('received_at', '<', now()->subDays(7));
    }
}
