<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A ledger account (B5.3): whose it is, what it counts (`usd` cents,
 * `credit`, `seat` or `point`) and its running balance.
 *
 * @property int $id
 * @property string $key
 * @property string $type
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string $unit
 * @property int $balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class LedgerAccount extends Model
{
    protected $fillable = ['key', 'type', 'owner_type', 'owner_id', 'unit', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'owner_id' => 'integer'];
    }
}
