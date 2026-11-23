<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An uploaded certificate, read and waiting for the person's review (B10.3b).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $path
 * @property string $mime
 * @property string|null $reader textract or simulator
 * @property array{name?: string|null, course?: string|null, hours?: float|null, completed_on?: string|null, provider?: string|null, confidence?: array<string, float>} $fields
 * @property string $scan_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class CertificateRead extends Model
{
    protected $fillable = ['uuid', 'user_id', 'path', 'mime', 'reader', 'fields', 'scan_status'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }
}
