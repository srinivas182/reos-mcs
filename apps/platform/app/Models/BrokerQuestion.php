<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's question to the virtual broker (A12.1, ADR 0047; agent
 * screens 61 and 62, broker journey screens 11 and 12).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property int $user_id
 * @property int|null $deal_id
 * @property string $question
 * @property string $language
 * @property string $answer
 * @property string $confidence high, medium or low
 * @property list<array{kind: string, title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string}> $sources
 * @property string $status answered, waiting, confirmed or corrected
 * @property string|null $reason unclear, money, legal or asked
 * @property CarbonImmutable|null $escalated_at
 * @property CarbonImmutable|null $due_at
 * @property string|null $broker_answer
 * @property int|null $answered_by
 * @property CarbonImmutable|null $answered_at
 * @property bool $added_to_knowledge
 * @property string $engine
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read User|null $answerer
 * @property-read Deal|null $deal
 * @property-read Organisation $organisation
 */
final class BrokerQuestion extends Model
{
    public const ANSWERED = 'answered';

    public const WAITING = 'waiting';

    public const CONFIRMED = 'confirmed';

    public const CORRECTED = 'corrected';

    public const REASONS = ['unclear', 'money', 'legal', 'asked'];

    protected $fillable = [
        'uuid', 'organisation_id', 'user_id', 'deal_id', 'question', 'language', 'answer', 'confidence', 'sources', 'status', 'reason',
        'escalated_at', 'due_at', 'broker_answer', 'answered_by', 'answered_at', 'added_to_knowledge', 'engine',
    ];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'escalated_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'answered_at' => 'immutable_datetime',
            'added_to_knowledge' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
