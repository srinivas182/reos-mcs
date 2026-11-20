<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Events\IntegrationSettingsChanged;
use App\Integrations\Catalogue;
use App\Integrations\ConnectionField;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Integrations\IntegrationStatus;
use App\Models\AuditEvent;
use App\Models\IntegrationCheck;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The Integration Hub (Sprint 2.4, ADR 0009): keys and run mode for every
 * service in the register, set by the super admin without a deploy.
 *
 * Every integration starts on demo data (Simulator). It can run on the
 * vendor's test system or live only once the matching keys are saved, so a
 * missing approval or key never blocks building a feature.
 */
final readonly class IntegrationHub
{
    public const AUDIT_SUBJECT = 'integration';

    public const DEFAULT_MODE = IntegrationMode::Simulator;

    public function __construct(
        private Catalogue $catalogue,
        private CredentialVault $vault,
        private AuditLogger $audit,
    ) {}

    /**
     * Every connectable service with its status, in register order.
     *
     * @return list<array{definition: IntegrationDefinition, status: IntegrationStatus}>
     */
    public function overview(): array
    {
        $settings = IntegrationSetting::query()->get()->keyBy('integration_id');
        $saved = $this->vault->saved();

        return array_map(fn (IntegrationDefinition $definition): array => [
            'definition' => $definition,
            'status' => $this->buildStatus($settings->get($definition->id), $saved[$definition->id] ?? []),
        ], $this->catalogue->all());
    }

    public function status(IntegrationDefinition $definition): IntegrationStatus
    {
        return $this->buildStatus(
            IntegrationSetting::query()->find($definition->id),
            array_values(array_filter(
                CredentialEnvironment::cases(),
                fn (CredentialEnvironment $environment): bool => $this->hasCompleteKeys($definition, $environment),
            )),
        );
    }

    public function mode(IntegrationDefinition $definition): IntegrationMode
    {
        return $this->status($definition)->mode;
    }

    /**
     * Each mode and, when it can't be chosen yet, the translation key saying why.
     *
     * @return array<value-of<IntegrationMode>, string|null>
     */
    public function modeAvailability(IntegrationDefinition $definition, ?IntegrationStatus $status = null): array
    {
        $status ??= $this->status($definition);

        $needsKeys = static fn (CredentialEnvironment $environment, string $reason): ?string => $definition->needsKeys() && ! $status->hasKeys($environment) ? $reason : null;

        return [
            IntegrationMode::Off->value => null,
            IntegrationMode::Simulator->value => null,
            IntegrationMode::Sandbox->value => $definition->hasTestSystem()
                ? $needsKeys(CredentialEnvironment::Sandbox, 'integrations.mode.needsSandboxKeys')
                : 'integrations.mode.noTestSystem',
            IntegrationMode::Live->value => $needsKeys(CredentialEnvironment::Live, 'integrations.mode.needsLiveKeys')
                ?? $this->liveCheckReason($definition),
        ];
    }

    /**
     * Live needs a check of the current live keys that didn't fail. Services
     * with no keys (public data) need no check.
     */
    private function liveCheckReason(IntegrationDefinition $definition): ?string
    {
        if (! $definition->needsKeys()) {
            return null;
        }

        $check = $this->latestCheck($definition, CredentialEnvironment::Live);
        $keysSaved = $this->vault->updatedAt($definition->id, CredentialEnvironment::Live);

        if ($check === null || ($keysSaved !== null && $check->created_at->lt($keysSaved))) {
            return 'integrations.mode.needsLiveTest';
        }

        return $check->outcome->allowsLive() ? null : 'integrations.mode.liveTestFailed';
    }

    /** The most recent check of one environment's keys. */
    public function latestCheck(IntegrationDefinition $definition, CredentialEnvironment $environment): ?IntegrationCheck
    {
        return IntegrationCheck::query()
            ->with('checker:id,name')
            ->where('integration_id', $definition->id)
            ->where('environment', $environment)
            ->latest('id')
            ->first();
    }

    public function setMode(IntegrationDefinition $definition, IntegrationMode $mode, ?User $actor): void
    {
        $reason = $this->modeAvailability($definition)[$mode->value];

        if ($reason !== null) {
            throw new InvalidArgumentException("{$definition->id} can't run as {$mode->value} yet ({$reason}).");
        }

        $status = $this->status($definition);
        $from = $status->mode;

        if ($from === $mode && $status->pausedAt === null) {
            return;
        }

        DB::transaction(function () use ($definition, $mode, $from, $actor): void {
            // Choosing a mode is a fresh start: it also lifts an automatic pause.
            IntegrationSetting::query()->updateOrCreate(
                ['integration_id' => $definition->id],
                ['mode' => $mode, 'consecutive_failures' => 0, 'paused_at' => null, 'updated_by' => $actor?->id],
            );

            $this->audit->record('integration.mode_changed', self::AUDIT_SUBJECT, $definition->id, [
                'from' => $from->value,
                'to' => $mode->value,
            ], $actor);

            IntegrationSettingsChanged::dispatch($definition->id);
        });
    }

    /**
     * What the saved keys would be after this input: a blank secret keeps the
     * saved value (secrets are never sent back to the browser); a blank plain
     * field clears it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function merge(IntegrationDefinition $definition, CredentialEnvironment $environment, array $input): array
    {
        $saved = $this->vault->get($definition->id, $environment);
        $merged = [];

        foreach ($definition->fields as $field) {
            $value = $input[$field->key] ?? null;
            $value = is_string($value) ? trim($value) : '';

            if ($value !== '') {
                $merged[$field->key] = $value;
            } elseif ($field->secret && isset($saved[$field->key])) {
                $merged[$field->key] = $saved[$field->key];
            }
        }

        return $merged;
    }

    /**
     * Required fields that have no value.
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public function missingRequired(IntegrationDefinition $definition, array $values): array
    {
        return array_values(array_map(
            static fn (ConnectionField $field): string => $field->key,
            array_filter($definition->fields, static fn (ConnectionField $field): bool => $field->required && ! isset($values[$field->key])),
        ));
    }

    /**
     * Saves keys and records which fields changed (never their values).
     *
     * @param  array<string, mixed>  $input
     */
    public function saveCredentials(IntegrationDefinition $definition, CredentialEnvironment $environment, array $input, ?User $actor): void
    {
        if (! $definition->needsKeys()) {
            throw new InvalidArgumentException("{$definition->id} takes no keys.");
        }

        $values = $this->merge($definition, $environment, $input);
        $missing = $this->missingRequired($definition, $values);

        if ($missing !== []) {
            throw new InvalidArgumentException("{$definition->id} is missing ".implode(', ', $missing).'.');
        }

        $saved = $this->vault->get($definition->id, $environment);
        $changed = array_values(array_filter(
            $definition->fieldKeys(),
            static fn (string $key): bool => ($saved[$key] ?? null) !== ($values[$key] ?? null),
        ));

        if ($changed === []) {
            return;
        }

        DB::transaction(function () use ($definition, $environment, $values, $actor, $changed): void {
            $this->vault->put($definition->id, $environment, $values, $actor);

            $this->audit->record('integration.credentials_updated', self::AUDIT_SUBJECT, $definition->id, [
                'environment' => $environment->value,
                'fields' => $changed,
            ], $actor);

            IntegrationSettingsChanged::dispatch($definition->id);
        });
    }

    /**
     * Removes keys. If the integration was running on them, it goes back to
     * demo data so nothing tries to call the vendor without keys.
     */
    public function removeCredentials(IntegrationDefinition $definition, CredentialEnvironment $environment, ?User $actor): void
    {
        if ($this->vault->get($definition->id, $environment) === []) {
            return;
        }

        DB::transaction(function () use ($definition, $environment, $actor): void {
            $mode = $this->mode($definition);

            $this->vault->forget($definition->id, $environment);

            $this->audit->record('integration.credentials_removed', self::AUDIT_SUBJECT, $definition->id, [
                'environment' => $environment->value,
            ], $actor);

            IntegrationSettingsChanged::dispatch($definition->id);

            if ($mode === $environment->mode()) {
                IntegrationSetting::query()->updateOrCreate(
                    ['integration_id' => $definition->id],
                    ['mode' => self::DEFAULT_MODE, 'updated_by' => $actor?->id],
                );

                $this->audit->record('integration.mode_changed', self::AUDIT_SUBJECT, $definition->id, [
                    'from' => $mode->value,
                    'to' => self::DEFAULT_MODE->value,
                ], $actor);
            }
        });
    }

    /**
     * What the screen may show about saved keys: plain fields in full, secrets
     * only as "saved" with their last four characters when long enough that
     * four characters give nothing useful away.
     *
     * @return array<string, array{saved: bool, value: string|null, last4: string|null}>
     */
    public function maskedCredentials(IntegrationDefinition $definition, CredentialEnvironment $environment): array
    {
        $saved = $this->vault->get($definition->id, $environment);
        $masked = [];

        foreach ($definition->fields as $field) {
            $value = $saved[$field->key] ?? null;

            $masked[$field->key] = [
                'saved' => $value !== null,
                'value' => $field->secret ? null : $value,
                'last4' => $field->secret && $value !== null && mb_strlen($value) >= 12 ? mb_substr($value, -4) : null,
            ];
        }

        return $masked;
    }

    /**
     * The latest changes to one integration, newest first.
     *
     * @return Collection<int, AuditEvent>
     */
    public function activity(IntegrationDefinition $definition, int $limit = 10): Collection
    {
        return AuditEvent::query()
            ->with('actor:id,name')
            ->where('subject_type', self::AUDIT_SUBJECT)
            ->where('subject_id', $definition->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<CredentialEnvironment>  $environments  environments with saved keys
     */
    private function buildStatus(?IntegrationSetting $setting, array $environments): IntegrationStatus
    {
        return new IntegrationStatus(
            mode: $setting === null ? self::DEFAULT_MODE : $setting->mode,
            hasSandboxKeys: in_array(CredentialEnvironment::Sandbox, $environments, true),
            hasLiveKeys: in_array(CredentialEnvironment::Live, $environments, true),
            pausedAt: $setting?->paused_at,
        );
    }

    private function hasCompleteKeys(IntegrationDefinition $definition, CredentialEnvironment $environment): bool
    {
        $values = $this->vault->get($definition->id, $environment);

        return $values !== [] && $this->missingRequired($definition, $values) === [];
    }
}
