<?php

declare(strict_types=1);

namespace App\Features;

use App\Authorization\Role;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Notifications\FlagRolledBack;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Facades\Notification;

/**
 * Changes to a flag's rollout (A6.2, screen 70): the next step, turning it
 * off everywhere and back on, and the automatic step back. Every change is
 * audited with before and after, and forgets Pennant's stored answers.
 */
final readonly class Rollouts
{
    public const AUDIT_SUBJECT = 'feature_flag';

    public function __construct(
        private Flags $flags,
        private AuditLogger $audit,
        private Memberships $memberships,
    ) {}

    /**
     * @param  array{stage: RolloutStage, tenant_ids: list<int>, states: list<string>, percent: int, roles: list<string>|null, error_threshold: int}  $rollout
     */
    public function change(FeatureFlag $flag, array $rollout, User $actor): void
    {
        $before = self::describe($flag);

        $flag->forceFill([
            'stage' => $rollout['stage'],
            'tenant_ids' => $rollout['tenant_ids'] === [] ? null : $rollout['tenant_ids'],
            'states' => $rollout['states'] === [] ? null : $rollout['states'],
            'percent' => $rollout['percent'],
            'roles' => $rollout['roles'] === [] ? null : $rollout['roles'],
            'error_threshold' => $rollout['error_threshold'],
        ]);

        if ($flag->isDirty('stage')) {
            $flag->forceFill(['stage_changed_at' => now(), 'rolled_back_at' => null, 'rolled_back_from' => null]);
        }

        $flag->save();
        $this->flags->changed($flag);
        $this->audit->record('flag.rollout_changed', self::AUDIT_SUBJECT, $flag->key, ['before' => $before, 'after' => self::describe($flag)], $actor);
    }

    /** The kill switch: off for everyone at once, whatever the rollout or a tenant's switch says. */
    public function kill(FeatureFlag $flag, User $actor): void
    {
        if ($flag->killed()) {
            return;
        }

        $flag->forceFill(['killed_at' => now(), 'killed_by' => $actor->id])->save();
        $this->flags->changed($flag);
        $this->audit->record('flag.killed', self::AUDIT_SUBJECT, $flag->key, ['from' => 'on', 'to' => 'off', 'stage' => $flag->stage->value], $actor);
    }

    /** Back on, at the rollout it had. */
    public function restore(FeatureFlag $flag, User $actor): void
    {
        if (! $flag->killed()) {
            return;
        }

        $flag->forceFill(['killed_at' => null, 'killed_by' => null])->save();
        $this->flags->changed($flag);
        $this->audit->record('flag.restored', self::AUDIT_SUBJECT, $flag->key, ['from' => 'off', 'to' => 'on', 'stage' => $flag->stage->value], $actor);
    }

    /** A release rolls its flags out to everyone. */
    public function toEveryone(FeatureFlag $flag, User $actor, string $release): void
    {
        if ($flag->stage === RolloutStage::Everyone) {
            return;
        }

        $before = self::describe($flag);
        $flag->forceFill(['stage' => RolloutStage::Everyone, 'stage_changed_at' => now(), 'rolled_back_at' => null, 'rolled_back_from' => null])->save();
        $this->flags->changed($flag);
        $this->audit->record('flag.rollout_changed', self::AUDIT_SUBJECT, $flag->key, ['before' => $before, 'after' => self::describe($flag), 'release' => $release], $actor);
    }

    /** Errors stayed above the threshold for 10 minutes: one step back, and the super admins are told. */
    public function rollBack(FeatureFlag $flag, float $rate): void
    {
        $from = $flag->stage;
        $to = $from->previous();

        if ($from === $to) {
            return;
        }

        $flag->forceFill(['stage' => $to, 'stage_changed_at' => now(), 'rolled_back_at' => now(), 'rolled_back_from' => $from])->save();
        $this->flags->changed($flag);
        $this->audit->record('flag.rolled_back', self::AUDIT_SUBJECT, $flag->key, ['from' => $from->value, 'to' => $to->value, 'error_rate' => round($rate, 1)], null, null);

        Notification::send($this->memberships->holders(Role::SuperAdmin), new FlagRolledBack($flag, $from, $rate));
    }

    /**
     * The rollout as the audit log shows it.
     *
     * @return array{stage: string, tenants: list<int>, states: list<string>, percent: int, roles: list<string>|null, threshold: int}
     */
    public static function describe(FeatureFlag $flag): array
    {
        return [
            'stage' => $flag->stage->value,
            'tenants' => $flag->tenant_ids ?? [],
            'states' => $flag->states ?? [],
            'percent' => $flag->percent,
            'roles' => $flag->roles,
            'threshold' => $flag->error_threshold,
        ];
    }
}
