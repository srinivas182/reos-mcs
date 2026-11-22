<?php

declare(strict_types=1);

namespace App\Billing;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * The refund policy (B5.3; screen 66), set by the super admin: full and
 * partial refunds, full refunds only, or no refunds from REOS; the window
 * in days, and how much of a course may be done (checked once the course
 * player tracks progress, Sprint 8). Defaults are the Sprint 5 decisions:
 * full and partial, 7 days, under 25%. Changes are audited.
 */
final readonly class RefundPolicy
{
    public const KEY = 'billing.refund_policy';

    public const FULL_AND_PARTIAL = 'full_and_partial';

    public const FULL_ONLY = 'full_only';

    public const NONE = 'none';

    public const MODES = [self::FULL_AND_PARTIAL, self::FULL_ONLY, self::NONE];

    public function __construct(private AuditLogger $audit) {}

    /** @return array{mode: string, window_days: int, max_progress_percent: int} */
    public function get(): array
    {
        $setting = PlatformSetting::query()->find(self::KEY);
        $value = $setting === null ? [] : $setting->value;
        $mode = $value['mode'] ?? null;
        $days = $value['window_days'] ?? null;
        $progress = $value['max_progress_percent'] ?? null;

        return [
            'mode' => is_string($mode) && in_array($mode, self::MODES, true) ? $mode : self::FULL_AND_PARTIAL,
            'window_days' => is_int($days) ? $days : config()->integer('reos.billing.refund_days', 7),
            'max_progress_percent' => is_int($progress) ? $progress : config()->integer('reos.billing.refund_max_progress_percent', 25),
        ];
    }

    /** @param  array{mode: string, window_days: int, max_progress_percent: int}  $policy */
    public function set(array $policy, User $actor): void
    {
        $before = $this->get();

        if ($before === $policy) {
            return;
        }

        PlatformSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => $policy, 'updated_by' => $actor->id]);
        $this->audit->record('platform.setting_changed', 'platform_setting', self::KEY, ['from' => $before, 'to' => $policy], $actor);
    }
}
