<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A free-quiz question in the request's language, with its answer: the app
 * explains each answer the moment it's tapped.
 *
 * @property QuizQuestion $resource
 */
final class QuizQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $text = $this->resource->in(app()->getLocale());

        return [
            'id' => (int) $this->resource->id,
            'topic' => $this->resource->topic,
            'question' => (string) $text['question'],
            /**
             * Four options, in order.
             *
             * @var list<string>
             */
            'options' => $text['options'],
            /** Index of the right option (0–3). */
            'correct_option' => (int) $this->resource->correct_option,
            'explanation' => (string) $text['explanation'],
            /** Where the answer comes from: a rule, statute or exam outline topic. */
            'source' => (string) $this->resource->source,
            /** A sample question (until the licensed question bank arrives). */
            'sample' => (bool) $this->resource->is_sample,
        ];
    }
}
