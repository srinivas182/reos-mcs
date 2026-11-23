<?php

declare(strict_types=1);

namespace App\Connections\Drivers\SkySlope;

/**
 * SkySlope's listings and sales, as REOS's deals (B9.2, ADR 0037), with
 * SkySlope's own field names (`saleGuid`, `listingGuid`, `status`,
 * `escrowClosingDate`, `actualClosingDate`, `contractAcceptedDate`,
 * `salePrice`, `listingPrice`, `expirationDate`, `email`, `property`).
 */
final class SkySlopeRecords
{
    /** A sale's status → stage (an open sale is under contract). */
    public const SALE_STAGES = ['active' => 'under_contract', 'pending' => 'under_contract', 'incomplete' => 'under_contract', 'closed' => 'closed', 'canceledApproved' => 'cancelled', 'canceledPending' => 'cancelled', 'expired' => 'cancelled', 'archived' => 'cancelled'];

    /** A listing's status → stage. */
    public const LISTING_STAGES = ['active' => 'active', 'incomplete' => 'active', 'pending' => 'under_contract', 'closed' => 'closed', 'canceledApproved' => 'cancelled', 'canceledPending' => 'cancelled', 'expired' => 'cancelled', 'archived' => 'cancelled'];

    /**
     * @param  array<array-key, mixed>  $file  A sale or a listing
     * @return array{external_id: string, external_url: null, owner_email: string|null, property: string, city: string|null, side: string, stage: string, price: int|null, commission: null, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, documents: list<array{name: string, status: string, url: null}>, dates: list<array{kind: string, label: string|null, due_on: string, external_id: string}>, updated_at: string|null}
     */
    public static function deal(array $file, string $kind): array
    {
        $sale = $kind === 'sale';
        $id = self::text($file[$sale ? 'saleGuid' : 'listingGuid'] ?? null) ?? '';
        $property = is_array($file['property'] ?? null) ? $file['property'] : [];
        $street = trim(implode(' ', array_filter([self::text($property['streetNumber'] ?? null), self::text($property['streetAddress'] ?? $property['streetName'] ?? null), self::text($property['unit'] ?? null)])));
        $status = self::text($file['status'] ?? null) ?? 'active';
        $price = $file[$sale ? 'salePrice' : 'listingPrice'] ?? null;
        $dates = [];

        foreach ($sale ? ['escrowClosingDate' => ['closing', null]] : ['expirationDate' => ['other', 'Listing expires']] as $field => [$dateKind, $label]) {
            $day = self::day($file[$field] ?? null);
            if ($day !== null) {
                $dates[] = ['kind' => $dateKind, 'label' => $label, 'due_on' => $day, 'external_id' => $id.':'.$field];
            }
        }

        $parties = [];
        foreach (['buyers' => 'Buyer', 'sellers' => 'Seller'] as $key => $role) {
            foreach (is_array($file[$key] ?? null) ? $file[$key] : [] as $person) {
                if (! is_array($person)) {
                    continue;
                }
                $name = trim(implode(' ', array_filter([self::text($person['firstName'] ?? null), self::text($person['lastName'] ?? null)])));
                if ($name !== '') {
                    $parties[] = ['name' => $name, 'role' => $role, 'email' => self::text($person['email'] ?? null), 'phone' => self::text($person['phoneNumber'] ?? null)];
                }
            }
        }

        return [
            'external_id' => ($sale ? 'sale:' : 'listing:').$id,
            'external_url' => null,
            'owner_email' => self::text($file['email'] ?? null),
            'property' => $street !== '' ? $street : (self::text($file['address'] ?? null) ?? ($sale ? 'Sale ' : 'Listing ').$id),
            'city' => self::text($property['city'] ?? null),
            'side' => $sale ? (self::text($file['listingGuid'] ?? null) !== null ? 'sell' : 'buy') : 'sell',
            'stage' => ($sale ? self::SALE_STAGES : self::LISTING_STAGES)[$status] ?? ($sale ? 'under_contract' : 'active'),
            'price' => is_numeric($price) ? (int) round((float) $price) : null,
            'commission' => null,
            'parties' => $parties,
            'documents' => self::documents($file['documents'] ?? null),
            'dates' => $dates,
            'updated_at' => self::text($file['modifiedOn'] ?? null),
        ];
    }

    /** @return list<array{name: string, status: string, url: null}> documents REOS filed there (B9.3) */
    private static function documents(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $doc) {
            $name = is_array($doc) ? self::text($doc['fileName'] ?? $doc['name'] ?? null) : null;
            if ($name !== null) {
                $out[] = ['name' => $name, 'status' => 'complete', 'url' => null];
            }
        }

        return $out;
    }

    private static function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 ? substr($value, 0, 10) : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
