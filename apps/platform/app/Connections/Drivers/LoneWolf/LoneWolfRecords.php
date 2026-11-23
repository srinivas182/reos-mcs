<?php

declare(strict_types=1);

namespace App\Connections\Drivers\LoneWolf;

/**
 * Lone Wolf Transact's records, as REOS's deals (B9.1b, ADR 0037). One
 * transaction with its contacts, documents and accepted offer becomes one
 * deal record for `Deals::receive`.
 *
 * Transact's published API names the fields used here (address, `asking`,
 * `closingDate`, `listingExpiration`; offers' `purchasePrice`,
 * `acceptanceDate`, `closingDate`, `possessionDate`, `finalWalkthroughDate`,
 * `expirationDate`; contacts' `contactType`, `firstname`, `lastname`,
 * `email`, `phone`). Its numeric codes for `type`, `phase` and
 * `contactType` are marked "needs documentation" there; the maps below
 * follow the partner documentation's order and are confirmed in the
 * partner sandbox before going live (register: lone-wolf).
 */
final class LoneWolfRecords
{
    /** `type`: what kind of transaction it is. */
    public const TYPE_SIDE = [0 => 'sell', 1 => 'buy', 2 => 'lease', 3 => 'lease', 4 => 'both'];

    /** `phase`: where it is. */
    public const PHASE_STAGE = [0 => 'active', 1 => 'active', 2 => 'under_contract', 3 => 'closed', 4 => 'cancelled'];

    /** `contactType`: the first codes of Transact's 0–21 list. */
    public const CONTACT_ROLES = [0 => 'Buyer', 1 => 'Seller', 2 => 'Buyer agent', 3 => 'Listing agent', 4 => 'Lender', 5 => 'Title company', 6 => 'Attorney', 7 => 'Inspector', 8 => 'Appraiser', 9 => 'Tenant', 10 => 'Landlord'];

    /** Transact's web address for a transaction (opened from REOS). */
    public const OPEN = 'https://transact.lwolf.com/#/transactions/';

    /**
     * @param  array<array-key, mixed>  $tx  The transaction
     * @param  list<array<array-key, mixed>>  $contacts
     * @param  list<array<array-key, mixed>>  $documents
     * @param  list<array<array-key, mixed>>  $offers
     * @return array{external_id: string, external_url: string, owner_email: string|null, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, documents: list<array{name: string, status: string, url: string|null}>, dates: list<array{kind: string, label: string|null, due_on: string, external_id: string}>, updated_at: string|null}
     */
    public static function deal(array $tx, array $contacts, array $documents, array $offers, ?string $ownerEmail): array
    {
        $id = self::text($tx['id'] ?? null) ?? '';
        $offer = self::accepted($offers);
        $address = trim(implode(' ', array_filter([self::text($tx['address1'] ?? null), self::text($tx['address2'] ?? null)])));
        $type = is_numeric($tx['type'] ?? null) ? (int) $tx['type'] : 1;
        $phase = is_numeric($tx['phase'] ?? null) ? (int) $tx['phase'] : 0;
        $price = $offer['purchasePrice'] ?? $tx['asking'] ?? null;
        $commission = $offer['commission'] ?? $tx['commission'] ?? null;
        $dates = [];

        foreach ([
            'closing' => [$offer['closingDate'] ?? $tx['closingDate'] ?? null, null],
            'other:walkthrough' => [$offer['finalWalkthroughDate'] ?? null, 'Final walkthrough'],
            'other:possession' => [$offer['possessionDate'] ?? null, 'Possession'],
            'other:offer' => [$phase < 2 ? ($offer['expirationDate'] ?? null) : null, 'Offer expires'],
            'other:listing' => [$phase < 2 ? ($tx['listingExpiration'] ?? null) : null, 'Listing expires'],
        ] as $key => [$value, $label]) {
            $day = self::day($value);
            if ($day !== null) {
                $dates[] = ['kind' => str_starts_with($key, 'other') ? 'other' : $key, 'label' => $label, 'due_on' => $day, 'external_id' => $id.':'.$key];
            }
        }

        // Texas contract dates, when the transaction carries them (TREC forms filled in Transact).
        foreach (is_array($tx['keyDates'] ?? null) ? $tx['keyDates'] : [] as $n => $date) {
            if (! is_array($date) || self::day($date['date'] ?? null) === null) {
                continue;
            }
            $name = mb_strtolower(self::text($date['name'] ?? null) ?? '');
            $kind = match (true) {
                str_contains($name, 'option') => 'option_period',
                str_contains($name, 'financ') => 'financing',
                str_contains($name, 'apprais') => 'appraisal',
                str_contains($name, 'inspect') => 'inspection',
                str_contains($name, 'survey') => 'survey',
                str_contains($name, 'title') => 'title_commitment',
                default => 'other',
            };
            $dates[] = ['kind' => $kind, 'label' => $kind === 'other' ? (self::text($date['name'] ?? null) ?? 'Key date') : null, 'due_on' => (string) self::day($date['date'] ?? null), 'external_id' => $id.':key:'.(self::text($date['id'] ?? null) ?? (string) $n)];
        }

        return [
            'external_id' => $id,
            'external_url' => self::OPEN.rawurlencode($id),
            'owner_email' => $ownerEmail,
            'property' => $address !== '' ? $address : 'Transaction '.$id,
            'city' => self::text($tx['locality'] ?? null),
            'side' => self::TYPE_SIDE[$type] ?? 'buy',
            'stage' => self::PHASE_STAGE[$phase] ?? 'active',
            'price' => is_numeric($price) ? (int) round((float) $price) : null,
            'commission' => is_numeric($commission) ? number_format((float) $commission, 2, '.', '') : null,
            'parties' => array_values(array_filter(array_map(self::party(...), $contacts))),
            'documents' => array_map(static fn (array $d): array => [
                'name' => self::text($d['name'] ?? null) ?? 'Document',
                'status' => ($d['status'] ?? null) === 'missing' ? 'missing' : (($d['status'] ?? null) === 'pending' ? 'pending' : 'complete'),
                'url' => null,
            ], $documents),
            'dates' => $dates,
            'updated_at' => self::text($tx['modifiedDate'] ?? $tx['lastModified'] ?? null),
        ];
    }

    /**
     * The accepted offer (or the newest one).
     *
     * @param  list<array<array-key, mixed>>  $offers
     * @return array<array-key, mixed>
     */
    private static function accepted(array $offers): array
    {
        foreach ($offers as $offer) {
            if (self::day($offer['acceptanceDate'] ?? null) !== null) {
                return $offer;
            }
        }

        return $offers[array_key_last($offers) ?? 0] ?? [];
    }

    /**
     * @param  array<array-key, mixed>  $c
     * @return array{name: string, role: string, email: string|null, phone: string|null}|null
     */
    private static function party(array $c): ?array
    {
        $name = trim(implode(' ', array_filter([self::text($c['firstname'] ?? null), self::text($c['lastname'] ?? null)]))) ?: self::text($c['companyName'] ?? null);

        if ($name === null || $name === '') {
            return null;
        }

        $type = is_numeric($c['contactType'] ?? null) ? (int) $c['contactType'] : -1;

        return ['name' => $name, 'role' => self::CONTACT_ROLES[$type] ?? 'Contact', 'email' => self::text($c['email'] ?? null), 'phone' => self::text($c['cellPhone'] ?? $c['phone'] ?? null)];
    }

    private static function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 ? substr($value, 0, 10) : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : (is_int($value) ? (string) $value : null);
    }
}
