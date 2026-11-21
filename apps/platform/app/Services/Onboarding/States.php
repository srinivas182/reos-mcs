<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\StateStatus;
use App\Models\State;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The states REOS serves, their waitlists, and the super admin's launch
 * controls (mobile journey screens 2–3, web journey screen 61).
 */
final readonly class States
{
    public const AUDIT_SUBJECT = 'state';

    public function __construct(private AuditLogger $audit) {}

    /**
     * Live states first, then by name.
     *
     * @return Collection<int, State>
     */
    public function all(): Collection
    {
        return State::query()
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StateStatus::Live->value])
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, int> people waiting, by state code */
    public function waiting(): array
    {
        /** @var array<string, int> */
        return DB::table('state_waitlist')
            ->whereNull('notified_at')
            ->groupBy('state_code')
            ->selectRaw('state_code, count(*) as waiting')
            ->pluck('waiting', 'state_code')
            ->map(static fn (mixed $count): int => is_numeric($count) ? (int) $count : 0)
            ->all();
    }

    /**
     * Join a state's waitlist once per person (or per device before an
     * account exists). Returns how many are waiting now.
     */
    public function join(State $state, ?User $user, ?string $deviceId, ?string $email): int
    {
        $match = $user !== null ? ['user_id' => $user->id] : ['device_id' => $deviceId];

        WaitlistEntry::query()->firstOrCreate(
            ['state_code' => $state->code, ...$match],
            ['device_id' => $deviceId, 'email' => $email ?? $user?->email],
        );

        return $this->waiting()[$state->code] ?? 0;
    }

    public function setStatus(State $state, StateStatus $status, User $actor): void
    {
        $previous = $state->status;

        if ($previous === $status) {
            return;
        }

        $state->forceFill(['status' => $status])->save();

        $this->audit->record('state.status_changed', self::AUDIT_SUBJECT, $state->code, [
            'from' => $previous->value,
            'to' => $status->value,
        ], $actor);
    }
}
