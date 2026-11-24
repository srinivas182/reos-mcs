<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a brokerage's policy manual (A12.1, ADR 0047; broker web
 * screen 34). The newest upload is current; the others are kept.
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property int $version
 * @property string $file_name
 * @property string $path
 * @property int $pages
 * @property int $passages
 * @property list<string> $topics
 * @property list<array{rule: string, citation: string, url: string|null, page: int|null, position: int, excerpt: string, rule_text: string}> $conflicts
 * @property string $status current or replaced
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $uploader
 */
final class PolicyManual extends Model
{
    public const CURRENT = 'current';

    public const REPLACED = 'replaced';

    protected $fillable = ['uuid', 'organisation_id', 'version', 'file_name', 'path', 'pages', 'passages', 'topics', 'conflicts', 'status', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'pages' => 'integer',
            'passages' => 'integer',
            'topics' => 'array',
            'conflicts' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
