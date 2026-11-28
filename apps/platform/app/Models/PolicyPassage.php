<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A passage the virtual broker answers from (A12.1, ADR 0047): part of the
 * current policy manual (with its page and heading), or an entry in the
 * brokerage FAQ made from a broker's answer.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $policy_manual_id
 * @property string $kind manual, faq or policy (a brokerage rule written in REOS, A15.2)
 * @property string|null $heading
 * @property int|null $page
 * @property int $position
 * @property string $content
 * @property int|null $broker_question_id
 * @property array{rule: string, citation: string, url: string|null, excerpt: string, rule_text: string}|null $conflict The TREC rule it contradicts (A15.2)
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class PolicyPassage extends Model
{
    use SoftDeletes;

    public const MANUAL = 'manual';

    public const FAQ = 'faq';

    /** A brokerage rule typed in REOS (A15.2). */
    public const POLICY = 'policy';

    protected $fillable = ['organisation_id', 'policy_manual_id', 'kind', 'heading', 'page', 'position', 'content', 'broker_question_id', 'conflict'];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'position' => 'integer',
            'conflict' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
