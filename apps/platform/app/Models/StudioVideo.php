<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A studio video (B12.2, ADR 0051; agent screen 168, web screen 17).
 *
 * @phpstan-type Scene array{media: string, kind: 'image'|'video', start: float, seconds: float, length: float|null, caption: string}
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property array{type: 'listing'|'own', id: string|null, label: string|null} $source
 * @property string|null $listing
 * @property string $title
 * @property list<Scene> $scenes
 * @property string $music
 * @property bool $brand
 * @property string $status
 * @property int $version
 * @property string|null $path
 * @property string|null $poster
 * @property string|null $seconds
 * @property string|null $error
 * @property string $language
 * @property string $engine
 * @property int $credits
 * @property CarbonImmutable|null $rendered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class StudioVideo extends Model
{
    protected $fillable = ['uuid', 'user_id', 'source', 'listing', 'title', 'scenes', 'music', 'brand', 'status', 'version', 'path', 'poster', 'seconds', 'error', 'language', 'engine', 'credits', 'rendered_at'];

    protected function casts(): array
    {
        return [
            'source' => 'array', 'scenes' => 'array', 'brand' => 'boolean', 'version' => 'integer', 'credits' => 'integer',
            'rendered_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
