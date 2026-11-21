<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Authorization\Role;
use App\Events\IntegrationSettingsChanged;
use App\Integrations\Checks\CheckTrigger;
use App\Integrations\Checks\TesterRegistry;
use App\Models\IntegrationSetting;
use App\Notifications\IntegrationPaused;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Facades\Notification;

/**
 * Every 15 minutes, checks each integration that runs on the vendor's test
 * system or live. After three failures in a row it pauses the integration
 * (features treat it as Off, never as demo data, so nothing pretends to
 * work) and emails the super admins. A passing "Test connection" resumes it.
 */
final readonly class HealthMonitor
{
    public const PAUSE_AFTER = 3;

    public function __construct(
        private IntegrationHub $hub,
        private ConnectionChecker $checker,
        private TesterRegistry $testers,
        private AuditLogger $audit,
        private Memberships $memberships,
    ) {}

    /** @return array{checked: int, failed: int, paused: int} */
    public function run(): array
    {
        $summary = ['checked' => 0, 'failed' => 0, 'paused' => 0];

        foreach ($this->hub->overview() as ['definition' => $definition, 'status' => $status]) {
            $environment = $status->mode->environment();

            // Only what's running on real vendor systems, not already paused,
            // and only vendors whose tester really contacts them.
            if ($environment === null || $status->pausedAt !== null || ! $this->testers->for($definition->id)->contactsVendor()) {
                continue;
            }

            $check = $this->checker->check($definition, $environment, CheckTrigger::Scheduled);
            $setting = IntegrationSetting::query()->findOrFail($definition->id);
            $summary['checked']++;

            if ($check->outcome->allowsLive()) {
                $setting->forceFill(['consecutive_failures' => 0])->save();

                continue;
            }

            $summary['failed']++;
            $failures = $setting->consecutive_failures + 1;
            $setting->forceFill(['consecutive_failures' => $failures])->save();

            if ($failures < self::PAUSE_AFTER) {
                continue;
            }

            $setting->forceFill(['paused_at' => now()])->save();
            IntegrationSettingsChanged::dispatch($definition->id);
            $summary['paused']++;

            $this->audit->record('integration.paused', IntegrationHub::AUDIT_SUBJECT, $definition->id, [
                'mode' => $setting->mode->value,
                'failures' => $failures,
                'reason' => $check->message,
            ]);

            Notification::send(
                $this->memberships->holders(Role::SuperAdmin),
                new IntegrationPaused($definition, $check),
            );
        }

        return $summary;
    }
}
