<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One journal entry REOS posted to the brokerage's QuickBooks (B17.3), once per split sheet or payout.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $kind split_sheet or payout
 * @property string $subject_id
 * @property int $amount_cents
 * @property string $status posted or failed
 * @property string|null $ref The journal entry's id in QuickBooks
 * @property string|null $error
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class AccountingExport extends Model
{
    protected $fillable = ['organisation_id', 'kind', 'subject_id', 'amount_cents', 'status', 'ref', 'error', 'posted_at'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }
}
