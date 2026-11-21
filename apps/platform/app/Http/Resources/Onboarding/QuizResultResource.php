<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use App\Enums\QuizTopic;
use App\Models\QuizAttempt;
use App\Services\Onboarding\FreeQuiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A saved quiz score ("you already know 40%") and how each topic went.
 *
 * @property QuizAttempt $resource
 */
final class QuizResultResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $attempt = $this->resource;

        return [
            'correct' => (int) $attempt->correct,
            'total' => (int) $attempt->total,
            'percent' => (int) round(100 * $attempt->correct / max(1, $attempt->total)),
            /**
             * Each topic: strong, learning or start_here.
             *
             * @var list<array{topic: QuizTopic, level: 'strong'|'learning'|'start_here'}>
             */
            'topics' => app(FreeQuiz::class)->topics($attempt),
        ];
    }
}
