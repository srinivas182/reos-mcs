<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Licences\Regulators\RegulatorFile;
use App\Models\BrokerVerification;
use App\Models\Organisation;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Licences\Licences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verify your brokerage (A15.1a, broker journey screen 2, ADR 0069): only
 * the real designated broker creates a brokerage on REOS.
 *
 * - The person's **broker licence** is found in TREC's nightly records
 *   (B10.3): an individual broker licence (BRK), active, in the person's
 *   own name (same last name and first initial).
 * - **Their brokerages**: each business entity (LLC, corporation or
 *   partnership broker) whose designated broker TREC lists as them, and
 *   their own licence when they sponsor agents themselves. Each shows how
 *   many active agents TREC lists under it.
 * - **The role**: a designated broker continues to the plan and creates
 *   the brokerage; an office admin or team leader is invited by the
 *   designated broker instead, so no tenant is made for them.
 * - A brokerage already on REOS can't be claimed again.
 *
 * @phpstan-type Brokerage array{number: string, name: string, kind: 'entity'|'individual', sponsored: int, on_reos: bool}
 * @phpstan-type Lookup array{licence: array{number: string, name: string, status: string|null, active: bool, expires_on: string|null}, name_matches: bool, brokerages: list<Brokerage>}
 */
final readonly class BrokerVerifications
{
    public const ROLES = ['designated_broker', 'office_admin', 'team_leader'];

    /** TREC's business-entity broker licences. */
    private const ENTITIES = ['BLLC', 'BCRP', '6'];

    /** Agents (and brokers) a brokerage can sponsor. */
    private const SPONSORED = ['SALE', 'BRK'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * What TREC says about a broker licence, for the person to confirm.
     *
     * @return Lookup
     */
    public function lookup(User $user, string $number): array
    {
        $licence = $this->brokerLicence($number);

        return [
            'licence' => ['number' => $licence->number, 'name' => $licence->name, 'status' => $licence->status, 'active' => self::active($licence), 'expires_on' => $licence->expires_on?->toDateString()],
            'name_matches' => Licences::sameName($licence->name, $user->name),
            'brokerages' => $this->brokerages($licence),
        ];
    }

    /**
     * The person confirms a brokerage and their role. A designated broker
     * gets a verification to create the brokerage with; other roles get
     * null and are told to ask their designated broker for an invite.
     */
    public function confirm(User $user, string $number, string $brokerageNumber, string $role): ?BrokerVerification
    {
        $licence = $this->brokerLicence($number);

        if ($role !== 'designated_broker') {
            return null;
        }

        if (! self::active($licence)) {
            throw ValidationException::withMessages(['licence' => __('brokerage.verify.inactive')]);
        }

        if (! Licences::sameName($licence->name, $user->name)) {
            throw ValidationException::withMessages(['licence' => __('brokerage.verify.otherName', ['name' => $licence->name])]);
        }

        $brokerage = collect($this->brokerages($licence))->firstWhere('number', $brokerageNumber);

        if ($brokerage === null) {
            throw ValidationException::withMessages(['brokerage' => __('brokerage.verify.notYours')]);
        }

        if ($brokerage['on_reos']) {
            throw ValidationException::withMessages(['brokerage' => __('brokerage.verify.claimed', ['name' => $brokerage['name']])]);
        }

        $verification = BrokerVerification::query()->create([
            'uuid' => (string) Str::uuid7(), 'user_id' => $user->id, 'licence_number' => $licence->number, 'licence_name' => $licence->name,
            'brokerage_number' => $brokerage['number'], 'brokerage_name' => $brokerage['name'], 'brokerage_kind' => $brokerage['kind'],
            'sponsored' => $brokerage['sponsored'], 'role' => $role,
        ]);
        $this->audit->record('brokerage.verified', 'broker_verification', $verification->uuid, ['licence' => $licence->number, 'brokerage' => $brokerage['number'], 'sponsored' => $brokerage['sponsored']], $user);

        return $verification;
    }

    /** The person's latest unused verification, if still fresh (a day). */
    public function current(User $user, ?string $uuid = null): ?BrokerVerification
    {
        return BrokerVerification::query()->where('user_id', $user->id)->whereNull('used_at')
            ->when($uuid !== null, static fn ($q) => $q->where('uuid', $uuid))
            ->where('created_at', '>=', CarbonImmutable::now()->subDay())->latest('id')->first();
    }

    /** Agents TREC lists as sponsored by a brokerage number today. */
    public static function sponsoredCount(string $brokerageNumber): int
    {
        return RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')
            ->whereIn('licence_type', self::SPONSORED)->where('active', true)
            ->whereIn('sponsor_number', self::spellings($brokerageNumber))->count();
    }

    /**
     * The ways a licence number is written in TREC's files ("0600123" and
     * "600123" are the same licence).
     *
     * @return list<string>
     */
    public static function spellings(string $number): array
    {
        $digits = RegulatorFile::digits($number);

        return array_values(array_unique(array_filter([trim($number), $digits, ltrim($digits, '0'), str_pad(ltrim($digits, '0'), 7, '0', STR_PAD_LEFT)])));
    }

    private function brokerLicence(string $number): RegulatorRecord
    {
        $digits = RegulatorFile::digits($number);
        $record = $digits === '' ? null : RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')
            ->where('number_digits', $digits)->where('licence_type', 'BRK')->orderByDesc('active')->first();

        if ($record === null) {
            $other = $digits === '' ? null : RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')->where('number_digits', $digits)->first();

            throw ValidationException::withMessages(['licence' => $other === null ? __('brokerage.verify.notFound') : __('brokerage.verify.notBroker')]);
        }

        return $record;
    }

    /** @return list<Brokerage> */
    private function brokerages(RegulatorRecord $broker): array
    {
        $claimed = static fn (string $n): bool => Organisation::query()->where('trec_number', $n)->exists();
        $list = [];

        // Entities whose designated broker is this licence.
        $entities = RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')->whereIn('licence_type', self::ENTITIES)
            ->whereIn('sponsor_number', self::spellings($broker->number))->where('active', true)->orderBy('name')->limit(20)->get();

        foreach ($entities as $entity) {
            $list[] = ['number' => $entity->number, 'name' => $entity->name, 'kind' => 'entity', 'sponsored' => self::sponsoredCount($entity->number), 'on_reos' => $claimed($entity->number)];
        }

        // Their own licence, as an individual broker.
        $list[] = ['number' => $broker->number, 'name' => $broker->name, 'kind' => 'individual', 'sponsored' => self::sponsoredCount($broker->number), 'on_reos' => $claimed($broker->number)];

        return $list;
    }

    private static function active(RegulatorRecord $record): bool
    {
        return $record->active;
    }
}
