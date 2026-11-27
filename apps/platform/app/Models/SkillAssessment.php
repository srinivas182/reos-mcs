<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An agent's skills assessment (A14.1a; agent screen 7): five short
 * role-play rounds, one per type.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property list<array{type: 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up', score: int}>|null $results
 * @property list<'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up'>|null $weakest
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class SkillAssessment extends Model
{
    protected $fillable = ['uuid', 'user_id', 'results', 'weakest', 'completed_at'];

    protected function casts(): array
    {
        return ['results' => 'array', 'weakest' => 'array', 'completed_at' => 'immutable_datetime'];
    }
}
