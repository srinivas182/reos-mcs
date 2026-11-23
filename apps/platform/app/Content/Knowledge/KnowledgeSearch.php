<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

use App\Content\Builder\LessonReview;
use App\Content\CourseStatus;
use App\Models\KbPassage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Searches course content (A5.3, ADR 0020) by keyword and by meaning, and
 * mixes the two lists with reciprocal rank fusion: a passage high on either
 * list ranks high, one on both ranks highest. Keyword search is PostgreSQL
 * full text in the passage's language; meaning search compares Titan
 * embeddings with pgvector. Only live courses in the given states (and
 * language) are searched.
 */
final readonly class KnowledgeSearch
{
    /** Each list is cut here before mixing. */
    private const CANDIDATES = 40;

    /** Reciprocal rank fusion constant (the usual 60). */
    private const RRF = 60;

    /** Passages further than this (cosine distance) aren't about the question. */
    private const MAX_DISTANCE = 0.75;

    public function __construct(private Embedders $embedders) {}

    /**
     * @param  list<string>|null  $states  null: every state (admin preview)
     * @param  int|null  $courseId  only this course (Ask the instructor, mid-lesson)
     * @return list<SearchHit>
     */
    public function search(string $query, ?array $states, ?string $language = null, int $limit = 10, bool $liveOnly = true, ?int $courseId = null): array
    {
        $query = trim(mb_substr($query, 0, 300));

        if (mb_strlen($query) < 2) {
            return [];
        }

        $pgsql = DB::getDriverName() === 'pgsql';
        $keyword = $pgsql ? $this->keyword($query, $states, $language, $liveOnly, $courseId) : $this->like($query, $states, $language, $liveOnly, $courseId);
        $meaning = $pgsql ? $this->meaning($query, $states, $language, $liveOnly, $courseId) : [];

        $scores = [];

        foreach ([$keyword, $meaning] as $list) {
            foreach ($list as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF + $rank + 1);
            }
        }

        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);

        if ($ids === []) {
            return [];
        }

        $snippets = $pgsql ? $this->headlines($ids, $query) : [];
        $passages = KbPassage::query()->with(['lesson:id,title', 'course:id,title'])->whereKey($ids)->get()->keyBy('id');
        $hits = [];

        foreach ($ids as $id) {
            $passage = $passages->get($id);

            if (! $passage instanceof KbPassage) {
                continue;
            }

            $inKeyword = in_array($id, $keyword, true);
            $inMeaning = in_array($id, $meaning, true);
            $hits[] = new SearchHit(
                $passage->id, $passage->lesson_id, $passage->lesson->title, $passage->course_id, $passage->course->title,
                $passage->kind, $passage->heading, $passage->page, $passage->seconds, $passage->language,
                $snippets[$id] ?? self::snippet($passage->content, $query),
                $inKeyword && $inMeaning ? 'both' : ($inKeyword ? 'keyword' : 'meaning'),
            );
        }

        return $hits;
    }

    /**
     * Passages people may see: live courses (unless previewing), lessons and courses not deleted, the states and language.
     *
     * @param  list<string>|null  $states
     * @return Builder<KbPassage>
     */
    private function scope(?array $states, ?string $language, bool $liveOnly, ?int $courseId = null): Builder
    {
        return KbPassage::query()
            ->join('courses', 'courses.id', '=', 'kb_passages.course_id')
            ->join('lessons', 'lessons.id', '=', 'kb_passages.lesson_id')
            ->whereNull('courses.deleted_at')
            ->whereNull('lessons.deleted_at')
            ->when($liveOnly, static fn (Builder $q) => $q->where('courses.status', CourseStatus::Live->value)
                // A school's lessons reach learners only once approved (A7.2).
                ->where(static fn (Builder $r) => $r->whereNull('lessons.review_status')->orWhere('lessons.review_status', LessonReview::Approved->value)))
            ->when($states !== null, static fn (Builder $q) => $q->forStates($states ?? []))
            ->when($language !== null, static fn (Builder $q) => $q->where('kb_passages.language', $language))
            ->when($courseId !== null, static fn (Builder $q) => $q->where('kb_passages.course_id', $courseId));
    }

    /**
     * @param  list<string>|null  $states
     * @return list<int>
     */
    private function keyword(string $query, ?array $states, ?string $language, bool $liveOnly, ?int $courseId = null): array
    {
        $match = "(kb_passages.language = 'es' AND kb_passages.search @@ websearch_to_tsquery('spanish', ?))
            OR (kb_passages.language <> 'es' AND kb_passages.search @@ websearch_to_tsquery('english', ?))";
        $rank = "ts_rank_cd(kb_passages.search, CASE WHEN kb_passages.language = 'es' THEN websearch_to_tsquery('spanish', ?) ELSE websearch_to_tsquery('english', ?) END)";

        return self::ids($this->scope($states, $language, $liveOnly, $courseId)
            ->whereRaw("({$match})", [$query, $query])
            ->orderByRaw("{$rank} DESC", [$query, $query])
            ->orderBy('kb_passages.id')
            ->limit(self::CANDIDATES)
            ->pluck('kb_passages.id')->all());
    }

    /**
     * @param  list<string>|null  $states
     * @return list<int>
     */
    private function meaning(string $query, ?array $states, ?string $language, bool $liveOnly, ?int $courseId = null): array
    {
        $embedder = $this->embedders->embedder();

        if ($embedder === null) {
            return [];
        }

        try {
            $vector = Cache::remember('kb.query.'.hash('sha256', $query), now()->addMinutes(30), static fn (): string => Embedders::literal($embedder->embed($query)));
        } catch (Throwable $e) {
            // Keyword results still come back.
            report($e);

            return [];
        }

        return DB::transaction(function () use ($vector, $states, $language, $liveOnly, $courseId): array {
            // pgvector 0.8+: keep scanning the index until enough rows pass the filters.
            if (self::iterativeScans()) {
                DB::statement('SET LOCAL hnsw.iterative_scan = relaxed_order');
            }

            DB::statement('SET LOCAL hnsw.ef_search = 100');

            return self::ids($this->scope($states, $language, $liveOnly, $courseId)
                ->whereNotNull('kb_passages.embedding')
                ->whereRaw('(kb_passages.embedding <=> CAST(? AS vector)) < ?', [$vector, self::MAX_DISTANCE])
                ->orderByRaw('kb_passages.embedding <=> CAST(? AS vector)', [$vector])
                ->limit(self::CANDIDATES)
                ->pluck('kb_passages.id')->all());
        });
    }

    /** Whether pgvector can keep scanning past filtered-out rows (0.8 and later; production and CI run 0.8). */
    private static function iterativeScans(): bool
    {
        $version = DB::scalar("SELECT extversion FROM pg_extension WHERE extname = 'vector'");

        return is_string($version) && version_compare($version, '0.8.0', '>=');
    }

    /**
     * Keyword search without PostgreSQL (SQLite tests): every word must appear.
     *
     * @param  list<string>|null  $states
     * @return list<int>
     */
    private function like(string $query, ?array $states, ?string $language, bool $liveOnly, ?int $courseId = null): array
    {
        $words = self::words($query);

        if ($words === []) {
            return [];
        }

        $scope = $this->scope($states, $language, $liveOnly, $courseId);
        // "a or b" means either, as websearch_to_tsquery reads it on PostgreSQL.
        $any = in_array('or', $words, true);
        $words = $any ? array_values(array_diff($words, ['or'])) : $words;

        $scope->where(static function (Builder $q) use ($words, $any): void {
            foreach ($words as $word) {
                $q->where('kb_passages.content', 'like', '%'.addcslashes($word, '%_\\').'%', $any ? 'or' : 'and');
            }
        });

        return self::ids($scope->orderBy('kb_passages.id')->limit(self::CANDIDATES)->pluck('kb_passages.id')->all());
    }

    /**
     * Snippets with the matching words marked, from PostgreSQL.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function headlines(array $ids, string $query): array
    {
        $options = 'StartSel=[[, StopSel=]], MaxWords=40, MinWords=18, MaxFragments=2, FragmentDelimiter=" … "';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::select(
            "SELECT id, ts_headline(CASE WHEN language = 'es' THEN 'spanish'::regconfig ELSE 'english'::regconfig END, content,
                CASE WHEN language = 'es' THEN websearch_to_tsquery('spanish', ?) ELSE websearch_to_tsquery('english', ?) END, ?) AS snippet
             FROM kb_passages WHERE id IN ({$placeholders})",
            [$query, $query, $options, ...$ids],
        );
        $snippets = [];

        foreach ($rows as $row) {
            if (is_object($row) && isset($row->id, $row->snippet) && is_string($row->snippet)) {
                $snippets[(int) $row->id] = $row->snippet;
            }
        }

        return $snippets;
    }

    /** A snippet around the first matching word, with matches marked (no PostgreSQL). */
    public static function snippet(string $content, string $query): string
    {
        $words = self::words($query);
        $first = null;

        foreach ($words as $word) {
            $at = mb_stripos($content, $word);

            if ($at !== false && ($first === null || $at < $first)) {
                $first = $at;
            }
        }

        $start = max(0, ($first ?? 0) - 80);
        $text = ($start > 0 ? '… ' : '').mb_substr($content, $start, 240).(mb_strlen($content) > $start + 240 ? ' …' : '');

        foreach ($words as $word) {
            $text = (string) preg_replace('/('.preg_quote($word, '/').')/iu', '[[$1]]', $text);
        }

        return $text;
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids));
    }

    /** @return list<string> */
    public static function words(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [];

        return array_values(array_unique(array_filter($words, static fn (string $w): bool => mb_strlen($w) >= 2)));
    }
}
