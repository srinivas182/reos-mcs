<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One weekly transfer to a connected account (B5.2b): the sum of its
 * pending lines, sent once (idempotency key = uuid).
 *
 * @property int $id
 * @property string $uuid
 * @property int $connected_account_id
 * @property string $environment
 * @property int $amount_cents
 * @property string $currency
 * @property string $status sent or failed
 * @property string|null $transfer_id
 * @property string|null $failure
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConnectedAccount $account
 */
final class Payout extends Model
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = ['uuid', 'connected_account_id', 'environment', 'amount_cents', 'currency', 'status', 'transfer_id', 'failure'];

    /** @return BelongsTo<ConnectedAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class, 'connected_account_id');
    }

    /** @return HasMany<PayoutLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayoutLine::class);
    }
}
