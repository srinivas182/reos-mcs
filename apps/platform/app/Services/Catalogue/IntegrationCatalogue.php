<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Authorization\Permission;
use App\Catalogue\CatalogueStatus;
use App\Catalogue\LibraryGroup;
use App\Connections\ConnectorRegistry;
use App\Enums\OrganisationType;
use App\Integrations\AccountScope;
use App\Integrations\Catalogue;
use App\Integrations\IntegrationDefinition;
use App\Models\CatalogueSetting;
use App\Models\Connection;
use App\Models\IntegrationInterest;
use App\Models\Organisation;
use App\Models\ToolRequest;
use App\Models\User;
use App\Notifications\BrokerAsked;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Services\Integrations\IntegrationHub;
use App\Services\States\PersonStates;
use App\Services\Tenancy\SupportAccess;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The integrations catalogue (B3.2, ADR 0014): what people see for each tool
 * and who may use it.
 *
 * - **Status**: the register's `launch` until the super admin changes it
 *   (audited). Paused is the kill switch; retired shows its end date, then
 *   goes.
 * - **Availability**: by state and role now; by plan stored now and enforced
 *   with plans (B5.1). Beta tools are for chosen organisations.
 * - **Organisations** can hide a tool from their people, never enable one.
 * - **Honesty**: a tool whose connector isn't built yet, or that REOS has
 *   switched off, never offers "Connect": people see "Coming soon" or
 *   "Temporarily unavailable" instead.
 */
final readonly class IntegrationCatalogue
{
    public const ACTION_CONNECT = 'connect';

    public const ACTION_JOIN_BETA = 'join_beta';

    public const ACTION_NOTIFY = 'notify';

    /** Tools the brokerage connects for everyone (A4.3a): the agent asks their broker. */
    public const ACTION_ASK_BROKER = 'ask_broker';

    public const ACTION_NONE = 'none';

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private ActiveRole $activeRole,
        private AuditLogger $audit,
    ) {}

    /**
     * Tools people connect themselves (their own or their organisation's
     * account). Payment gateways are managed on their own screen.
     *
     * @return list<IntegrationDefinition>
     */
    public function forPeople(): array
    {
        return array_values(array_filter(
            $this->catalogue->all(),
            static fn (IntegrationDefinition $d): bool => $d->kind === 'integration' && in_array($d->accounts->value, ['user', 'tenant'], true) && $d->category !== 'payments',
        ));
    }

    /** @return list<IntegrationDefinition> Every integration (not platform services). */
    public function integrations(): array
    {
        return array_values(array_filter($this->catalogue->all(), static fn (IntegrationDefinition $d): bool => $d->kind === 'integration'));
    }

    public function setting(string $integrationId): CatalogueSetting
    {
        return CatalogueSetting::query()->find($integrationId) ?? new CatalogueSetting(['integration_id' => $integrationId]);
    }

    /** The status the super admin set, or the register's default. */
    public function status(IntegrationDefinition $definition, ?CatalogueSetting $setting = null): CatalogueStatus
    {
        $setting ??= $this->setting($definition->id);

        return $setting->status ?? CatalogueStatus::tryFrom($definition->launch) ?? CatalogueStatus::Enabled;
    }

    /** Whether connections to this tool may sync (not paused, not past retirement). */
    public function syncAllowed(string $integrationId): bool
    {
        $definition = $this->catalogue->find($integrationId);

        if ($definition === null) {
            return false;
        }

        $setting = $this->setting($integrationId);
        $status = $this->status($definition, $setting);

        return $status->syncs() && ! ($status === CatalogueStatus::Retired && $this->retiredNow($setting));
    }

    /**
     * The people's catalogue for this person: tools they may see, with what
     * they can do with each.
     *
     * @return list<array{definition: IntegrationDefinition, status: CatalogueStatus, group: LibraryGroup, action: string, interested: bool, retires_on: string|null, connected: int}>
     */
    public function forUser(User $user): array
    {
        $settings = CatalogueSetting::query()->get()->keyBy('integration_id');
        $membership = $this->activeRole->for($user);
        $state = app(PersonStates::class)->current($user);
        $hidden = $membership === null ? [] : $this->hiddenBy($membership->organisation);
        $brokerage = $this->brokerageOf($membership?->organisation);
        $manages = $membership !== null && $user->can(Permission::OrganisationManage->value);
        $interests = IntegrationInterest::query()->where('user_id', $user->id)->pluck('kind', 'integration_id')->all();
        $connected = Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)
            ->selectRaw('integration_id, count(*) as total')->groupBy('integration_id')->pluck('total', 'integration_id')->all();
        $items = [];

        foreach ($this->forPeople() as $definition) {
            $setting = $settings->get($definition->id) ?? new CatalogueSetting(['integration_id' => $definition->id]);
            $status = $this->status($definition, $setting);

            if (! $this->visible($status, $setting, $state, $membership?->role->value, in_array($definition->id, $hidden, true))) {
                continue;
            }

            [$shown, $action] = $this->forPerson($definition, $status, $setting, $membership?->organisation->id);

            // A brokerage connects these once for everyone: its agents ask their broker; the broker connects (screen 40, B8.2).
            if ($definition->accounts === AccountScope::Tenant && $brokerage !== null && ! $manages && ! in_array($shown, [CatalogueStatus::Paused, CatalogueStatus::Retired], true)) {
                $action = self::ACTION_ASK_BROKER;
            }

            $items[] = [
                'definition' => $definition,
                'status' => $shown,
                'group' => LibraryGroup::forCategory($definition->category),
                'action' => $action,
                'interested' => isset($interests[$definition->id]),
                'retires_on' => $status === CatalogueStatus::Retired ? $setting->retires_on?->toDateString() : null,
                'connected' => self::count($connected[$definition->id] ?? 0),
            ];
        }

        return $items;
    }

    /** Whether this person may start connecting this tool now. */
    public function canConnect(User $user, string $integrationId): bool
    {
        foreach ($this->forUser($user) as $item) {
            if ($item['definition']->id === $integrationId) {
                return $item['action'] === self::ACTION_CONNECT;
            }
        }

        return false;
    }

    /**
     * The person asks for a tool they can't connect yet: "Notify me" for one
     * coming soon, "Join beta" for a beta. False when neither applies.
     */
    public function askFor(User $user, string $integrationId): bool
    {
        $item = collect($this->forUser($user))->first(static fn (array $i): bool => $i['definition']->id === $integrationId);

        if ($item === null || ! in_array($item['action'], [self::ACTION_NOTIFY, self::ACTION_JOIN_BETA, self::ACTION_ASK_BROKER], true)) {
            return false;
        }

        $kind = match ($item['action']) {
            self::ACTION_JOIN_BETA => IntegrationInterest::BETA,
            self::ACTION_ASK_BROKER => IntegrationInterest::BROKER,
            default => IntegrationInterest::NOTIFY,
        };
        $new = ! IntegrationInterest::query()->where(['integration_id' => $integrationId, 'user_id' => $user->id])->exists();
        $this->setInterest($user, $integrationId, $kind, true);

        if ($kind === IntegrationInterest::BROKER && $new) {
            $this->askBroker($user, $item['definition']);
        }

        return true;
    }

    /**
     * The brokerage of the role in use: the brokerage itself, or a team's
     * brokerage. Null for everyone else.
     */
    public function brokerageOf(?Organisation $organisation): ?Organisation
    {
        return match ($organisation?->type) {
            OrganisationType::Brokerage => $organisation,
            OrganisationType::Team => $organisation->parent?->type === OrganisationType::Brokerage ? $organisation->parent : null,
            default => null,
        };
    }

    /**
     * "Ask my broker": the brokerage's owners hear who asked for which tool
     * (a minute later, so Undo can stop it), audited in the brokerage.
     */
    private function askBroker(User $user, IntegrationDefinition $definition): void
    {
        $brokerage = $this->brokerageOf($this->activeRole->for($user)?->organisation);

        if ($brokerage === null) {
            return;
        }

        $owners = app(SupportAccess::class)->owners($brokerage);
        Notification::send($owners, (new BrokerAsked($user, $definition->id, $definition->name))->delay(now()->addMinute()));
        app(Tenancy::class)->run($brokerage->id, fn () => $this->audit->record('connection.broker_asked', 'integration', $definition->id, ['brokerage_id' => $brokerage->id], $user, $brokerage->id));
    }

    /** "My tool isn't listed": counted when choosing what to connect next. */
    public function requestTool(User $user, string $name, ?string $note): ToolRequest
    {
        return ToolRequest::query()->create(['user_id' => $user->id, 'name' => trim($name), 'note' => $note === null || trim($note) === '' ? null : trim($note)]);
    }

    /** "Notify me" / "Join beta", or undo it. */
    public function setInterest(User $user, string $integrationId, string $kind, bool $interested): void
    {
        if ($interested) {
            IntegrationInterest::query()->firstOrCreate(['integration_id' => $integrationId, 'user_id' => $user->id, 'kind' => $kind]);
        } else {
            IntegrationInterest::query()->where(['integration_id' => $integrationId, 'user_id' => $user->id])->delete();
        }
    }

    /**
     * The super admin's changes, audited by field name.
     *
     * @param  array{status?: CatalogueStatus|null, retires_on?: string|null, states?: list<string>|null, roles?: list<string>|null, plans?: list<string>|null, beta_organisations?: list<int>, tenants_may_hide?: bool, tenants_may_require?: bool, show_coming_soon?: bool}  $changes
     */
    public function update(IntegrationDefinition $definition, array $changes, User $actor): void
    {
        DB::transaction(function () use ($definition, $changes, $actor): void {
            $setting = $this->setting($definition->id);
            $before = $this->status($definition, $setting);
            $setting->fill([...$changes, 'updated_by' => $actor->id]);
            $changed = array_keys(array_intersect_key($setting->getDirty(), $changes));

            if ($changed === []) {
                return;
            }

            $setting->save();
            $after = $this->status($definition, $setting);

            $this->audit->record('catalogue.updated', IntegrationHub::AUDIT_SUBJECT, $definition->id, [
                'fields' => $changed,
                'from' => $before->value,
                'to' => $after->value,
            ], $actor);
        });
    }

    /** Pause (kill switch) or resume a tool. Resuming goes back to the register's status. */
    public function pause(IntegrationDefinition $definition, bool $paused, User $actor): void
    {
        $this->update($definition, ['status' => $paused ? CatalogueStatus::Paused : null], $actor);
    }

    /** An organisation hides a tool from its people, or shows it again. */
    public function hide(Organisation $organisation, string $integrationId, bool $hidden, User $actor): void
    {
        if (! $hidden) {
            DB::table('organisation_hidden_integrations')->where(['organisation_id' => $organisation->id, 'integration_id' => $integrationId])->delete();

            return;
        }

        if (! $this->setting($integrationId)->tenants_may_hide) {
            return;
        }

        DB::table('organisation_hidden_integrations')->insertOrIgnore([
            'organisation_id' => $organisation->id,
            'integration_id' => $integrationId,
            'hidden_by' => $actor->id,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, int> "Notify me" and "Join beta" taps per tool. */
    public function demand(): array
    {
        /** @var array<string, int> */
        return IntegrationInterest::query()->selectRaw('integration_id, count(*) as total')->groupBy('integration_id')
            ->pluck('total', 'integration_id')->map(self::count(...))->all();
    }

    /** @return array<string, int> Connections per tool, across everyone. */
    public function connectedCounts(): array
    {
        /** @var array<string, int> */
        return Connection::query()->selectRaw('integration_id, count(*) as total')->groupBy('integration_id')
            ->pluck('total', 'integration_id')->map(self::count(...))->all();
    }

    /** @return list<string> */
    private function hiddenBy(Organisation $organisation): array
    {
        /** @var list<string> */
        return DB::table('organisation_hidden_integrations')->where('organisation_id', $organisation->id)->pluck('integration_id')->all();
    }

    private function visible(CatalogueStatus $status, CatalogueSetting $setting, string $state, ?string $role, bool $hidden): bool
    {
        return ! $hidden
            && ! ($status === CatalogueStatus::ComingSoon && ! $setting->show_coming_soon)
            && ! ($status === CatalogueStatus::Retired && $this->retiredNow($setting))
            && ($setting->states === null || in_array($state, $setting->states, true))
            && ($setting->roles === null || ($role !== null && in_array($role, $setting->roles, true)));
    }

    /**
     * What this person sees and can do. A tool whose connector isn't built
     * yet shows as coming soon; one REOS has switched off as paused.
     *
     * @return array{0: CatalogueStatus, 1: string}
     */
    private function forPerson(IntegrationDefinition $definition, CatalogueStatus $status, CatalogueSetting $setting, ?int $organisationId): array
    {
        $built = in_array($definition->id, ConnectorRegistry::ids(), true);
        $running = $this->hub->status($definition)->effectiveMode()->value !== 'off';

        return match ($status) {
            CatalogueStatus::Paused => [CatalogueStatus::Paused, self::ACTION_NONE],
            CatalogueStatus::Retired => [CatalogueStatus::Retired, self::ACTION_NONE],
            CatalogueStatus::ComingSoon => [CatalogueStatus::ComingSoon, self::ACTION_NOTIFY],
            CatalogueStatus::Beta, CatalogueStatus::Enabled => match (true) {
                ! $built => [CatalogueStatus::ComingSoon, self::ACTION_NOTIFY],
                ! $running => [CatalogueStatus::Paused, self::ACTION_NONE],
                $status === CatalogueStatus::Beta && ! in_array($organisationId, $setting->beta_organisations, true) => [CatalogueStatus::Beta, self::ACTION_JOIN_BETA],
                default => [$status, self::ACTION_CONNECT],
            },
        };
    }

    /**
     * What REOS offers for a tool to an organisation, before its own hiding
     * (Brokerage integrations, screen 40): the status shown and the action.
     *
     * @return array{0: CatalogueStatus, 1: string}
     */
    public function offerFor(IntegrationDefinition $definition, ?int $organisationId): array
    {
        $setting = $this->setting($definition->id);

        return $this->forPerson($definition, $this->status($definition, $setting), $setting, $organisationId);
    }

    /** Whether an organisation may hide this tool from its people (the super admin's switch). */
    public function mayHide(string $integrationId): bool
    {
        return $this->setting($integrationId)->tenants_may_hide;
    }

    /** @return list<string> the tools an organisation hides from its people */
    public function hiddenFor(Organisation $organisation): array
    {
        return $this->hiddenBy($organisation);
    }

    /** Database counts arrive as int or numeric string depending on the driver. */
    private static function count(mixed $value): int
    {
        return is_numeric($value) ? intval($value) : 0;
    }

    private function retiredNow(CatalogueSetting $setting): bool
    {
        return $setting->retires_on === null || $setting->retires_on->isPast();
    }
}
