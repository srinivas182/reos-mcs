<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of a ledger transaction (B5.3). Never edited or deleted:
 * corrections are new entries.
 *
 * @property int $id
 * @property string $transaction
 * @property int $ledger_account_id
 * @property string $unit
 * @property int $amount
 * @property int $balance_after
 * @property string $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $memo
 * @property Carbon $created_at
 * @property-read LedgerAccount $account
 */
final class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['transaction', 'ledger_account_id', 'unit', 'amount', 'balance_after', 'reason', 'reference_type', 'reference_id', 'memo'];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Ledger entries are never edited; post a correction.'));
        self::deleting(static fn (): never => throw new LogicException('Ledger entries are never deleted; post a correction.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
