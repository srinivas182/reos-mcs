<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A privacy request (TDPSA, A4.3b): see, correct or delete one's data.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $email
 * @property string $kind
 * @property string|null $details
 * @property string $status
 * @property Carbon $due_at
 * @property bool $extended
 * @property string|null $response
 * @property string|null $export_path
 * @property Carbon|null $completed_at
 * @property int|null $handled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read User|null $handler
 */
#[Fillable(['user_id', 'name', 'email', 'kind', 'details', 'status', 'due_at', 'extended', 'response', 'export_path', 'completed_at', 'handled_by'])]
#[Hidden(['export_path'])]
final class PrivacyRequest extends Model
{
    public const ACCESS = 'access';

    public const CORRECT = 'correct';

    public const DELETE = 'delete';

    public const KINDS = [self::ACCESS, self::CORRECT, self::DELETE];

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    /** Account deletion waiting out its 30 days. */
    public const HOLD = 'hold';

    public const DONE = 'done';

    public const DECLINED = 'declined';

    public const WITHDRAWN = 'withdrawn';

    /** Still waiting for REOS. */
    public function waiting(): bool
    {
        return in_array($this->status, [self::OPEN, self::IN_PROGRESS, self::HOLD], true);
    }

    public function overdue(): bool
    {
        return $this->waiting() && $this->due_at->isPast();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'extended' => 'boolean'];
    }
}
