<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Integrations\Checks\CheckResult;
use App\Integrations\Checks\CheckTrigger;
use App\Integrations\Checks\TesterRegistry;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationDefinition;
use App\Models\IntegrationCheck;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs "Test connection" and the scheduled health checks, and records each
 * result (Sprint 2.4b). A passing manual test of the keys a paused
 * integration runs on switches it back on.
 */
final readonly class ConnectionChecker
{
    public function __construct(
        private TesterRegistry $testers,
        private CredentialVault $vault,
        private IntegrationHub $hub,
        private AuditLogger $audit,
    ) {}

    public function check(IntegrationDefinition $definition, CredentialEnvironment $environment, CheckTrigger $trigger, ?User $actor = null): IntegrationCheck
    {
        $started = hrtime(true);
        $result = $this->run($definition, $environment);
        $duration = (int) round((hrtime(true) - $started) / 1_000_000);

        return DB::transaction(function () use ($definition, $environment, $trigger, $actor, $result, $duration): IntegrationCheck {
            $check = IntegrationCheck::query()->create([
                'integration_id' => $definition->id,
                'environment' => $environment,
                'outcome' => $result->outcome,
                'message' => $result->message,
                'parameters' => $result->parameters,
                'duration_ms' => $duration,
                'trigger' => $trigger,
                'checked_by' => $actor?->id,
                'created_at' => now(),
            ]);

            if ($trigger === CheckTrigger::Manual) {
                $this->audit->record('integration.checked', IntegrationHub::AUDIT_SUBJECT, $definition->id, [
                    'environment' => $environment->value,
                    'outcome' => $result->outcome->value,
                ], $actor);

                $this->resumeIfFixed($definition, $environment, $check, $actor);
            }

            return $check;
        });
    }

    private function run(IntegrationDefinition $definition, CredentialEnvironment $environment): CheckResult
    {
        $credentials = $this->vault->get($definition->id, $environment);

        if ($definition->needsKeys() && $this->hub->missingRequired($definition, $credentials) !== []) {
            return CheckResult::failed('missingKeys');
        }

        try {
            return $this->testers->for($definition->id)->test($definition, $environment, $credentials);
        } catch (Throwable $exception) {
            // Never surface the exception text: it could echo a key back.
            report($exception);

            return CheckResult::failed('unexpected');
        }
    }

    private function resumeIfFixed(IntegrationDefinition $definition, CredentialEnvironment $environment, IntegrationCheck $check, ?User $actor): void
    {
        $setting = IntegrationSetting::query()->find($definition->id);

        if ($setting?->paused_at === null || $setting->mode->environment() !== $environment || ! $check->outcome->allowsLive()) {
            return;
        }

        $setting->forceFill(['paused_at' => null, 'consecutive_failures' => 0, 'updated_by' => $actor?->id])->save();

        $this->audit->record('integration.resumed', IntegrationHub::AUDIT_SUBJECT, $definition->id, [
            'mode' => $setting->mode->value,
        ], $actor);
    }
}
