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

    protected $fillable = ['lesson_id', 'version', 'status', 'sources', 'lines', 'flags', 'presenter', 'vendor', 'credits', 'failure', 'created_by'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'lines' => 'array', 'flags' => 'array', 'version' => 'integer', 'credits' => 'integer'];
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
