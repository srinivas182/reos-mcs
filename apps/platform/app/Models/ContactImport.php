<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CSV of contacts (B8.3b): its columns, how they map, and what the
 * import did. Imported rows are a source of their own, `csv-{id}`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $file_name
 * @property string|null $path
 * @property list<string> $headers
 * @property array<string, string>|null $mapping Column header => field.
 * @property int $rows
 * @property string $status
 * @property array{new?: int, merged?: int, skipped?: int}|null $counts
 * @property Carbon|null $imported_at
 * @property Carbon|null $undone_at
 * @property Carbon|null $created_at
 */
final class ContactImport extends Model
{
    public const FIELDS = ['full_name', 'first_name', 'last_name', 'email', 'phone', 'stage', 'tags', 'birthday'];

    protected $fillable = ['uuid', 'user_id', 'file_name', 'path', 'headers', 'mapping', 'rows', 'status', 'counts', 'imported_at', 'undone_at'];

    protected function casts(): array
    {
        return ['headers' => 'array', 'mapping' => 'array', 'counts' => 'array', 'imported_at' => 'datetime', 'undone_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** The contact source its rows come from. */
    public function source(): string
    {
        return 'csv-'.$this->id;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
