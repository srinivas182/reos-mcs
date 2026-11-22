<?php

declare(strict_types=1);

namespace App\Content\Learning;

use App\Content\CourseStatus;
use App\Content\Extraction\Captions;
use App\Content\Knowledge\KnowledgeSearch;
use App\Content\Knowledge\SearchHit;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Content\Video\VideoHosts;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use App\Services\Content\LessonFiles;
use App\Services\States\PersonStates;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a learner sees (A5.3b; web Learn, phone Learn screen 8): live courses
 * for their current state, search inside them, and a lesson opened at the
 * heading, page or second a search result points to. Access by purchase or
 * sponsored seat joins these filters with B5.1.
 */
final readonly class Learning
{
    /** Signed media links last this long; the page asks again after. */
    private const LINK_MINUTES = 120;

    public function __construct(private PersonStates $states, private KnowledgeSearch $search, private VideoHosts $videos) {}

    public function state(User $user): string
    {
        return $this->states->current($user);
    }

    /**
     * Live courses in the person's state, with their lessons.
     *
     * @return list<array{id: int, title: string, type: string, hours: float|null, lessons: list<array{id: int, title: string, minutes: int|null, language: string}>}>
     */
    public function courses(User $user): array
    {
        return array_values(Course::query()
            ->where('status', CourseStatus::Live)
            ->forStates([$this->state($user)])
            ->with(['lessons' => static fn ($q) => $q->select(['id', 'course_id', 'title', 'minutes', 'language', 'position'])])
            ->orderBy('title')
            ->get()
            ->map(static fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'type' => $course->type->value,
                'hours' => $course->hours === null ? null : (float) $course->hours,
                'lessons' => array_values($course->lessons->map(static fn (Lesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'minutes' => $lesson->minutes,
                    'language' => $lesson->language,
                ])->all()),
            ])
            ->all());
    }

    /** @return list<SearchHit> */
    public function search(User $user, string $query, ?string $language = null): array
    {
        return $this->search->search($query, [$this->state($user)], $language, 15);
    }

    /** A lesson the person may open: in a live course for their state. */
    public function find(User $user, int $id): ?Lesson
    {
        return Lesson::query()
            ->whereKey($id)
            ->whereHas('course', fn (Builder $course) => $course->where('status', CourseStatus::Live)->whereNull('deleted_at')->forStates([$this->state($user)]))
            ->first();
    }

    /**
     * The lesson to read and watch: its text by heading, slides and documents
     * page by page, the video (Mux with a signed token, or the original file)
     * and its captions with timings.
     *
     * @return array<string, mixed>
     */
    public function lesson(Lesson $lesson): array
    {
        $media = $lesson->media()->where('status', MediaStatus::Ready)->get();
        $video = $media->first(static fn (LessonMedia $m): bool => $m->kind === MediaKind::Video);
        $captions = $media->first(static fn (LessonMedia $m): bool => $m->kind === MediaKind::Captions);
        $vtt = $captions !== null ? $captions->transcript : $video?->transcript;
        $siblings = array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, Lesson::query()->where('course_id', $lesson->course_id)->orderBy('position')->orderBy('id')->pluck('id')->all()));
        $at = array_search($lesson->id, $siblings, true);

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'language' => $lesson->language,
            'minutes' => $lesson->minutes,
            'course' => ['id' => $lesson->course->id, 'title' => $lesson->course->title],
            'sections' => self::sections((string) $lesson->body),
            'documents' => array_values($media
                ->filter(static fn (LessonMedia $m): bool => $m->kind === MediaKind::Slides || $m->kind === MediaKind::Document)
                ->map(fn (LessonMedia $m): array => [
                    'id' => $m->id,
                    'kind' => $m->kind->value,
                    'name' => $m->original_name,
                    'url' => $this->link($m),
                    'pages' => array_values(array_filter($m->sections ?? [], static fn (array $s): bool => $s['page'] !== null)),
                ])->all()),
            'video' => $video === null ? null : $this->video($video),
            'transcript' => $vtt === null ? [] : Captions::cues($vtt),
            'previous' => $at === false || $at === 0 ? null : $siblings[$at - 1],
            'next' => $at === false ? null : ($siblings[$at + 1] ?? null),
        ];
    }

    /**
     * Lesson text split at its headings (lines starting with "#").
     *
     * @return list<array{heading: string|null, text: string}>
     */
    public static function sections(string $body): array
    {
        $sections = [];
        $heading = null;
        $lines = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $body)) as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $match) === 1) {
                if (trim(implode("\n", $lines)) !== '' || $heading !== null) {
                    $sections[] = ['heading' => $heading, 'text' => trim(implode("\n", $lines))];
                }

                $heading = trim($match[1]);
                $lines = [];

                continue;
            }

            $lines[] = $line;
        }

        if (trim(implode("\n", $lines)) !== '' || $heading !== null) {
            $sections[] = ['heading' => $heading, 'text' => trim(implode("\n", $lines))];
        }

        return $sections;
    }

    /** @return array{type: 'hls'|'file', url: string, duration: int|null, poster: string|null} */
    private function video(LessonMedia $video): array
    {
        $id = $video->mux_playback_id;
        $mux = $id === null ? null : $this->videos->mux();

        if ($mux !== null) {
            return [
                'type' => 'hls',
                'url' => "https://stream.mux.com/{$id}.m3u8?token=".$mux->token($id, 'v', self::LINK_MINUTES),
                'duration' => $video->duration_seconds,
                'poster' => "https://image.mux.com/{$id}/thumbnail.webp?token=".$mux->token($id, 't', self::LINK_MINUTES),
            ];
        }

        return ['type' => 'file', 'url' => $this->link($video), 'duration' => $video->duration_seconds, 'poster' => null];
    }

    private function link(LessonMedia $media): string
    {
        return LessonFiles::disk()->temporaryUrl($media->path, now()->addMinutes(self::LINK_MINUTES), [
            'ResponseContentDisposition' => 'inline; filename="'.addcslashes($media->original_name, '"\\').'"',
        ]);
    }
}
