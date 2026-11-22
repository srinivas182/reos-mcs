<?php

declare(strict_types=1);

namespace App\Http\Resources\Learn;

use App\Content\Knowledge\SearchHit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A passage found in the person's courses (A5.3b): where it opens and the
 * matching words wrapped in `[[` and `]]`.
 *
 * @property SearchHit $resource
 */
final class KnowledgeHitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $hit = $this->resource;

        return [
            'passage_id' => $hit->passageId,
            'lesson_id' => $hit->lessonId,
            'lesson_title' => $hit->lessonTitle,
            'course_id' => $hit->courseId,
            'course_title' => $hit->courseTitle,
            /** @var 'text'|'slide'|'transcript' */
            'kind' => $hit->kind,
            'heading' => $hit->heading,
            /** Page of the slides or document to open at. */
            'page' => $hit->page,
            /** Second of the video to start at. */
            'seconds' => $hit->seconds,
            /** @var 'en'|'es' */
            'language' => $hit->language,
            'snippet' => $hit->snippet,
            /** @var 'keyword'|'meaning'|'both' */
            'match' => $hit->match,
        ];
    }
}
