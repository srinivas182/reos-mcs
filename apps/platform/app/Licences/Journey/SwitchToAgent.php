<?php

declare(strict_types=1);

namespace App\Licences\Journey;

use App\Authorization\Role;
use App\Billing\SponsorCodes;
use App\Enums\LicenceStatus;
use App\Models\BrokerOffer;
use App\Models\Licence;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Notifications\LicenceFound;
use App\Practice\StudyPlans;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use App\Services\Licences\LicenceCheck;
use App\Services\Licences\Licences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Switching to agent (A10.3c, ADR 0041; candidate screens f03 and 20). The
 * candidate enters their licence number; REOS checks the regulator's record
 * (TREC's, through the licence lookup), which must be active and in the
 * account holder's name. If it isn't active yet, REOS checks daily and
 * tells them when it is. Then one of three ways in: the brokerage whose
 * offer they accepted (broker seat), a sponsor's code (association), or on
 * their own. Same account: progress, certificates and history stay.
 *
 * @phpstan-type SwitchData array{number: string|null, check: 'verified'|'inactive'|'name_mismatch'|'not_found'|'no_lookup'|null, checked_at: string|null, watching: bool, record: array{name: string, type: string, status: string, licensed_on: string|null, sponsor: string|null}|null, can_switch: bool, broker: array{id: string, name: string}|null, switched: bool}
 */
final readonly class SwitchToAgent
{
    public const PATHS = ['broker', 'association', 'alone'];

    public function __construct(
        private LicenceJourneys $journeys,
        private Licences $licences,
        private Memberships $memberships,
        private ActiveRole $roles,
        private SponsorCodes $codes,
        private AuditLogger $audit,
    ) {}

    /** @return SwitchData */
    public function show(User $user): array
    {
        $journey = $this->journeys->journey($user);
        $record = null;

        if ($journey->licence_number !== null && $journey->licence_check !== null && in_array($journey->licence_check, ['verified', 'inactive', 'name_mismatch'], true)) {
            $state = State::query()->where('code', $journey->state_code)->firstOrFail();
            $found = $this->licences->preview($user, $state, $journey->licence_number)['record'];
            $record = $found === null ? null : [
                'name' => $found->name, 'type' => $found->type->value, 'status' => $found->status->value,
                'licensed_on' => $found->licensedOn?->toDateString(), 'sponsor' => $found->sponsor,
            ];
        }

        $broker = $this->acceptedOffer($user);

        return [
            'number' => $journey->licence_number,
            'check' => match ($journey->licence_check) {
                'verified' => 'verified', 'inactive' => 'inactive', 'name_mismatch' => 'name_mismatch', 'not_found' => 'not_found', 'no_lookup' => 'no_lookup',
                default => null,
            },
            'checked_at' => $journey->licence_checked_at?->toIso8601String(),
            'watching' => $journey->licence_watch,
            'record' => $record,
            'can_switch' => $journey->licence_check === 'verified' && $journey->switched_at === null,
            'broker' => $broker === null ? null : ['id' => $broker->uuid, 'name' => $broker->organisation->name],
            'switched' => $journey->switched_at !== null,
        ];
    }

    /** Checks the number with the regulator now; if it isn't active in their name yet, REOS keeps checking daily. */
    public function check(User $user, string $number): void
    {
        $journey = $this->journeys->journey($user);
        $journey->forceFill(['licence_number' => trim($number)])->save();
        $this->refresh($user, $journey);
    }

    /**
     * Daily (`reos:licence:watch`): numbers that weren't active yet; the
     * candidate is told once when one is.
     */
    public function watchDue(): int
    {
        $found = 0;

        foreach (LicenceJourney::query()->where('licence_watch', true)->whereNotNull('licence_number')->with('user')->get() as $journey) {
            if ($this->refresh($journey->user, $journey) === 'verified') {
                $journey->forceFill(['licence_found_at' => now()])->save();
                $journey->user->notify(new LicenceFound($journey));
                $found++;
            }
        }

        return $found;
    }

    /**
     * Becomes an agent on the same account, one of three ways in.
     *
     * @param  'broker'|'association'|'alone'  $path
     */
    public function switch(User $user, string $path, ?string $code): Organisation
    {
        $journey = $this->journeys->journey($user);

        if ($journey->switched_at !== null) {
            throw ValidationException::withMessages(['path' => __('licence.switch.errors.done')]);
        }

        if ($this->refresh($user, $journey) !== 'verified') {
            throw ValidationException::withMessages(['number' => __('licence.switch.errors.notActive')]);
        }

        $state = State::query()->where('code', $journey->state_code)->firstOrFail();
        $offer = $path === 'broker' ? ($this->acceptedOffer($user) ?? throw ValidationException::withMessages(['path' => __('licence.switch.errors.noBroker')])) : null;

        if ($path === 'association') {
            if ($code === null || trim($code) === '') {
                throw ValidationException::withMessages(['code' => __('licence.switch.errors.code')]);
            }

            // The association's code: its benefits on this account (B5.1).
            $this->codes->redeem($user, $code);
        }

        $organisation = $offer === null ? Organisation::platform() : $offer->organisation;

        DB::transaction(function () use ($user, $journey, $state, $organisation): void {
            // The regulator's record, saved as the agent's licence (A5.1).
            if (! Licence::query()->where('user_id', $user->id)->where('state_code', $state->code)->exists()) {
                $this->licences->add($user, $state, (string) $journey->licence_number, null, null);
            }

            $this->memberships->grant($user, Role::Agent, $organisation, $user);
            $today = StudyPlans::today()->toDateString();
            $steps = $journey->steps;
            $steps['active'] ??= $today;

            if ($organisation->id !== Organisation::platform()->id) {
                $steps['sponsor'] ??= $today;
            }

            $journey->forceFill(['steps' => $steps, 'switched_at' => now(), 'licence_watch' => false])->save();
        });

        $this->roles->choose($user, Role::Agent, $organisation->id);
        $this->audit->record('licence.switched_to_agent', 'user', (string) $user->id, ['path' => $path, 'organisation_id' => $organisation->id, 'state' => $state->code], $user);

        return $organisation;
    }

    /** Looks the number up and records what was found; returns the check. */
    private function refresh(User $user, LicenceJourney $journey): string
    {
        $state = State::query()->where('code', $journey->state_code)->firstOrFail();
        $preview = $this->licences->preview($user, $state, (string) $journey->licence_number);
        $record = $preview['record'];
        $check = match (true) {
            $preview['result'] === LicenceCheck::Verified && $record !== null && $record->status !== LicenceStatus::Active => 'inactive',
            default => $preview['result']->value,
        };

        $journey->forceFill([
            'licence_check' => $check,
            'licence_checked_at' => now(),
            // Keep checking while it isn't active in their name yet (not found, or not active).
            'licence_watch' => in_array($check, ['inactive', 'not_found'], true) && $journey->switched_at === null,
        ])->save();

        return $check;
    }

    private function acceptedOffer(User $user): ?BrokerOffer
    {
        return BrokerOffer::query()->where('candidate_id', $user->id)->where('status', BrokerOffer::ACCEPTED)->with('organisation')->latest('answered_at')->first();
    }
}
