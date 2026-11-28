<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An envelope sent from a deal through the agent's own DocuSign (B9.3,
 * ADR 0038). Nothing is sent until the agent presses Send in DocuSign.
 *
 * @property int $id
 * @property string $uuid
 * @property int $deal_id
 * @property int $user_id
 * @property int|null $organisation_id
 * @property int|null $connection_id
 * @property string|null $envelope_id
 * @property string $subject
 * @property string $status draft, sent, delivered, completed, declined or voided
 * @property list<array{name: string, email: string, routing_order: int, status: string, at: string|null, client_user_id?: string|null}> $signers status: waiting, sent, delivered, signed or declined
 * @property list<string> $documents
 * @property Carbon|null $sent_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $filed_at
 * @property string|null $filed_in
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Deal $deal
 * @property-read User $user
 * @property-read Connection|null $connection
 */
final class SignatureRequest extends Model
{
    public const STATUSES = ['draft', 'sent', 'delivered', 'completed', 'declined', 'voided'];

    /** Finished: nothing more will change. */
    public const FINAL = ['completed', 'declined', 'voided'];

    /** @var list<string> */
    protected $fillable = ['uuid', 'deal_id', 'user_id', 'organisation_id', 'connection_id', 'envelope_id', 'subject', 'status', 'signers', 'documents', 'sent_at', 'completed_at', 'filed_at', 'filed_in'];

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['signers' => 'array', 'documents' => 'array', 'sent_at' => 'datetime', 'completed_at' => 'datetime', 'filed_at' => 'datetime'];
    }
}
