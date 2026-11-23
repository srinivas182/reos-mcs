<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lesson version's avatar video in one language (A7.3): asked of the
 * vendor, checked until ready, then copied into REOS's storage and video
 * pipeline as a lesson file in that language.
 *
 * @property int $id
 * @property int $lesson_version_id
 * @property string $language
 * @property string $vendor
 * @property string|null $external_id
 * @property string $status queued, rendering, ready or failed
 * @property int|null $seconds
 * @property int|null $lesson_media_id
 * @property bool $simulated
 * @property string|null $failure
 * @property int $checks
 * @property Carbon|null $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LessonVersion $version
 */
final class LessonRender extends Model
{
    public const QUEUED = 'queued';

    public const RENDERING = 'rendering';

    public const READY = 'ready';

    public const FAILED = 'failed';

    /** Checked every two minutes; a render still not ready after a day has failed. */
    public const MAX_CHECKS = 720;

    protected $fillable = ['lesson_version_id', 'language', 'vendor', 'external_id', 'status', 'seconds', 'lesson_media_id', 'simulated', 'failure', 'checks', 'checked_at'];

    protected function casts(): array
    {
        return ['simulated' => 'boolean', 'seconds' => 'integer', 'checks' => 'integer', 'checked_at' => 'datetime'];
    }

    /** @return BelongsTo<LessonVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LessonVersion::class, 'lesson_version_id');
    }
}
