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
 * @property string $kind manual or faq
 * @property string|null $heading
 * @property int|null $page
 * @property int $position
 * @property string $content
 * @property int|null $broker_question_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
final class PolicyPassage extends Model
{
    use SoftDeletes;

    public const MANUAL = 'manual';

    public const FAQ = 'faq';

    protected $fillable = ['organisation_id', 'policy_manual_id', 'kind', 'heading', 'page', 'position', 'content', 'broker_question_id'];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
