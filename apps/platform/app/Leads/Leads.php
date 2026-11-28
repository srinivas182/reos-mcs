<?php

declare(strict_types=1);

namespace App\Leads;

use App\Crm\CrmWriteBack;
use App\Models\Contact;
use App\Models\User;
use App\Notifications\NewLeadReceived;
use Carbon\CarbonImmutable;

/**
 * A new person for an agent's CRM from REOS's own capture (B14.3a, ADR
 * 0066): an open house sign-in or a message from the agent's page.
 *
 * The person is added like any contact the agent adds (B8.3b: matched by
 * email or phone, then written to the agent's default CRM), with a note of
 * where they came from, a follow-up to-do for tomorrow, a suggested first
 * message, and a notification. Someone already working with another agent
 * gets no sales follow-up: only what they asked for (TREC: don't interfere
 * with another broker's client).
 */
final readonly class Leads
{
    public function __construct(private CrmWriteBack $crm) {}

    /**
     * @param  array{name: string, email: string|null, phone: string|null}  $person
     * @param  list<string>  $tags
     * @param  'open_house'|'page'|'referral'  $kind
     */
    public function add(User $agent, array $person, string $note, string $draft, bool $hasAgent, array $tags, string $kind): Contact
    {
        [$first, $last] = self::split($person['name']);
        [$contact] = $this->crm->addPerson($agent, [
            'first_name' => $first, 'last_name' => $last,
            'emails' => $person['email'] === null ? [] : [$person['email']],
            'phones' => $person['phone'] === null ? [] : [$person['phone']],
            'stage' => null, 'tags' => $tags,
        ]);
        $lang = $agent->locale->value;

        $this->crm->note($contact, $note, $agent);
        $this->crm->note($contact, self::tr($hasAgent ? 'leads.followUp.hasAgent' : 'leads.followUp.draft', ['draft' => $draft], $lang), $agent);
        $this->crm->task($contact, self::tr($hasAgent ? 'leads.followUp.taskHasAgent' : 'leads.followUp.task', ['name' => $first ?? $person['name']], $lang), CarbonImmutable::now('America/Chicago')->addDay(), $agent);
        // An accepted referral needs no alert: the agent just took it.
        if ($kind !== 'referral') {
            $agent->notify(new NewLeadReceived($kind, $person['name'], $contact->uuid));
        }

        return $contact;
    }

    /** @return array{0: string|null, 1: string|null} */
    private static function split(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return [($parts[0] ?? '') === '' ? null : $parts[0], $parts[1] ?? null];
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $value = __($key, $words, $lang);

        return is_string($value) ? $value : $key;
    }
}
