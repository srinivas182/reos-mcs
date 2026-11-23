<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\MediaKind;
use App\Content\MediaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lesson's video, slides, document or captions file (A5.3). Uploaded
 * straight to storage, scanned, then processed; video plays from Mux when
 * it's on, otherwise from the original file.
 *
 * @property int $id
 * @property int $lesson_id
 * @property MediaKind $kind
 * @property string|null $language The spoken language of an AI lesson video (A7.3)
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property MediaStatus $status
 * @property string|null $failure Translation key: why it was refused or failed
 * @property string|null $mux_asset_id
 * @property string|null $mux_playback_id
 * @property int|null $duration_seconds
 * @property int|null $pages
 * @property string|null $transcript Captions (WebVTT): a captions file, or the video host's
 * @property array<string, string>|null $captions An AI lesson video's captions (WebVTT) by language, timed to it
 * @property array{resolution: string, bytes: int|null}|null $offline The 720p download Mux made (A8.2b), once ready
 * @property list<array{page: int|null, text: string}>|null $sections Words on each page (slides and documents)
 * @property Carbon|null $scanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
final class LessonMedia extends Model
{
    protected $table = 'lesson_media';

    protected $fillable = [
        'lesson_id', 'kind', 'path', 'original_name', 'mime', 'size', 'status', 'failure', 'mux_asset_id', 'mux_playback_id',
        'duration_seconds', 'pages', 'transcript', 'sections', 'scanned_at', 'language', 'captions', 'offline',
    ];

    protected $attributes = ['status' => 'awaiting_upload'];

    protected $hidden = ['transcript', 'sections', 'captions'];

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'status' => MediaStatus::class,
            'size' => 'integer',
            'duration_seconds' => 'integer',
            'pages' => 'integer',
            'sections' => 'array',
            'captions' => 'array',
            'offline' => 'array',
            'scanned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
