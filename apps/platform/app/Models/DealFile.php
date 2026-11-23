<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file kept on a deal (B9.3, ADR 0038): a PDF the agent uploaded to send
 * for signature, or the signed copy and certificate filed when an envelope
 * completed. On the content disk, virus-scanned before it opens or is
 * sent, and opened with a short-lived signed link.
 *
 * @property int $id
 * @property string $uuid
 * @property int $deal_id
 * @property int $user_id
 * @property int|null $organisation_id
 * @property int|null $signature_request_id
 * @property string $kind upload, signed or certificate
 * @property string $name
 * @property string $path
 * @property int $size
 * @property string $scan_status pending, clean or infected
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Deal $deal
 */
final class DealFile extends Model
{
    public const KINDS = ['upload', 'signed', 'certificate'];

    /** @var list<string> */
    protected $fillable = ['uuid', 'deal_id', 'user_id', 'organisation_id', 'signature_request_id', 'kind', 'name', 'path', 'size', 'scan_status'];

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
