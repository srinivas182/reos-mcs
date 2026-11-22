<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\Payouts\PayeeRole;
use App\Billing\Payouts\PayoutLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one paid order earns one payee (B5.2b). `gross` is the payee's part
 * of the sale before the platform fee (the whole sale for the seller),
 * `platform_fee` REOS's part, `amount` what is paid out.
 *
 * @property int $id
 * @property int $order_id
 * @property string $payee_type
 * @property int $payee_id
 * @property PayeeRole $role
 * @property int $gross_cents
 * @property int $platform_fee_cents
 * @property int $amount_cents
 * @property PayoutLineStatus $status
 * @property int|null $payout_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Payout|null $payout
 */
final class PayoutLine extends Model
{
    protected $fillable = ['order_id', 'payee_type', 'payee_id', 'role', 'gross_cents', 'platform_fee_cents', 'amount_cents', 'status', 'payout_id'];

    protected function casts(): array
    {
        return ['role' => PayeeRole::class, 'status' => PayoutLineStatus::class];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
