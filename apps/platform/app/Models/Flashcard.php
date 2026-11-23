<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A card in a candidate's deck (screen 31, A9.2d): from a course's approved
 * flashcards, saved from a check-in, Ask or answer review, or a speed-round
 * miss; with its FSRS schedule.
 *
 * @property int $id
 * @property int $user_id
 * @property string $source course, checkin, ask, review or speed
 * @property int|null $review_item_id
 * @property int|null $quiz_question_id
 * @property string|null $topic
 * @property array<string, array{front: string, back: string}> $content By language
 * @property float|null $stability
 * @property float|null $difficulty
 * @property int $reps
 * @property int $lapses
 * @property Carbon|null $due_at
 * @property Carbon|null $last_reviewed_at
 */
final class Flashcard extends Model
{
    public const SOURCES = ['course', 'checkin', 'ask', 'review', 'speed'];

    /** @var list<string> */
    protected $fillable = ['user_id', 'source', 'review_item_id', 'quiz_question_id', 'topic', 'content', 'stability', 'difficulty', 'reps', 'lapses', 'due_at', 'last_reviewed_at'];

    /** @return array{front: string, back: string} */
    public function in(string $language): array
    {
        return $this->content[$language] ?? $this->content['en'] ?? (array_values($this->content)[0] ?? ['front' => '', 'back' => '']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['content' => 'array', 'stability' => 'float', 'difficulty' => 'float', 'reps' => 'integer', 'lapses' => 'integer', 'due_at' => 'datetime', 'last_reviewed_at' => 'datetime'];
    }
}
