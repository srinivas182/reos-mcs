<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One generation of an AI lesson (A7.3, ADR 0026): the script line by line
 * in English and Spanish, each line with its source, the legal flags, the
 * presenter and vendor, and what it cost. Earlier versions are kept.
 *
 * @property int $id
 * @property int $lesson_id
 * @property int $version
 * @property string $status drafting, flagged, rendering, ready or failed
 * @property list<array{source: string, page: int|null, text: string}> $sources
 * @property list<array{n: int, en: string, es: string, source: string, page: int|null}>|null $lines
 * @property list<array{line: int, rule: string, language: string}>|null $flags
 * @property array<int, array<string, mixed>>|null $reviews Line-by-line review, by line number (A8.1); read through review()
 * @property string|null $review_state in_review, sent_back or approved
 * @property string|null $review_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string $presenter
 * @property string $vendor
 * @property int $credits
 * @property string|null $failure
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 * @property-read Collection<int, LessonRender> $renders
 */
final class LessonVersion extends Model
{
    public const DRAFTING = 'drafting';

    public const FLAGGED = 'flagged';

    public const RENDERING = 'rendering';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $fillable = ['lesson_id', 'version', 'status', 'sources', 'lines', 'flags', 'reviews', 'review_state', 'review_note', 'reviewed_by', 'reviewed_at', 'presenter', 'vendor', 'credits', 'failure', 'created_by'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'lines' => 'array', 'flags' => 'array', 'reviews' => 'array', 'reviewed_at' => 'datetime', 'version' => 'integer', 'credits' => 'integer'];
    }

    /**
     * One line's review (A8.1), with defaults.
     *
     * @return array{status: string, rerecord: bool, suggestion: array{en: string, es: string}|null, comments: list<array{by: int, name: string, body: string, at: string}>}
     */
    public function review(int $n): array
    {
        $r = ($this->reviews ?? [])[$n] ?? [];
        $suggestion = $r['suggestion'] ?? null;
        $comments = [];

        foreach (is_array($r['comments'] ?? null) ? $r['comments'] : [] as $c) {
            if (is_array($c) && is_int($c['by'] ?? null) && is_string($c['name'] ?? null) && is_string($c['body'] ?? null) && is_string($c['at'] ?? null)) {
                $comments[] = ['by' => $c['by'], 'name' => $c['name'], 'body' => $c['body'], 'at' => $c['at']];
            }
        }

        return [
            'status' => is_string($r['status'] ?? null) ? $r['status'] : 'to_review',
            'rerecord' => ($r['rerecord'] ?? false) === true,
            'suggestion' => is_array($suggestion) && is_string($suggestion['en'] ?? null) && is_string($suggestion['es'] ?? null) ? ['en' => $suggestion['en'], 'es' => $suggestion['es']] : null,
            'comments' => $comments,
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return HasMany<LessonRender, $this> */
    public function renders(): HasMany
    {
        return $this->hasMany(LessonRender::class);
    }
}
