<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\Builder\SourceRole;
use App\Content\MediaKind;
use App\Content\MediaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A module's source material (A7.2, ADR 0026): uploaded straight to storage,
 * virus-scanned and read (pages, headings, captions), for the instructor to
 * build lessons from and the AI to draft from (A7.3). Never shown to learners.
 *
 * @property int $id
 * @property int $course_module_id
 * @property SourceRole $role
 * @property MediaKind $kind
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property MediaStatus $status
 * @property string|null $failure
 * @property Carbon|null $scanned_at
 * @property list<array{page: int|null, text: string}>|null $sections
 * @property int|null $pages
 * @property int|null $words
 * @property string|null $transcript
 * @property int $position
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseModule $module
 */
final class ModuleSource extends Model
{
    protected $fillable = ['course_module_id', 'role', 'kind', 'path', 'original_name', 'mime', 'size', 'status', 'failure', 'scanned_at', 'sections', 'pages', 'words', 'transcript', 'position', 'created_by'];

    protected function casts(): array
    {
        return [
            'role' => SourceRole::class,
            'kind' => MediaKind::class,
            'status' => MediaStatus::class,
            'size' => 'integer',
            'scanned_at' => 'datetime',
            'sections' => 'array',
            'pages' => 'integer',
            'words' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<CourseModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }
}
