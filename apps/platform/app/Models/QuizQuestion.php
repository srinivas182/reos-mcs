<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuizTopic;
use Illuminate\Database\Eloquent\Model;

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
 * @property bool $in_free_quiz
 */
final class QuizQuestion extends Model
{
    /** @var list<string> */
    protected $fillable = ['state_code', 'topic', 'content', 'correct_option', 'source', 'is_sample', 'in_free_quiz'];

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
        ];
    }
}
