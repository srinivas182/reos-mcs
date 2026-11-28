<?php

declare(strict_types=1);

namespace App\Licences;

use App\Authorization\Role;
use App\Licences\Regulators\RegulatorFile;
use App\Models\ProviderLicence;
use App\Models\ProviderProfile;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\ProviderNetwork\ProviderOnboarding;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Services\Licences\Licences;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service providers' professional licences (B10.3, decision 8):
 *
 * - **TREC inspectors** and **TDI licensees** are checked in seconds against
 *   the regulators' imported data: an active licence in the provider's name
 *   is verified; another name, or no record, goes to a reviewer.
 * - **NMLS IDs** always go to a REOS reviewer, who checks NMLS Consumer
 *   Access (no public API) from the link on the review.
 *
 * @phpstan-type ProviderLicenceRow array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}
 */
final readonly class ProviderLicences
{
    /** Licence kind => the register entry that checks it. */
    public const KINDS = ['trec_inspector' => 'trec-licence', 'tdi' => 'tdi-lookup', 'nmls' => 'nmls-lookup'];

    public function __construct(private ActiveRole $roles, private AuditLogger $audit) {}

    /** @return list<ProviderLicenceRow> */
    public function of(User $user): array
    {
        $this->allow($user);

        return $this->rowsFor($user);
    }

    /** @return list<ProviderLicenceRow> */
    public function rowsFor(User $user): array
    {
        return array_values(ProviderLicence::query()->where('user_id', $user->id)->orderBy('kind')->get()->map($this->row(...))->all());
    }

    /**
     * @param  'trec_inspector'|'tdi'|'nmls'  $kind
     * @return ProviderLicenceRow
     */
    public function add(User $user, string $kind, string $number): array
    {
        $this->allow($user);
        $number = trim($number);
        $values = match ($kind) {
            'nmls' => ['status' => ProviderLicence::REVIEW, 'checked_via' => 'reviewer', 'review_note' => self::note('providers.licences.notes.nmls'), 'name_on_record' => null, 'licence_type' => null, 'expires_on' => null],
            default => $this->check($user, $kind, $number),
        };

        $licence = ProviderLicence::query()->updateOrCreate(
            ['user_id' => $user->id, 'kind' => $kind],
            ['number' => $number, ...$values, 'reviewed_by' => null, 'reviewed_at' => null],
        );
        self::onboarding()->refresh($user);

        return $this->row($licence);
    }

    /** @return Collection<int, ProviderLicence> waiting for a reviewer, oldest first */
    public function waitingForReview(): Collection
    {
        return ProviderLicence::query()->with('user')->where('status', ProviderLicence::REVIEW)->oldest()->limit(200)->get();
    }

    public function approve(ProviderLicence $licence, User $reviewer): void
    {
        $licence->forceFill(['status' => ProviderLicence::VERIFIED, 'checked_via' => 'reviewer', 'reviewed_by' => $reviewer->id, 'reviewed_at' => CarbonImmutable::now(), 'review_note' => null])->save();
        $this->audit->record('provider_licence.approved', 'provider_licence', (string) $licence->id, ['kind' => $licence->kind, 'person' => $licence->user_id], $reviewer);
        self::onboarding()->refresh($licence->user);
    }

    public function reject(ProviderLicence $licence, User $reviewer, string $note): void
    {
        $licence->forceFill(['status' => ProviderLicence::REJECTED, 'reviewed_by' => $reviewer->id, 'reviewed_at' => CarbonImmutable::now(), 'review_note' => $note])->save();
        $this->audit->record('provider_licence.rejected', 'provider_licence', (string) $licence->id, ['kind' => $licence->kind, 'person' => $licence->user_id], $reviewer);
        self::onboarding()->refresh($licence->user);
    }

    /**
     * The monthly re-check (B15.2): a TREC inspector or TDI licence is looked
     * up again in the regulator's latest data. An expired or inactive licence
     * hides the profile until it's fixed. NMLS IDs and photo IDs were checked
     * by a reviewer and keep that answer.
     */
    public function recheck(ProviderLicence $licence): void
    {
        if (! in_array($licence->kind, ['trec_inspector', 'tdi'], true) || $licence->status === ProviderLicence::REJECTED) {
            return;
        }

        $values = $this->check($licence->user, $licence->kind === 'tdi' ? 'tdi' : 'trec_inspector', $licence->number);

        // A reviewer's approval stands for a name mismatch; only the licence's own state changes it.
        if ($licence->checked_via === 'reviewer' && $values['status'] === ProviderLicence::REVIEW) {
            $values['status'] = ProviderLicence::VERIFIED;
        }

        if ($values['expires_on'] !== null && CarbonImmutable::parse($values['expires_on'])->isPast()) {
            $values['status'] = ProviderLicence::INACTIVE;
        }

        $before = $licence->status;
        $licence->forceFill([...$values, 'checked_via' => $licence->checked_via === 'reviewer' ? 'reviewer' : $values['checked_via']])->save();

        if ($before !== $licence->status) {
            $this->audit->record('provider_licence.rechecked', 'provider_licence', (string) $licence->id, ['before' => $before, 'after' => $licence->status], null);
        }

        self::onboarding()->refresh($licence->user);
    }

    private static function onboarding(): ProviderOnboarding
    {
        return app(ProviderOnboarding::class);
    }

    /** NMLS Consumer Access's page for an individual's NMLS ID. */
    public static function consumerUrl(string $kind, string $number): ?string
    {
        $digits = RegulatorFile::digits($number);

        return $kind === 'nmls' && $digits !== '' ? "https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/{$digits}" : null;
    }

    /**
     * @param  'trec_inspector'|'tdi'  $kind
     * @return array{status: string, checked_via: string|null, review_note: string|null, name_on_record: string|null, licence_type: string|null, expires_on: string|null}
     */
    private function check(User $user, string $kind, string $number): array
    {
        $digits = RegulatorFile::digits($number);
        $authority = $kind === 'tdi' ? 'TDI' : 'TREC';
        $record = $digits === '' ? null : RegulatorRecord::query()->where('authority', $authority)->where('kind', $kind === 'tdi' ? 'insurance' : 'inspectors')
            ->where(static fn ($q) => $q->where('number_digits', $digits)->orWhere('npn', $digits))
            ->orderByDesc('active')->first();

        if ($record === null) {
            return ['status' => ProviderLicence::REVIEW, 'checked_via' => null, 'review_note' => self::note('providers.licences.notes.notFound', ['authority' => $authority]), 'name_on_record' => null, 'licence_type' => null, 'expires_on' => null];
        }

        $sameName = Licences::sameName($record->name, $user->name);

        return [
            'status' => ! $sameName ? ProviderLicence::REVIEW : ($record->active ? ProviderLicence::VERIFIED : ProviderLicence::INACTIVE),
            'checked_via' => self::KINDS[$kind],
            'review_note' => $sameName ? null : self::note('providers.licences.notes.otherName'),
            'name_on_record' => $record->name,
            'licence_type' => $record->licence_type,
            'expires_on' => $record->expires_on?->toDateString(),
        ];
    }

    /** @param  array<string, string>  $replace */
    private static function note(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }

    /** Providers, and people joining as one (B15.2) once they've chosen a category. */
    private function allow(User $user): void
    {
        abort_unless($this->roles->for($user)?->role === Role::Provider || ProviderProfile::query()->where('user_id', $user->id)->whereNotNull('provider_category_id')->exists(), 404);
    }

    /** @return ProviderLicenceRow */
    private function row(ProviderLicence $licence): array
    {
        return [
            'id' => $licence->id,
            'kind' => $licence->kind,
            'number' => $licence->number,
            'status' => $licence->status,
            'name_on_record' => $licence->name_on_record,
            'licence_type' => $licence->licence_type,
            'expires_on' => $licence->expires_on?->toDateString(),
            'checked_via' => $licence->checked_via,
            'review_note' => $licence->review_note,
            'consumer_url' => self::consumerUrl($licence->kind, $licence->number),
        ];
    }
}
