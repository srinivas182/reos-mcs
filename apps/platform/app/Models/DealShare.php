<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deal shared with a transaction coordinator (B17.1), by switch, until closing.
 *
 * @property int $id
 * @property string $uuid
 * @property int $deal_id
 * @property int $agent_id
 * @property int $coordinator_id
 * @property array{dates: bool, documents: bool, parties: bool, messages: bool} $switches
 * @property list<string>|null $checklist
 * @property string $status active or ended
 * @property Carbon|null $ended_at
 * @property string|null $end_reason closed, cancelled or removed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Deal $deal
 * @property-read User $agent
 * @property-read User $coordinator
 */
final class DealShare extends Model
{
    public const SWITCHES = ['dates', 'documents', 'parties', 'messages'];

    /** Decision 4: documents and dates on, parties and messages off. */
    public const DEFAULTS = ['dates' => true, 'documents' => true, 'parties' => false, 'messages' => false];

    protected $fillable = ['uuid', 'deal_id', 'agent_id', 'coordinator_id', 'switches', 'checklist', 'status', 'ended_at', 'end_reason'];

    protected function casts(): array
    {
        return ['switches' => 'array', 'checklist' => 'array', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }
}
