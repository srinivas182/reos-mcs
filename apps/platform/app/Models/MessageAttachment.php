<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file sent with a message (B6.1b): an image or a PDF up to 20 MB,
 * virus-scanned before anyone can open it, like lesson files.
 *
 * @property int $id
 * @property string $uuid
 * @property int $message_id
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property string $scan_status pending, clean or infected
 * @property Carbon $created_at
 * @property-read Message $message
 */
final class MessageAttachment extends Model
{
    public const UPDATED_AT = null;

    public const MAX_KB = 20 * 1024;

    public const MAX_FILES = 5;

    /** Images and PDFs only. */
    public const MIMES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf'];

    protected $fillable = ['uuid', 'message_id', 'path', 'name', 'mime', 'size', 'scan_status'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
