<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuizTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An exam-style question with four options, an explanation and its source.
 * `content` holds each language: {"en": {question, options, explanation}, "es": {...}}.
 *
 * @property int $id
 * @property string|null $state_code
 * @property QuizTopic $topic
 * @property array<string, array{question: string, options: list<string>, explanation: string}> $content
 * @property int $correct_option
 * @property string $source
 * @property bool $is_sample
 * @property int|null $organisation_id The school that owns it (A8.1); null for REOS's bank
 * @property int|null $course_id
 * @property int|null $review_item_id
 * @property string|null $difficulty easy, medium or hard
 * @property string|null $portion national or state
 * @property Carbon|null $retired_at
 * @property bool $in_free_quiz
 */
final class QuizQuestion extends Model
{
    /** @var list<string> */
    protected $fillable = ['state_code', 'topic', 'content', 'correct_option', 'source', 'is_sample', 'in_free_quiz', 'organisation_id', 'course_id', 'review_item_id', 'difficulty', 'portion', 'retired_at'];

    /**
     * The question in one language (English if that language is missing).
     *
     * @return array{question: string, options: list<string>, explanation: string}
     */
    public function in(string $locale): array
    {
        return $this->content[$locale] ?? $this->content['en'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'topic' => QuizTopic::class,
            'content' => 'array',
            'correct_option' => 'integer',
            'is_sample' => 'boolean',
            'in_free_quiz' => 'boolean',
            'retired_at' => 'datetime',
        ];
    }
}
