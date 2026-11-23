<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A weekly progress report a candidate shared (A9.2e): with the school
 * (organisation set) or by link (expires). The report is kept as shared.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $organisation_id
 * @property Carbon $week_start
 * @property array<string, mixed> $report
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property-read User $user
 */
final class ReportShare extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'user_id', 'organisation_id', 'week_start', 'report', 'expires_at', 'revoked_at'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['week_start' => 'date', 'report' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
