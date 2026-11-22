<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\IngestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A lesson (A5.3, ADR 0020): its text plus videos, slides, documents and
 * captions. Its knowledge base copy (passages) is rebuilt whenever any of
 * them change.
 *
 * @property int $id
 * @property int $course_id
 * @property int|null $course_module_id
 * @property string $title
 * @property int $position
 * @property string $language en or es
 * @property string|null $body Plain text; lines starting with "# " are headings
 * @property int|null $minutes
 * @property IngestStatus $ingest_status
 * @property Carbon|null $ingested_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Course $course
 * @property-read CourseModule|null $module
 * @property-read Collection<int, LessonMedia> $media
 * @property-read Collection<int, KbPassage> $passages
 */
final class Lesson extends Model
{
    use SoftDeletes;

    public const LANGUAGES = ['en', 'es'];

    protected $fillable = ['course_id', 'course_module_id', 'title', 'position', 'language', 'body', 'minutes', 'ingest_status', 'ingested_at'];

    protected $attributes = ['language' => 'en', 'ingest_status' => 'pending'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'minutes' => 'integer',
            'ingest_status' => IngestStatus::class,
            'ingested_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /** @return BelongsTo<CourseModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /** @return HasMany<LessonMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(LessonMedia::class)->orderBy('id');
    }

    /** @return HasMany<KbPassage, $this> */
    public function passages(): HasMany
    {
        return $this->hasMany(KbPassage::class)->orderBy('position');
    }
}
