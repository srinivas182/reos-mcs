<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's contract review (A12.2, ADR 0048; agent screens 63–66, web
 * screen 15). Private to the agent; the broker sees what's sent to them.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $organisation_id
 * @property int|null $deal_id
 * @property int|null $deal_file_id
 * @property int|null $previous_id
 * @property string $file_name
 * @property string $path
 * @property string $mime
 * @property string|null $form
 * @property string $status reviewing, done or failed
 * @property string $step read, terms, checks, compare or done
 * @property int $pages
 * @property array<int, string>|null $page_text page number => text (encrypted)
 * @property array<string, array{code: string, value: string, page: int|null, previous?: string|null}>|null $terms
 * @property list<string>|null $addenda
 * @property list<array{code: string, severity: 'high'|'check', title: string, detail: string, page: int|null, date: string|null, citation: string|null, url: string|null}>|null $findings
 * @property list<array{code: string, kind: string, label: string, due_on: string, page: int|null, basis: string}>|null $deadlines
 * @property list<array{question: string, answer: string, pages: list<int>, note: string, asked_at: string}>|null $questions
 * @property CarbonImmutable|null $effective_on
 * @property string $language
 * @property string $engine
 * @property int $credits
 * @property string|null $error
 * @property CarbonImmutable|null $deadlines_added_at
 * @property int|null $broker_question_id
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Deal|null $deal
 * @property-read BrokerQuestion|null $brokerQuestion
 */
final class ContractReview extends Model
{
    public const STEPS = ['read', 'terms', 'checks', 'compare', 'done'];

    protected $fillable = [
        'uuid', 'user_id', 'organisation_id', 'deal_id', 'deal_file_id', 'previous_id', 'file_name', 'path', 'mime', 'form', 'status', 'step', 'pages',
        'page_text', 'terms', 'addenda', 'findings', 'deadlines', 'questions', 'effective_on', 'language', 'engine', 'credits', 'error', 'deadlines_added_at',
        'broker_question_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'page_text' => 'encrypted:array',
            'terms' => 'array',
            'addenda' => 'array',
            'findings' => 'array',
            'deadlines' => 'array',
            'questions' => 'array',
            'effective_on' => 'immutable_date',
            'pages' => 'integer',
            'credits' => 'integer',
            'deadlines_added_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<BrokerQuestion, $this> */
    public function brokerQuestion(): BelongsTo
    {
        return $this->belongsTo(BrokerQuestion::class);
    }
}
