<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the content studio made for an agent (B12.1, ADR 0050; screens 165 and 166).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property array{type: 'listing'|'sale'|'market'|'text', id: string|null, text: string|null, label?: string|null, city?: string|null} $source
 * @property string|null $listing
 * @property list<string> $outputs
 * @property list<array{tone: string, text: string}> $captions
 * @property list<array{layout: string, path: string}> $designs
 * @property list<string> $carousel
 * @property array{headlines: list<string>, primary: list<string>, description: string}|null $ad
 * @property string $language
 * @property string $engine
 * @property int $credits
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class StudioGeneration extends Model
{
    protected $fillable = ['uuid', 'user_id', 'source', 'listing', 'outputs', 'captions', 'designs', 'carousel', 'ad', 'language', 'engine', 'credits'];

    protected function casts(): array
    {
        return [
            'source' => 'array', 'outputs' => 'array', 'captions' => 'array', 'designs' => 'array', 'carousel' => 'array', 'ad' => 'array',
            'credits' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
