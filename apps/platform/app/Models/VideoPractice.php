<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A reviewed video practice (A14.1b): the words and length only; the video
 * stays with the agent.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property 'listing_pitch'|'video_intro' $kind
 * @property string $language
 * @property string $transcript
 * @property int $duration_seconds
 * @property int $words
 * @property int $wpm
 * @property list<array{word: string, count: int}> $fillers
 * @property int $score
 * @property array{pace: int, fillers: int, opening: int, proof: int, close: int} $scores
 * @property list<array{dimension: 'pace'|'fillers'|'opening'|'proof'|'close', text: string, example: string, lesson: 'listing_presentation'|'discovery_questions'|'objection_handling'|'clear_speaking'|'asking_for_business'}> $fixes
 * @property list<array{code: string, phrase: string|null}> $compliance
 * @property string $engine
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class VideoPractice extends Model
{
    protected $fillable = ['uuid', 'user_id', 'kind', 'language', 'transcript', 'duration_seconds', 'words', 'wpm', 'fillers', 'score', 'scores', 'fixes', 'compliance', 'engine'];

    protected function casts(): array
    {
        return ['fillers' => 'array', 'scores' => 'array', 'fixes' => 'array', 'compliance' => 'array', 'duration_seconds' => 'integer', 'words' => 'integer', 'wpm' => 'integer', 'score' => 'integer'];
    }
}
