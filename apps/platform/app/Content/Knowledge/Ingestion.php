<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

use App\Content\Extraction\Captions;
use App\Content\Extraction\Section;
use App\Content\IngestStatus;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\LessonMedia;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Builds a lesson's knowledge base copy (A5.3, ADR 0020): its text, the
 * words on its slides and documents, and its video captions become passages
 * with where they came from. Passages that didn't change keep their
 * embedding, so editing one paragraph re-embeds only that paragraph.
 */
final readonly class Ingestion
{
    public function __construct(private Chunker $chunker, private Embedders $embedders) {}

    public function ingest(Lesson $lesson): void
    {
        $lesson->forceFill(['ingest_status' => IngestStatus::Processing])->save();

        try {
            $this->replace($lesson, $this->drafts($lesson));
            $lesson->forceFill(['ingest_status' => IngestStatus::Ready, 'ingested_at' => now()])->save();
        } catch (Throwable $e) {
            $lesson->forceFill(['ingest_status' => IngestStatus::Failed])->save();

            throw $e;
        }

        // Meaning search: passages without an embedding get one. A failure here
        // leaves keyword search working; `reos:knowledge:embed` tries again.
        try {
            $this->embedMissing($lesson->id);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Embeds passages that have no embedding yet (all lessons, or one).
     *
     * @return int how many were embedded
     */
    public function embedMissing(?int $lessonId = null, int $limit = 2000): int
    {
        $embedder = $this->embedders->embedder();

        if ($embedder === null) {
            return 0;
        }

        $count = 0;

        KbPassage::query()
            ->whereNull('embedded_at')
            ->when($lessonId !== null, static fn ($query) => $query->where('lesson_id', $lessonId))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'heading', 'content'])
            ->each(static function (KbPassage $passage) use ($embedder, &$count): void {
                $text = $passage->heading === null ? $passage->content : $passage->heading."\n".$passage->content;
                DB::update('UPDATE kb_passages SET embedding = CAST(? AS vector), embedded_at = ? WHERE id = ?', [
                    Embedders::literal($embedder->embed($text)), now(), $passage->id,
                ]);
                $count++;
            });

        return $count;
    }

    /** @return list<Draft> */
    public function drafts(Lesson $lesson): array
    {
        $drafts = $this->chunker->text((string) $lesson->body);
        $media = $lesson->media()->where('status', MediaStatus::Ready)->get();
        $captioned = $media->contains(static fn (LessonMedia $m): bool => $m->kind === MediaKind::Captions);

        foreach ($media as $file) {
            $more = match ($file->kind) {
                MediaKind::Slides, MediaKind::Document => $this->chunker->sections(array_map(
                    static fn (array $section): Section => new Section($section['page'], $section['text']),
                    $file->sections ?? [],
                ), $file->id),
                MediaKind::Captions => $this->chunker->cues(Captions::cues((string) $file->transcript), $file->id),
                // The host's generated captions, unless a captions file was uploaded.
                MediaKind::Video => $captioned || $file->transcript === null ? [] : $this->chunker->cues(Captions::cues($file->transcript), $file->id),
            };

            array_push($drafts, ...$more);
        }

        return $drafts;
    }

    /** @param  list<Draft>  $drafts */
    private function replace(Lesson $lesson, array $drafts): void
    {
        $course = $lesson->course;

        DB::transaction(static function () use ($lesson, $course, $drafts): void {
            /** @var array<string, list<KbPassage>> $existing */
            $existing = [];

            // Instructors' answers added to knowledge (A10.1b) aren't part of the lesson's own text: kept as they are.
            foreach ($lesson->passages()->where('kind', '!=', KbPassage::INSTRUCTOR)->get() as $passage) {
                $existing[$passage->content_hash][] = $passage;
            }

            foreach ($drafts as $position => $draft) {
                $hash = $draft->hash();
                $values = [
                    'course_id' => $course->id,
                    'lesson_media_id' => $draft->mediaId,
                    'states' => $course->states,
                    'language' => $lesson->language,
                    'position' => $position,
                ];
                $kept = isset($existing[$hash]) ? array_shift($existing[$hash]) : null;

                if ($kept instanceof KbPassage) {
                    $kept->forceFill($values)->save();

                    continue;
                }

                KbPassage::query()->create([
                    ...$values,
                    'lesson_id' => $lesson->id,
                    'kind' => $draft->kind,
                    'heading' => $draft->heading,
                    'page' => $draft->page,
                    'seconds' => $draft->seconds,
                    'content' => $draft->content,
                    'content_hash' => $hash,
                ]);
            }

            $stale = array_map(static fn (KbPassage $p): int => $p->id, array_merge([], ...array_values($existing)));

            if ($stale !== []) {
                KbPassage::query()->whereKey($stale)->delete();
            }
        });
    }
}
