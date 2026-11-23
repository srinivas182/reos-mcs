<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fix a weak topic (candidate screen 46, A9.2b): a short plan built from the
 * candidate's own misses on one topic.
 *
 * @property int $id
 * @property int $user_id
 * @property string $topic
 * @property int|null $start_percent
 * @property list<array{kind: string, minutes: int, lesson_id?: int|null, seconds?: int|null, title?: string|null, attempt_id?: int|null, questions?: list<int>, done_at?: string|null}> $steps
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 */
final class TopicPlan extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'topic', 'start_percent', 'steps', 'completed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['steps' => 'array', 'start_percent' => 'integer', 'completed_at' => 'datetime'];
    }
}
