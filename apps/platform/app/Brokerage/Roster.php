<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Models\Invitation;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\Invitations;
use App\Support\PhoneNumber;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Bring in your agents (A15.1b, broker journey screen 3, ADR 0070): every
 * agent TREC lists under the brokerage's licence today, and one step to
 * invite them all.
 *
 * - **Who:** active sales agents and brokers whose sponsoring broker on
 *   TREC's nightly file is the brokerage's TREC number (A15.1a).
 * - **Each row:** the public TREC facts (name, licence, expiry), and
 *   whether they're already in the brokerage, invited, on REOS (an account
 *   with that Texas licence) or still need an email or phone. Nothing from
 *   an agent's account is shown beyond "on REOS".
 * - **Contacts:** TREC publishes no email or phone, so the broker adds them
 *   for agents not on REOS (one line each: licence number, then an email
 *   or phone). Lines for agents TREC doesn't list under the brokerage are
 *   refused.
 * - **Invite all:** every sponsored agent not yet in or invited, at their
 *   account's email or the contact added, through the usual invitations
 *   (14 days, one reminder, seats checked).
 *
 * @phpstan-type RosterRow array{licence: string, name: string, type: string, expires_on: string|null, state: 'member'|'invited'|'on_reos'|'contact'|'needs_contact', contact: string|null}
 * @phpstan-type RosterView array{trec_number: string|null, counts: array{sponsored: int, member: int, invited: int, on_reos: int, contact: int, needs_contact: int}, agents: list<RosterRow>}
 */
final readonly class Roster
{
    public const MAX = 2000;

    public function __construct(private Invitations $invitations, private Tenancy $tenancy, private AuditLogger $audit) {}

    /** @return RosterView */
    public function for(Organisation $brokerage): array
    {
        $rows = [];
        $counts = ['sponsored' => 0, 'member' => 0, 'invited' => 0, 'on_reos' => 0, 'contact' => 0, 'needs_contact' => 0];

        foreach ($this->people($brokerage) as $person) {
            $rows[] = $row = [
                'licence' => $person['record']->number, 'name' => self::display($person['record']->name), 'type' => $person['record']->licence_type,
                'expires_on' => $person['record']->expires_on?->toDateString(), 'state' => $person['state'],
                'contact' => $person['shown'] === null ? null : ($person['shown']['email'] ?? PhoneNumber::mask((string) $person['shown']['phone'])),
            ];
            $counts['sponsored']++;
            $counts[$row['state']]++;
        }

        return ['trec_number' => $brokerage->trec_number, 'counts' => $counts, 'agents' => $rows];
    }

    /**
     * Adds emails or phones for sponsored agents, one per line ("0722222,
     * maria@example.com").
     *
     * @return array{saved: int, refused: list<string>}
     */
    public function contacts(Organisation $brokerage, User $actor, string $text): array
    {
        $sponsored = collect($this->sponsored($brokerage))->keyBy(static fn (RegulatorRecord $r): string => $r->number_digits);
        $saved = 0;
        $refused = [];

        foreach (preg_split('/\R/', trim($text)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            preg_match('/\b(\d{5,9})\b/', $line, $number);
            $parsed = $this->invitations->parse((string) preg_replace('/\b\d{5,9}\b/', '', $line, 1))['people'][0] ?? null;
            $record = isset($number[1]) ? $sponsored->get(ltrim($number[1], '0')) ?? $sponsored->get($number[1]) : null;

            if ($record === null || $parsed === null) {
                $refused[] = mb_substr($line, 0, 120);

                continue;
            }

            $this->tenancy->run($brokerage->id, static fn () => DB::table('brokerage_roster_contacts')->updateOrInsert(
                ['organisation_id' => $brokerage->id, 'licence_number' => $record->number],
                ['email' => $parsed['email'], 'phone' => $parsed['email'] === null ? $parsed['phone'] : null, 'added_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()],
            ));
            $saved++;
        }

        $this->audit->record('roster.contacts_added', 'tenant', (string) $brokerage->id, ['saved' => $saved, 'refused' => count($refused)], $actor, $brokerage->id);

        return ['saved' => $saved, 'refused' => $refused];
    }

    /**
     * Invites every sponsored agent who isn't in the brokerage or invited
     * yet and can be reached.
     *
     * @return array{sent: int, skipped: int, needs_contact: int}
     */
    public function inviteAll(Organisation $brokerage, User $actor, string $locale): array
    {
        $people = [];
        $needs = 0;

        foreach ($this->people($brokerage) as $person) {
            if ($person['state'] === 'needs_contact') {
                $needs++;
            } elseif (in_array($person['state'], ['on_reos', 'contact'], true)) {
                $people[] = ['email' => $person['reach']['email'] ?? null, 'phone' => $person['reach']['phone'] ?? null, 'name' => self::display($person['record']->name)];
            }
        }

        $sent = 0;
        $skipped = 0;

        foreach (array_chunk($people, Invitations::MAX_BATCH) as $batch) {
            $result = $this->invitations->invite($brokerage, $actor, $batch, Role::Agent, null, $locale);
            $sent += $result['sent'];
            $skipped += count($result['skipped']);
        }

        $this->audit->record('roster.invited', 'tenant', (string) $brokerage->id, ['sent' => $sent, 'skipped' => $skipped, 'needs_contact' => $needs], $actor, $brokerage->id);

        return ['sent' => $sent, 'skipped' => $skipped, 'needs_contact' => $needs];
    }

    /** @return list<RegulatorRecord> */
    private function sponsored(Organisation $brokerage): array
    {
        if ($brokerage->trec_number === null) {
            return [];
        }

        return array_values(RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')->whereIn('licence_type', ['SALE', 'BRK'])
            ->where('active', true)->whereIn('sponsor_number', BrokerVerifications::spellings($brokerage->trec_number))
            ->orderBy('name')->limit(self::MAX)->get()->all());
    }

    /**
     * Each sponsored agent with their state, how to reach them, and what may
     * be shown: an agent's own account details are never shown to the broker.
     *
     * @return list<array{record: RegulatorRecord, state: 'member'|'invited'|'on_reos'|'contact'|'needs_contact', reach: array{email: string|null, phone: string|null}|null, shown: array{email: string|null, phone: string|null}|null}>
     */
    private function people(Organisation $brokerage): array
    {
        $records = $this->sponsored($brokerage);

        if ($records === []) {
            return [];
        }

        $numbers = array_map(static fn (RegulatorRecord $r): string => $r->number, $records);
        $spellings = array_merge(...array_map(BrokerVerifications::spellings(...), $numbers));
        $licences = Licence::query()->where('state_code', 'TX')->whereIn('number', $spellings)->with('user')->get()
            ->keyBy(static fn (Licence $l): string => ltrim((string) preg_replace('/\D/', '', $l->number), '0'));
        $places = [$brokerage->id, ...$brokerage->children()->pluck('id')->all()];
        $members = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Role::Agent->value)->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.organisation_id', $places)->pluck('model_has_roles.model_id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();
        [$contacts, $pending] = $this->tenancy->run($brokerage->id, static fn (): array => [
            DB::table('brokerage_roster_contacts')->where('organisation_id', $brokerage->id)->get()->keyBy(static fn (object $c): string => ltrim(is_string($c->licence_number ?? null) ? $c->licence_number : '', '0')),
            Invitation::query()->where('organisation_id', $brokerage->id)->where('status', Invitation::PENDING)->where('expires_at', '>', now())->get(['email', 'phone']),
        ]);
        $invited = static fn (?string $email, ?string $phone): bool => $pending->contains(static fn (Invitation $i): bool => ($email !== null && mb_strtolower((string) $i->email) === mb_strtolower($email)) || ($phone !== null && $i->phone === $phone));
        $out = [];

        foreach ($records as $record) {
            $key = ltrim($record->number_digits, '0');
            $user = $licences->get($key)?->user;
            $saved = $contacts->get($key);
            $contact = $user !== null
                ? ['email' => $user->email === null ? null : mb_strtolower($user->email), 'phone' => $user->email === null ? $user->phone : null]
                : ($saved === null ? null : ['email' => is_string($saved->email ?? null) ? $saved->email : null, 'phone' => is_string($saved->phone ?? null) ? $saved->phone : null]);
            $state = match (true) {
                $user !== null && in_array($user->id, $members, true) => 'member',
                $contact !== null && $invited($contact['email'], $contact['phone']) => 'invited',
                $user !== null => 'on_reos',
                $contact !== null => 'contact',
                default => 'needs_contact',
            };
            $out[] = ['record' => $record, 'state' => $state, 'reach' => $contact, 'shown' => $user === null ? $contact : null];
        }

        return $out;
    }

    /** "MILLER, JAMES R" or "James Miller" → "James Miller" as TREC's portal shows it. */
    private static function display(string $name): string
    {
        if (str_contains($name, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $name, 2));
            $name = $rest.' '.$last;
        }

        return mb_convert_case(mb_strtolower(trim($name)), MB_CASE_TITLE);
    }
}
