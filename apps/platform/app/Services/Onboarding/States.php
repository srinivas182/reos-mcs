<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\StateStatus;
use App\Models\State;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\StateWentLive;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The states REOS serves, their waitlists, and the super admin's launch
 * controls (mobile journey screens 2–3, web journey screen 61).
 *
 * A state goes live only when its launch checklist is complete (A5.1); that
 * day, everyone on its waitlist with an email is told.
 */
final readonly class States
{
    public const AUDIT_SUBJECT = 'state';

    /** The launch checklist on screen 61, in order. */
    public const CHECKLIST = ['rules', 'approvals', 'advertising', 'plans', 'integrations', 'legal'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Live states first, then by name.
     *
     * @return Collection<int, State>
     */
    public function all(): Collection
    {
        return State::query()
            ->withCount(['waitlist' => static fn ($query) => $query->whereNull('notified_at')])
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

    /** @throws ValidationException when going live with the checklist incomplete */
    public function setStatus(State $state, StateStatus $status, User $actor): void
    {
        $previous = $state->status;

        if ($previous === $status) {
            return;
        }

        if ($status === StateStatus::Live && self::missing($state) !== []) {
            throw ValidationException::withMessages(['status' => __('states.checklist.incomplete', ['state' => $state->name])]);
        }

        $state->forceFill([
            'status' => $status,
            'went_live_at' => $status === StateStatus::Live ? ($state->went_live_at ?? CarbonImmutable::now()) : $state->went_live_at,
        ])->save();

        $this->audit->record('state.status_changed', self::AUDIT_SUBJECT, $state->code, [
            'from' => $previous->value,
            'to' => $status->value,
        ], $actor);

        if ($status === StateStatus::Live) {
            $this->tellWaitlist($state);
        }
    }

    /** "Add state": starts preparing a state, with its regulator, exam vendor and hours. */
    public function prepare(State $state, string $regulator, ?string $examVendor, ?int $qualifyingHours, ?int $ceHours, User $actor): void
    {
        $before = ['status' => $state->status->value, ...$state->only(['regulator', 'exam_vendor', 'qualifying_hours', 'ce_hours'])];
        $state->forceFill([
            'status' => $state->status === StateStatus::Live ? StateStatus::Live : StateStatus::InPreparation,
            'regulator' => $regulator,
            'exam_vendor' => $examVendor,
            'qualifying_hours' => $qualifyingHours,
            'ce_hours' => $ceHours,
        ])->save();

        $this->audit->record('state.prepared', self::AUDIT_SUBJECT, $state->code, [
            'before' => $before,
            'after' => $state->only(['regulator', 'exam_vendor', 'qualifying_hours', 'ce_hours']),
        ], $actor);
    }

    /** Ticks (or unticks) one launch checklist item. */
    public function tick(State $state, string $item, bool $done, User $actor): void
    {
        if (! in_array($item, self::CHECKLIST, true)) {
            throw ValidationException::withMessages(['item' => __('states.checklist.unknown')]);
        }

        $checklist = self::checklist($state);
        $checklist[$item] = $done ? ['by' => $actor->id, 'at' => CarbonImmutable::now()->toIso8601String()] : null;
        $state->forceFill(['launch_checklist' => array_filter($checklist)])->save();

        $this->audit->record('state.checklist_ticked', self::AUDIT_SUBJECT, $state->code, ['item' => $item, 'done' => $done], $actor);
    }

    /**
     * Each checklist item and whether it's done.
     *
     * @return array<string, array{by: int, at: string}|null>
     */
    public static function checklist(State $state): array
    {
        $saved = is_array($state->launch_checklist) ? $state->launch_checklist : [];
        $items = [];

        foreach (self::CHECKLIST as $item) {
            $tick = $saved[$item] ?? null;
            $items[$item] = is_array($tick) && is_int($tick['by'] ?? null) && is_string($tick['at'] ?? null) ? ['by' => $tick['by'], 'at' => $tick['at']] : null;
        }

        return $items;
    }

    /** @return list<string> Items not yet done. */
    public static function missing(State $state): array
    {
        return array_keys(array_filter(self::checklist($state), static fn (?array $tick): bool => $tick === null));
    }

    /** Emails everyone waiting for this state (people in their language), once. */
    private function tellWaitlist(State $state): void
    {
        WaitlistEntry::query()->with('user')->where('state_code', $state->code)->whereNull('notified_at')
            ->chunkById(200, function ($entries) use ($state): void {
                foreach ($entries as $entry) {
                    $email = $entry->user->email ?? $entry->email;

                    if ($email === null) {
                        continue; // Phone-only entries hear by push (B6.2).
                    }

                    $entry->user !== null
                        ? $entry->user->notify(new StateWentLive($state))
                        : Notification::route('mail', $email)->notify(new StateWentLive($state));

                    $entry->forceFill(['notified_at' => CarbonImmutable::now()])->save();
                }
            });
    }
}
