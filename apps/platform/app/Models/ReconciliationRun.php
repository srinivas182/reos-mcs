<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One day's reconciliation of the ledger with a gateway (B5.3): how many
 * money entries matched the gateway's balance transactions, and each difference.
 *
 * @property int $id
 * @property Carbon $day
 * @property string $gateway
 * @property string $environment
 * @property int $matched
 * @property list<array{kind: string, reference: string, ledger: int|null, gateway: int|null}>|null $differences
 * @property string $status matched, differences or failed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ReconciliationRun extends Model
{
    protected $fillable = ['day', 'gateway', 'environment', 'matched', 'differences', 'status'];

    protected function casts(): array
    {
        return ['day' => 'date', 'differences' => 'array', 'matched' => 'integer'];
    }
}
