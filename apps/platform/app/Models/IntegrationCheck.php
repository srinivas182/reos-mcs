<?php

declare(strict_types=1);

namespace App\Models;

use App\Integrations\Checks\CheckOutcome;
use App\Integrations\Checks\CheckTrigger;
use App\Integrations\CredentialEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One connection test or scheduled health check. Kept for 30 days.
 *
 * @property int $id
 * @property string $integration_id
 * @property CredentialEnvironment $environment
 * @property CheckOutcome $outcome
 * @property string $message
 * @property array<string, string|int> $parameters
 * @property int|null $duration_ms
 * @property CheckTrigger $trigger
 * @property int|null $checked_by
 * @property Carbon $created_at
 * @property-read User|null $checker
 */
#[Fillable(['integration_id', 'environment', 'outcome', 'message', 'parameters', 'duration_ms', 'trigger', 'checked_by', 'created_at'])]
final class IntegrationCheck extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const KEEP_DAYS = 30;

    /** @return BelongsTo<User, $this> */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'environment' => CredentialEnvironment::class,
            'outcome' => CheckOutcome::class,
            'trigger' => CheckTrigger::class,
            'parameters' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
