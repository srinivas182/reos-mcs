<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\StateTagged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A short passage of a lesson in the knowledge base (A5.3, ADR 0020): from
 * its text (with the heading), a slide or page (with the page), or its video
 * transcript (with the second it starts), so a search result opens the lesson
 * at the right place. On PostgreSQL with pgvector it also has an `embedding`
 * column (not mapped here; written and read with SQL).
 *
 * @property int $id
 * @property int $lesson_id
 * @property int $course_id
 * @property int|null $lesson_media_id
 * @property list<string>|null $states
 * @property string $language
 * @property string $kind text, slide or transcript
 * @property string|null $heading
 * @property int|null $page
 * @property int|null $seconds
 * @property int $position
 * @property string $content
 * @property string $content_hash
 * @property Carbon|null $embedded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 * @property-read Course $course
 */
final class KbPassage extends Model
{
    use StateTagged;

    public const TEXT = 'text';

    public const SLIDE = 'slide';

    public const TRANSCRIPT = 'transcript';

    protected $fillable = ['lesson_id', 'course_id', 'lesson_media_id', 'states', 'language', 'kind', 'heading', 'page', 'seconds', 'position', 'content', 'content_hash', 'embedded_at'];

    protected function casts(): array
    {
        return [
            'states' => 'array',
            'page' => 'integer',
            'seconds' => 'integer',
            'position' => 'integer',
            'embedded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
