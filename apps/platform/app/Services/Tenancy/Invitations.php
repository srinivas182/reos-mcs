<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Enums\Locale;
use App\Enums\OrganisationType;
use App\Messaging\TextMessagesFactory;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\InvitationSent;
use App\Notifications\SeatsRequested;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\Privacy\Consents;
use App\Support\PhoneNumber;
use App\Support\Plural;
use App\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invitations into a tenant (A4.3a; web journey screens 32 and 41).
 *
 * - One person or many (pasted lines or a CSV), by email or text, with a
 *   role and, in a brokerage, a team.
 * - Each invite has a link and a short code. Both expire after 14 days,
 *   with one reminder at day 7. Accepting creates or links the account,
 *   gives the role and fills a seat; an invite works once.
 * - Seats: invites for the seat role stop at the tenant's limit (all or
 *   nothing per batch), with a way to ask the owner for more.
 * Sent, resent, revoked, accepted and seat requests are audited.
 */
final readonly class Invitations
{
    public const AUDIT_SUBJECT = 'invitation';

    public const DAYS = 14;

    public const REMIND_AFTER_DAYS = 7;

    public const MAX_BATCH = 500;

    /** Column names of an invite CSV, in English and Spanish. */
    private const HEADER_WORDS = ['name', 'email', 'phone', 'mobile', 'nombre', 'correo', 'teléfono', 'telefono', 'celular'];

    /** No 0/O or 1/I, so codes read out clearly. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private Tenancy $tenancy,
        private AuditLogger $audit,
        private Seats $seats,
        private Memberships $memberships,
        private TextMessagesFactory $texts,
        private SingleSignOn $sso,
        private SupportAccess $support,
        private Consents $consents,
    ) {}

    /**
     * Roles this admin may invite people into.
     *
     * @return list<Role>
     */
    public function roles(Organisation $actorOrganisation): array
    {
        return match ($actorOrganisation->type) {
            OrganisationType::Brokerage => [Role::Agent, Role::Mentor, Role::Broker],
            // A team leader invites agents into their own team.
            OrganisationType::Team => [Role::Agent],
            OrganisationType::School => [Role::Instructor, Role::Candidate, Role::Mentor, Role::SchoolAdmin],
            OrganisationType::Association => [Role::AssociationAdmin],
            OrganisationType::ProviderBusiness => [Role::Provider],
            OrganisationType::Platform => [],
        };
    }

    /**
     * Teams an admin may invite into: a brokerage's teams, or a team leader's own team.
     *
     * @return Collection<int, Organisation>
     */
    public function teams(Organisation $actorOrganisation): Collection
    {
        return match ($actorOrganisation->type) {
            OrganisationType::Brokerage => Organisation::query()->where('parent_id', $actorOrganisation->id)->where('type', OrganisationType::Team->value)->orderBy('name')->get(),
            OrganisationType::Team => collect([$actorOrganisation]),
            default => collect(),
        };
    }

    /**
     * What the invite sheet needs: roles, teams, whether texts can go out,
     * and the seats.
     *
     * @return array{roles: list<array{value: string, label: string, seat: bool}>, teams: list<array{id: int, name: string}>, can_text: bool, seats: array{limit: int|null, used: int, pending: int, left: int|null, payer: string, role: string|null}, locale: string}
     */
    public function options(Organisation $actorOrganisation): array
    {
        $usage = $this->seats->usage($actorOrganisation);

        return [
            'roles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => trans()->get('roles.names.'.$role->value),
                'seat' => $this->seats->takesSeat($actorOrganisation, $role),
            ], $this->roles($actorOrganisation)),
            'teams' => array_values($this->teams($actorOrganisation)->map(static fn (Organisation $team): array => ['id' => $team->id, 'name' => $team->name])->all()),
            'can_text' => $this->texts->available(),
            'seats' => [...$usage, 'role' => $usage['role']?->value],
            'locale' => Locale::current()->value,
        ];
    }

    /**
     * People from pasted text or a CSV: one per line, each an email or a
     * phone number, optionally with a name ("Rosa Lima, rosa@example.com").
     * A CSV's header row (name, email, phone) is understood and skipped.
     *
     * @return array{people: list<array{email: string|null, phone: string|null, name: string|null}>, invalid: list<string>}
     */
    public function parse(string $text): array
    {
        $people = [];
        $invalid = [];

        foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
            $cells = array_map(static fn (?string $cell): string => trim($cell ?? '', " \t\"'"), str_getcsv(trim($line), str_contains($line, ';') && ! str_contains($line, ',') ? ';' : ',', '"', ''));

            // A CSV header row (name, email, phone), wherever the file starts.
            $words = array_filter(array_map('mb_strtolower', $cells));

            if ($words !== [] && array_diff($words, self::HEADER_WORDS) === []) {
                continue;
            }

            $cells = array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));

            if ($cells === []) {
                continue;
            }

            $person = ['email' => null, 'phone' => null, 'name' => null];

            foreach ($cells as $cell) {
                if (filter_var($cell, FILTER_VALIDATE_EMAIL) !== false) {
                    $person['email'] ??= mb_strtolower($cell);
                } elseif (($phone = PhoneNumber::normalise($cell)) !== null && preg_match('/[a-z]/i', $cell) !== 1) {
                    $person['phone'] ??= $phone;
                } elseif (mb_strlen($cell) <= 120 && preg_match('/\p{L}/u', $cell) === 1 && ! str_contains($cell, '@')) {
                    $person['name'] ??= $cell;
                }
            }

            if ($person['email'] === null && $person['phone'] === null) {
                $invalid[] = mb_substr(trim($line), 0, 120);
            } else {
                $people[] = $person;
            }
        }

        return ['people' => $people, 'invalid' => $invalid];
    }

    /**
     * Sends invites. Email wins when a person has both an email and a phone.
     *
     * @param  list<array{email: string|null, phone: string|null, name: string|null}>  $people
     * @return array{sent: int, skipped: list<array{contact: string, reason: string}>}
     */
    public function invite(Organisation $actorOrganisation, User $actor, array $people, Role $role, ?Organisation $team, string $locale): array
    {
        if (! in_array($role, $this->roles($actorOrganisation), true)) {
            throw ValidationException::withMessages(['role' => __('invites.errors.role')]);
        }

        if ($actorOrganisation->type === OrganisationType::Team) {
            if ($team !== null && $team->id !== $actorOrganisation->id) {
                throw ValidationException::withMessages(['team_id' => __('invites.errors.team')]);
            }

            $team = $actorOrganisation;
        }

        if ($team !== null && ($team->type !== OrganisationType::Team || ! $this->teams($actorOrganisation)->contains('id', $team->id) || ! in_array(OrganisationType::Team, $role->organisationTypes(), true))) {
            throw ValidationException::withMessages(['team_id' => __('invites.errors.team')]);
        }

        if (count($people) > self::MAX_BATCH) {
            throw ValidationException::withMessages(['people' => __('invites.errors.tooMany', ['max' => self::MAX_BATCH])]);
        }

        $tenant = $this->seats->owner($actorOrganisation);
        $target = $team ?? $tenant;
        $canText = $this->texts->available();
        $seen = [];
        $send = [];
        $skipped = [];

        foreach ($people as $person) {
            $contact = $person['email'] ?? $person['phone'];

            if ($contact === null) {
                continue;
            }

            $reason = match (true) {
                isset($seen[$contact]) => 'duplicate',
                $person['email'] === null && ! $canText => 'noText',
                $this->isMember($person, $target, $role) => 'member',
                $this->pendingFor($tenant, $person) !== null => 'invited',
                default => null,
            };
            $seen[$contact] = true;

            if ($reason !== null) {
                $skipped[] = ['contact' => $person['email'] ?? PhoneNumber::mask($contact), 'reason' => $reason];
            } else {
                $send[] = $person;
            }
        }

        if ($send !== [] && $this->seats->takesSeat($target, $role)) {
            $usage = $this->seats->usage($tenant);

            if ($usage['left'] !== null && count($send) > $usage['left']) {
                throw ValidationException::withMessages(['seats' => Plural::of('invites.errors.seatsFull', $usage['left'], [
                    'left' => $usage['left'],
                    'limit' => $usage['limit'],
                    'wanted' => count($send),
                ])]);
            }
        }

        $locale = (Locale::tryFrom($locale) ?? Locale::current())->value;

        $invitations = DB::transaction(fn (): array => $this->tenancy->run($tenant->id, function () use ($send, $tenant, $team, $role, $actor, $locale): array {
            $created = [];

            foreach ($send as $person) {
                [$token, $code] = [Str::random(48), $this->code()];
                $created[] = Invitation::query()->create([
                    'organisation_id' => $tenant->id,
                    'team_id' => $team?->id,
                    'role' => $role,
                    'email' => $person['email'],
                    'phone' => $person['email'] === null ? $person['phone'] : null,
                    'name' => $person['name'],
                    'locale' => $locale,
                    'token_hash' => hash('sha256', $token),
                    'code_hash' => hash('sha256', $code),
                    'secret' => ['token' => $token, 'code' => $code],
                    'status' => Invitation::PENDING,
                    'invited_by' => $actor->id,
                    'expires_at' => now()->addDays(self::DAYS),
                ]);
            }

            return $created;
        }));

        foreach ($invitations as $invitation) {
            $this->deliver($invitation);
        }

        if ($invitations !== []) {
            $this->record('invite.sent', self::AUDIT_SUBJECT, (string) $tenant->id, [
                'count' => count($invitations),
                'role' => $role->value,
                'team_id' => $team?->id,
                'after' => ['invitations' => array_map(static fn (Invitation $i): array => ['id' => $i->id, 'contact' => $i->email ?? PhoneNumber::mask((string) $i->phone)], $invitations)],
            ], $actor, $tenant->id);
        }

        return ['sent' => count($invitations), 'skipped' => $skipped];
    }

    /**
     * Invitations of a tenant, newest first, for the Agents screen.
     *
     * @return Collection<int, Invitation>
     */
    public function of(Organisation $actorOrganisation): Collection
    {
        $tenant = $this->seats->owner($actorOrganisation);

        return $this->tenancy->run($tenant->id, static fn (): Collection => Invitation::query()
            ->with(['team', 'inviter'])
            ->where('organisation_id', $tenant->id)
            ->when($actorOrganisation->type === OrganisationType::Team, static fn ($query) => $query->where('team_id', $actorOrganisation->id))
            ->whereIn('status', [Invitation::PENDING])
            ->latest('id')
            ->limit(500)
            ->get());
    }

    /** An invitation this admin may manage (resend, revoke), else null. */
    public function manageable(Organisation $actorOrganisation, int $id): ?Invitation
    {
        return $this->of($actorOrganisation)->firstWhere('id', $id);
    }

    /** Resend: the same link and code, with 14 more days. */
    public function resend(Invitation $invitation, User $actor): Invitation
    {
        $this->tenancy->run($invitation->organisation_id, fn () => $invitation->forceFill(['expires_at' => now()->addDays(self::DAYS), 'reminded_at' => null])->save());
        $this->deliver($invitation);
        $this->record('invite.resent', self::AUDIT_SUBJECT, (string) $invitation->id, ['contact' => $this->shownContact($invitation)], $actor, $invitation->organisation_id);

        return $invitation;
    }

    public function revoke(Invitation $invitation, User $actor): void
    {
        $this->tenancy->run($invitation->organisation_id, fn () => $invitation->forceFill(['status' => Invitation::REVOKED])->save());
        $this->record('invite.revoked', self::AUDIT_SUBJECT, (string) $invitation->id, [
            'contact' => $this->shownContact($invitation),
            'before' => ['status' => Invitation::PENDING],
            'after' => ['status' => Invitation::REVOKED],
        ], $actor, $invitation->organisation_id);
    }

    public function findByToken(string $token): ?Invitation
    {
        return $this->tenancy->asPlatform(static fn (): ?Invitation => Invitation::query()->with(['organisation', 'team', 'inviter'])->where('token_hash', hash('sha256', $token))->first());
    }

    public function findByCode(string $code): ?Invitation
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        if (strlen($code) !== 8) {
            return null;
        }

        return $this->tenancy->asPlatform(static fn (): ?Invitation => Invitation::query()->with(['organisation', 'team', 'inviter'])
            ->where('code_hash', hash('sha256', substr($code, 0, 4).'-'.substr($code, 4)))->first());
    }

    /** The invite page's link for this invitation, on the tenant's own address. */
    public function link(Invitation $invitation): string
    {
        return $this->sso->origin($invitation->organisation).'/invite/'.$invitation->secret['token'];
    }

    /** Whether this account is the one the invitation was sent to. */
    public function isFor(Invitation $invitation, User $user): bool
    {
        return $invitation->email !== null
            ? $user->email !== null && mb_strtolower($user->email) === $invitation->email
            : $invitation->phone !== null && $user->phone === $invitation->phone;
    }

    /** The account that already uses the invitation's email or phone, if any. */
    public function existingAccount(Invitation $invitation): ?User
    {
        return $invitation->email !== null
            ? User::query()->whereRaw('lower(email) = ?', [$invitation->email])->first()
            : User::query()->where('phone', $invitation->phone)->first();
    }

    /**
     * Joins: gives the role (taking a seat) and closes the invitation. A
     * person already holding the role there just closes it.
     */
    public function accept(Invitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $fresh = $this->tenancy->asPlatform(static fn (): ?Invitation => Invitation::query()->lockForUpdate()->find($invitation->id));

            if ($fresh === null || ! $fresh->open()) {
                throw ValidationException::withMessages(['invite' => __('invites.errors.closed')]);
            }

            if (! $this->isFor($fresh, $user)) {
                throw ValidationException::withMessages(['invite' => __('invites.errors.otherPerson')]);
            }

            $target = $invitation->target();

            // Already holding the role elsewhere in the tenant takes no second seat.
            if ($this->memberships->find($user, $fresh->role, $target->id) === null) {
                $this->tenancy->run($fresh->organisation_id, fn () => $this->memberships->grant($user, $fresh->role, $target, $user));
            }

            $this->tenancy->run($fresh->organisation_id, static fn () => $fresh->forceFill([
                'status' => Invitation::ACCEPTED,
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ])->save());

            $this->record('invite.accepted', self::AUDIT_SUBJECT, (string) $fresh->id, [
                'user_id' => $user->id,
                'role' => $fresh->role->value,
                'organisation_id' => $target->id,
            ], $user, $fresh->organisation_id);
        });
    }

    /**
     * A new account for the person invited: the link proved they own the
     * email or phone, so it's verified. Email invites need a password;
     * phone invites sign in with a texted code.
     */
    public function register(Invitation $invitation, string $name, ?string $password): User
    {
        if ($this->existingAccount($invitation) !== null) {
            throw ValidationException::withMessages(['invite' => __('invites.errors.haveAccount')]);
        }

        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $invitation->email !== null ? $password : null,
                'locale' => $invitation->locale,
            ]);
            $user->forceFill($invitation->email !== null
                ? ['email_verified_at' => now()]
                : ['phone' => $invitation->phone, 'phone_verified_at' => now()])->save();
            $this->consents->signedUp($user);
            $this->accept($invitation, $user);

            return $user;
        });
    }

    /** The day-7 reminder, once per invitation. Returns how many were sent. */
    public function remind(): int
    {
        $due = $this->tenancy->asPlatform(static fn (): Collection => Invitation::query()->with(['organisation', 'team', 'inviter'])
            ->where('status', Invitation::PENDING)
            ->whereNull('reminded_at')
            ->where('sent_at', '<=', now()->subDays(self::REMIND_AFTER_DAYS))
            ->where('expires_at', '>', now())
            ->limit(1000)
            ->get());

        foreach ($due as $invitation) {
            $this->deliver($invitation, reminder: true);
            $this->tenancy->run($invitation->organisation_id, static fn () => $invitation->forceFill(['reminded_at' => now()])->save());
        }

        return $due->count();
    }

    /**
     * "Ask for more seats": to the tenant's owners, or to REOS when the
     * owner asks. Once a day per person.
     *
     * @return bool false when already asked today
     */
    public function askForSeats(Organisation $actorOrganisation, User $actor, ?string $note): bool
    {
        $tenant = $this->seats->owner($actorOrganisation);

        if (! Cache::add("reos:seat-request:{$tenant->id}:{$actor->id}", true, 86_400)) {
            return false;
        }

        $owners = array_values(array_filter($this->support->owners($tenant), static fn (User $owner): bool => $owner->id !== $actor->id));
        $recipients = $owners !== [] ? $owners : $this->memberships->holders(Role::SuperAdmin)->all();
        $usage = $this->seats->usage($tenant);

        Notification::send($recipients, new SeatsRequested($tenant, $actor, $usage['limit'], $note));
        $this->record('seats.requested', 'organisation', (string) $tenant->id, [
            'limit' => $usage['limit'],
            'used' => $usage['used'],
            'pending' => $usage['pending'],
            'note' => $note,
            'to' => $owners !== [] ? 'owners' : 'reos',
        ], $actor, $tenant->id);

        return true;
    }

    /**
     * An audit row in the invitation's tenant. The request may run in
     * another scope (a guest accepting, a team leader acting for the
     * brokerage), and row-level security only accepts rows for the tenant
     * in scope.
     *
     * @param  array<string, mixed>  $properties
     */
    private function record(string $action, string $subjectType, string $subjectId, array $properties, ?User $actor, int $tenantId): void
    {
        $this->tenancy->run($tenantId, fn () => $this->audit->record($action, $subjectType, $subjectId, $properties, $actor, $tenantId));
    }

    private function deliver(Invitation $invitation, bool $reminder = false): void
    {
        $invitation->loadMissing(['organisation', 'team', 'inviter']);
        $message = new InvitationSent($invitation, $this->link($invitation), $reminder);

        if ($invitation->email !== null) {
            Notification::route('mail', $invitation->email)->notify($message->locale($invitation->locale));
        } elseif ($invitation->phone !== null) {
            $this->texts->make()?->send($invitation->phone, $message->text());
        }

        if (! $reminder) {
            $this->tenancy->run($invitation->organisation_id, static fn () => $invitation->forceFill(['sent_at' => now()])->save());
        }
    }

    /** @param  array{email: string|null, phone: string|null, name: string|null}  $person */
    private function isMember(array $person, Organisation $target, Role $role): bool
    {
        $user = $person['email'] !== null
            ? User::query()->whereRaw('lower(email) = ?', [$person['email']])->first()
            : User::query()->where('phone', $person['phone'])->first();

        return $user !== null && $this->memberships->find($user, $role, $target->id) !== null;
    }

    /** @param  array{email: string|null, phone: string|null, name: string|null}  $person */
    private function pendingFor(Organisation $tenant, array $person): ?Invitation
    {
        return $this->tenancy->run($tenant->id, static fn (): ?Invitation => Invitation::query()
            ->where('organisation_id', $tenant->id)
            ->where('status', Invitation::PENDING)
            ->where('expires_at', '>', now())
            ->where($person['email'] !== null ? 'email' : 'phone', $person['email'] ?? $person['phone'])
            ->first());
    }

    private function shownContact(Invitation $invitation): string
    {
        return $invitation->email ?? PhoneNumber::mask((string) $invitation->phone);
    }

    /** XXXX-XXXX from an alphabet without look-alike characters. */
    private function code(): string
    {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return substr($code, 0, 4).'-'.substr($code, 4);
    }
}
