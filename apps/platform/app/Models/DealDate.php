<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A deal's key date (B9.1): a to-do on that day, linked to the deal, with
 * pushes at 72 and 24 hours.
 *
 * @property int $id
 * @property int $deal_id
 * @property string $kind
 * @property string|null $label
 * @property Carbon $due_on
 * @property string|null $external_id
 * @property int|null $todo_id
 * @property Carbon|null $alerted_72_at
 * @property Carbon|null $alerted_24_at
 * @property Carbon|null $done_at
 * @property-read Deal $deal
 * @property-read Todo|null $todo
 */
final class DealDate extends Model
{
    public const KINDS = ['option_period', 'financing', 'appraisal', 'inspection', 'survey', 'title_commitment', 'closing', 'other'];

    /** @var list<string> */
    protected $fillable = ['deal_id', 'kind', 'label', 'due_on', 'external_id', 'todo_id', 'alerted_72_at', 'alerted_24_at', 'done_at'];

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<Todo, $this> */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_on' => 'date', 'alerted_72_at' => 'datetime', 'alerted_24_at' => 'datetime', 'done_at' => 'datetime'];
    }
}
