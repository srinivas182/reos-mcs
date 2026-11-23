<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Dotloop;

/**
 * dotloop's loops, as REOS's deals (B9.2, ADR 0037). A loop with its
 * details, participants, documents and task lists becomes one deal record
 * for `Deals::receive`. Field and status names are dotloop Public API v2's.
 */
final class DotloopRecords
{
    /** `transactionType` → side. */
    public const SIDES = ['PURCHASE_OFFER' => 'buy', 'LISTING_FOR_SALE' => 'sell', 'LISTING_FOR_LEASE' => 'lease', 'LEASE_OFFER' => 'lease'];

    /** `status` → stage. */
    public const STAGES = ['UNDER_CONTRACT' => 'under_contract', 'SOLD' => 'closed', 'LEASED' => 'closed', 'ARCHIVED' => 'cancelled'];

    /** Loop detail dates (section, field) → key date kind and label. */
    public const DATES = [
        ['Contract Dates', 'Closing Date', 'closing', null],
        ['Offer Dates', 'Inspection Date', 'inspection', null],
        ['Offer Dates', 'Offer Expiration Date', 'other', 'Offer expires'],
        ['Offer Dates', 'Occupancy Date', 'other', 'Occupancy'],
        ['Listing Information', 'Expiration Date', 'other', 'Listing expires'],
    ];

    /** Participant roles → how REOS names them. */
    public const ROLES = ['BUYER' => 'Buyer', 'SELLER' => 'Seller', 'LISTING_AGENT' => 'Listing agent', 'BUYING_AGENT' => 'Buyer agent', 'LENDER' => 'Lender', 'ESCROW_TITLE_REP' => 'Title company', 'INSPECTOR' => 'Inspector', 'APPRAISER' => 'Appraiser', 'TENANT' => 'Tenant', 'LANDLORD' => 'Landlord', 'TRANSACTION_COORDINATOR' => 'Transaction coordinator'];

    /**
     * @param  array<array-key, mixed>  $loop  With `details` (sections of fields)
     * @param  list<array<array-key, mixed>>  $participants
     * @param  list<array<array-key, mixed>>  $folders  With their `documents`
     * @param  list<array<array-key, mixed>>  $tasks  Task list items
     * @return array{external_id: string, external_url: string|null, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, documents: list<array{name: string, status: string, url: string|null}>, dates: list<array{kind: string, label: string|null, due_on: string, external_id: string, done?: bool}>, updated_at: string|null}
     */
    public static function deal(array $loop, array $participants, array $folders, array $tasks): array
    {
        $id = is_scalar($loop['id'] ?? null) ? (string) $loop['id'] : '';
        $details = is_array($loop['details'] ?? null) ? $loop['details'] : [];
        $field = static fn (string $section, string $name): ?string => is_array($details[$section] ?? null) && is_scalar($details[$section][$name] ?? null) ? trim((string) $details[$section][$name]) : null;
        $street = trim(implode(' ', array_filter([$field('Property Address', 'Street Number'), $field('Property Address', 'Street Name'), $field('Property Address', 'Unit Number')])));
        $name = is_string($loop['name'] ?? null) ? $loop['name'] : 'Loop '.$id;
        $dates = [];

        foreach (self::DATES as [$section, $key, $kind, $label]) {
            $day = self::day($field($section, $key));
            if ($day !== null) {
                $dates[] = ['kind' => $kind, 'label' => $label, 'due_on' => $day, 'external_id' => $id.':'.$section.':'.$key];
            }
        }

        // Texas loops often carry the option period in their details; take any field named for it.
        foreach ($details as $section => $fields) {
            foreach (is_array($fields) ? $fields : [] as $key => $value) {
                $day = is_string($key) && stripos($key, 'option') !== false ? self::day(is_scalar($value) ? (string) $value : null) : null;
                if ($day !== null) {
                    $dates[] = ['kind' => 'option_period', 'label' => null, 'due_on' => $day, 'external_id' => $id.':'.$section.':'.$key];
                }
            }
        }

        // Task list items with a due date are key dates too, done when dotloop says so.
        foreach ($tasks as $task) {
            $day = self::day(is_string($task['dueDate'] ?? null) ? $task['dueDate'] : null);
            if ($day !== null && is_scalar($task['id'] ?? null)) {
                $dates[] = ['kind' => 'other', 'label' => is_string($task['name'] ?? null) ? mb_substr($task['name'], 0, 120) : 'Task', 'due_on' => $day, 'external_id' => $id.':task:'.$task['id'], 'done' => ($task['completed'] ?? false) === true];
            }
        }

        $documents = [];
        foreach ($folders as $folder) {
            foreach (is_array($folder['documents'] ?? null) ? $folder['documents'] : [] as $doc) {
                if (is_array($doc) && is_string($doc['name'] ?? null)) {
                    $documents[] = ['name' => $doc['name'], 'status' => 'complete', 'url' => null];
                }
            }
        }

        $price = self::number($field('Financials', 'Purchase/Sale Price') ?? $field('Listing Information', 'Current Price'));
        $commission = self::number($field('Financials', 'Sale Commission Total'));

        return [
            'external_id' => $id,
            'external_url' => is_string($loop['loopUrl'] ?? null) ? $loop['loopUrl'] : null,
            'property' => $street !== '' ? $street : $name,
            'city' => $field('Property Address', 'City'),
            'side' => self::SIDES[is_string($loop['transactionType'] ?? null) ? $loop['transactionType'] : ''] ?? 'buy',
            'stage' => self::STAGES[is_string($loop['status'] ?? null) ? $loop['status'] : ''] ?? 'active',
            'price' => $price === null ? null : (int) round($price),
            'commission' => $commission === null ? null : number_format($commission, 2, '.', ''),
            'parties' => array_values(array_filter(array_map(static function (array $p): ?array {
                $name = is_string($p['fullName'] ?? null) ? trim($p['fullName']) : '';
                $role = is_string($p['role'] ?? null) ? $p['role'] : '';

                return $name === '' ? null : ['name' => $name, 'role' => self::ROLES[$role] ?? ucwords(strtolower(str_replace('_', ' ', $role))) ?: 'Contact', 'email' => is_string($p['email'] ?? null) ? $p['email'] : null, 'phone' => is_string($p['Phone'] ?? $p['Cell Phone'] ?? null) ? ($p['Phone'] ?? $p['Cell Phone']) : null];
            }, $participants))),
            'documents' => $documents,
            'dates' => $dates,
            'updated_at' => is_string($loop['updated'] ?? null) ? $loop['updated'] : null,
        ];
    }

    /** dotloop dates are "MM/DD/YYYY" (or ISO). */
    public static function day(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) === 1) {
            return checkdate((int) $m[1], (int) $m[2], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]) : null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 ? substr($value, 0, 10) : null;
    }

    private static function number(?string $value): ?float
    {
        $clean = $value === null ? '' : preg_replace('/[^0-9.]/', '', $value);

        return is_string($clean) && $clean !== '' && is_numeric($clean) ? (float) $clean : null;
    }
}
