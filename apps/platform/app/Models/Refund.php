<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A refund REOS made (B5.3): full or partial, why, whether it was within
 * the refund policy, and the gateway's refund id.
 *
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property int $amount_cents
 * @property bool $full
 * @property string $reason
 * @property string $status done or failed
 * @property string|null $gateway_refund_id
 * @property bool $within_policy
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 */
final class Refund extends Model
{
    protected $fillable = ['uuid', 'order_id', 'amount_cents', 'full', 'reason', 'status', 'gateway_refund_id', 'within_policy', 'created_by'];

    protected function casts(): array
    {
        return ['full' => 'boolean', 'within_policy' => 'boolean'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
