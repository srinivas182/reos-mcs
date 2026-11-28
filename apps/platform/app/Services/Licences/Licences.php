<?php

declare(strict_types=1);

namespace App\Services\Licences;

use App\Enums\BrokerRole;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Leads\Invites;
use App\Licences\LicenceLookups;
use App\Licences\LicenceRecord;
use App\Models\Licence;
use App\Models\State;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A person's licences in every state (A5.1; agent journey screens 148 and
 * 149, web screen 23).
 *
 * - Each licence is checked with the state's regulator. A match in the
 *   account holder's name counts at once; a different name, a licence the
 *   regulator doesn't have (if the person asks), or a state with no check
 *   yet goes to a reviewer. Nothing counts until checked or approved.
 * - One licence per person and state; removing one can be undone.
 * - Licences in states that aren't live are tracked for renewal only.
 */
final readonly class Licences
{
    public const AUDIT_SUBJECT = 'licence';

    public function __construct(private LicenceLookups $lookups, private AuditLogger $audit, private PersonStates $states, private Invites $invites) {}

    /** @return Collection<int, Licence> Live states first, then by state. */
    public function of(User $user): Collection
    {
        return Licence::query()->with('state')->where('user_id', $user->id)
            ->join('states', 'states.code', '=', 'licences.state_code')
            ->orderByRaw("case when states.status = 'live' then 0 else 1 end")
            ->orderBy('states.name')
            ->select('licences.*')
            ->get();
    }

    /**
     * Checks and saves a licence.
     *
     * @return array{0: Licence, 1: LicenceCheck, 2: string|null} The licence, what the check found, and its source.
     *
     * @throws ValidationException when the regulator has no such licence and no review was asked for, or the state already has one
     */
    public function add(User $user, State $state, string $number, ?LicenceType $type, ?BrokerRole $role, bool $askReview = false): array
    {
        $number = trim($number);
        $existing = Licence::withTrashed()->where('user_id', $user->id)->where('state_code', $state->code)->first();

        if ($existing !== null && ! $existing->trashed()) {
            throw ValidationException::withMessages(['state' => __('licences.errors.already', ['state' => $state->name])]);
        }

        $lookup = $this->lookups->for($state);
        $record = $lookup?->find($state, $number, $user->name);

        if ($lookup !== null && $record === null && ! $askReview) {
            throw ValidationException::withMessages(['number' => __('licences.errors.notFound', ['state' => $state->name])]);
        }

        $check = match (true) {
            $lookup === null => LicenceCheck::NoLookup,
            $record === null => LicenceCheck::NotFound,
            ! self::sameName($record->name, $user->name) => LicenceCheck::NameMismatch,
            default => LicenceCheck::Verified,
        };
        $licenceType = $record->type ?? $type ?? LicenceType::SalesAgent;

        if ($role !== null && ! $role->allows($licenceType)) {
            throw ValidationException::withMessages(['broker_role' => __('licences.errors.role')]);
        }

        $verifiedBy = $check === LicenceCheck::Verified ? $lookup->id() : null;
        $status = $check === LicenceCheck::Verified ? $record->status : LicenceStatus::PendingReview;
        $note = match ($check) {
            LicenceCheck::NameMismatch => __('licences.review.nameMismatch', ['name' => $record->name]),
            LicenceCheck::NotFound => __('licences.review.notFound'),
            LicenceCheck::NoLookup => __('licences.review.noLookup'),
            LicenceCheck::Verified => null,
        };

        $licence = DB::transaction(function () use ($user, $state, $number, $record, $licenceType, $role, $existing, $verifiedBy, $status, $note): Licence {
            $licence = $existing ?? new Licence(['user_id' => $user->id, 'state_code' => $state->code]);
            $licence->forceFill([
                'deleted_at' => null,
                'type' => $licenceType,
                'number' => $number,
                'broker_role' => $role,
                'holder_name' => $record?->name,
                'licensed_on' => $record?->licensedOn,
                'expires_on' => $record?->expiresOn,
                'sponsor_name' => $record?->sponsor,
                'first_renewal' => $record !== null && self::firstPeriod($record),
                'status' => $status,
                'verified_by' => $verifiedBy,
                'verified_at' => $verifiedBy !== null ? CarbonImmutable::now() : null,
                'reviewed_by' => null,
                'review_note' => $note,
            ])->save();

            return $licence;
        });

        $this->audit->record('licence.added', self::AUDIT_SUBJECT, (string) $licence->id, [
            'state' => $state->code,
            'type' => $licenceType->value,
            'check' => $check->value,
        ], $user);

        // Invite and earn (B14.3b): a verified license earns whoever invited them their points.
        if ($licence->status === LicenceStatus::Active && $licence->verified_at !== null) {
            $this->invites->licensed($user);
        }

        return [$licence->load('state'), $check, $lookup?->source()];
    }

    /**
     * Looks a number up without saving it, so the phone can show what the
     * regulator holds before "Add licence" (agent journey screen 149).
     *
     * @return array{result: LicenceCheck, record: LicenceRecord|null, source: string|null}
     */
    public function preview(User $user, State $state, string $number): array
    {
        $lookup = $this->lookups->for($state);
        $record = $lookup?->find($state, trim($number), $user->name);

        return [
            'result' => match (true) {
                $lookup === null => LicenceCheck::NoLookup,
                $record === null => LicenceCheck::NotFound,
                ! self::sameName($record->name, $user->name) => LicenceCheck::NameMismatch,
                default => LicenceCheck::Verified,
            },
            'record' => $record,
            'source' => $lookup?->source(),
        ];
    }

    /** Sets who the licence holder supervises (changes their CE, A5.2). */
    public function setRole(Licence $licence, ?BrokerRole $role, User $actor): Licence
    {
        if ($role !== null && ! $role->allows($licence->type)) {
            throw ValidationException::withMessages(['broker_role' => __('licences.errors.role')]);
        }

        $licence->forceFill(['broker_role' => $role])->save();
        $this->audit->record('licence.role_changed', self::AUDIT_SUBJECT, (string) $licence->id, ['broker_role' => $role?->value], $actor);

        return $licence;
    }

    public function remove(Licence $licence, User $actor): void
    {
        $licence->delete();
        $this->audit->record('licence.removed', self::AUDIT_SUBJECT, (string) $licence->id, ['state' => $licence->state_code], $actor);
        $this->states->forget($licence->user, $licence->state_code);
    }

    public function restore(Licence $licence, User $actor): Licence
    {
        $licence->restore();
        $this->audit->record('licence.restored', self::AUDIT_SUBJECT, (string) $licence->id, ['state' => $licence->state_code], $actor);

        return $licence;
    }

    /** @return Collection<int, Licence> Waiting for a reviewer, oldest first. */
    public function waitingForReview(): Collection
    {
        return Licence::query()->with(['state', 'user'])->where('status', LicenceStatus::PendingReview)->oldest()->limit(200)->get();
    }

    public function approve(Licence $licence, User $reviewer): Licence
    {
        $licence->forceFill([
            'status' => LicenceStatus::Active,
            'verified_by' => 'reviewer',
            'verified_at' => CarbonImmutable::now(),
            'reviewed_by' => $reviewer->id,
        ])->save();
        $this->audit->record('licence.approved', self::AUDIT_SUBJECT, (string) $licence->id, ['state' => $licence->state_code, 'person' => $licence->user_id], $reviewer);
        $this->invites->licensed($licence->user);

        return $licence;
    }

    public function reject(Licence $licence, User $reviewer, string $note): Licence
    {
        $licence->forceFill(['status' => LicenceStatus::Rejected, 'reviewed_by' => $reviewer->id, 'review_note' => $note])->save();
        $this->audit->record('licence.rejected', self::AUDIT_SUBJECT, (string) $licence->id, ['state' => $licence->state_code, 'person' => $licence->user_id], $reviewer);
        $this->states->forget($licence->user, $licence->state_code);

        return $licence;
    }

    /**
     * The same person: the same last name and first initial, ignoring case,
     * accents and punctuation ("James R. Miller" matches "Miller, James").
     */
    public static function sameName(string $recorded, ?string $account): bool
    {
        $words = static function (string $name): array {
            if (str_contains($name, ',')) {
                [$last, $rest] = array_map('trim', explode(',', $name, 2));
                $name = $rest.' '.$last;
            }

            $plain = strtolower((string) preg_replace('/[^a-z ]/i', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $name)));

            return array_values(array_filter(explode(' ', $plain), static fn (string $word): bool => $word !== ''));
        };

        $a = $words($recorded);
        $b = $words((string) $account);

        if ($a === [] || $b === []) {
            return false;
        }

        return end($a) === end($b) && $a[0][0] === $b[0][0];
    }

    /** First licensed within the current two-year period: the first renewal is next. */
    private static function firstPeriod(LicenceRecord $record): bool
    {
        return $record->licensedOn !== null && $record->expiresOn !== null
            && $record->licensedOn->greaterThan($record->expiresOn->subYears(2)->subMonths(3));
    }
}
