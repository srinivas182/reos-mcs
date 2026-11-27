<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One role-play (A14.1a): the agent and a persona taking turns, then a
 * score and three fixes.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $skill_assessment_id
 * @property 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up' $type
 * @property 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead' $persona
 * @property string $language
 * @property list<array{role: 'persona'|'agent', text: string}> $turns
 * @property string $status active or scored
 * @property int|null $score 0–100
 * @property array{structure: int, questions: int, objections: int, clarity: int, ask: int}|null $scores each 0–20
 * @property list<array{dimension: 'structure'|'questions'|'objections'|'clarity'|'ask', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}>|null $fixes
 * @property string $engine claude or script
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class RolePlay extends Model
{
    protected $fillable = ['uuid', 'user_id', 'skill_assessment_id', 'type', 'persona', 'language', 'turns', 'status', 'score', 'scores', 'fixes', 'engine', 'finished_at'];

    protected function casts(): array
    {
        return ['turns' => 'array', 'scores' => 'array', 'fixes' => 'array', 'score' => 'integer', 'finished_at' => 'immutable_datetime'];
    }
}
