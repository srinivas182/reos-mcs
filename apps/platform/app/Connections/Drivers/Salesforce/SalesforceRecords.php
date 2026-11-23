<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Salesforce;

/**
 * Salesforce's REST JSON as REOS's normalised stream records (B8.2): Contacts
 * and Leads as people (with their owner's email), ContentNotes as notes, and
 * Tasks on a person as tasks. Shared by the driver and its simulator.
 */
final class SalesforceRecords
{
    /** Contacts start 003, Leads 00Q. */
    public const CONTACT = '003';

    public const LEAD = '00Q';

    /**
     * Where each brokerage keeps a person's stage (screen 40), saved per connection.
     *
     * @param  array<array-key, mixed>  $meta
     * @return array{contact_stage: string|null, lead_stage: string, leads: bool}
     */
    public static function mapping(array $meta): array
    {
        $map = is_array($meta['field_map'] ?? null) ? $meta['field_map'] : [];
        $field = static fn (mixed $v): ?string => is_string($v) && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $v) === 1 ? $v : null;

        return [
            'contact_stage' => $field($map['contact_stage'] ?? null),
            'lead_stage' => $field($map['lead_stage'] ?? null) ?? 'Status',
            'leads' => ($map['leads'] ?? true) !== false,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $r  a Contact or Lead
     * @return array<string, mixed>
     */
    public static function person(array $r, ?string $stageField): array
    {
        $text = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $owner = is_array($r['Owner'] ?? null) ? $r['Owner'] : [];
        $id = is_string($r['Id'] ?? null) ? $r['Id'] : '';

        return [
            'external_id' => $id,
            'first_name' => $text($r['FirstName'] ?? null),
            'last_name' => $text($r['LastName'] ?? null),
            'emails' => ($email = $text($r['Email'] ?? null)) === null ? [] : [$email],
            'phones' => array_values(array_unique(array_filter([$text($r['MobilePhone'] ?? null), $text($r['Phone'] ?? null)]))),
            'stage' => $stageField === null ? null : $text($r[$stageField] ?? null),
            'tags' => [],
            'birthday' => $text($r['Birthdate'] ?? null),
            'updated_at' => $text($r['SystemModstamp'] ?? null),
            'owner_email' => $text($owner['Email'] ?? null),
            'details' => [],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $n  a ContentNote with the person it's linked to
     * @return array<string, mixed>
     */
    public static function note(array $n, string $personId, ?string $content = null): array
    {
        $title = is_string($n['Title'] ?? null) ? trim($n['Title']) : '';
        $body = $content ?? (is_string($n['TextPreview'] ?? null) ? $n['TextPreview'] : '');

        return [
            'external_id' => is_string($n['Id'] ?? null) ? $n['Id'] : '',
            'person_id' => $personId,
            'body' => trim(($title !== '' && ! str_starts_with($body, $title) ? $title."\n" : '').$body),
            'updated_at' => is_string($n['LastModifiedDate'] ?? null) ? $n['LastModifiedDate'] : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $t  a Task
     * @return array<string, mixed>
     */
    public static function task(array $t): array
    {
        $owner = is_array($t['Owner'] ?? null) ? $t['Owner'] : [];

        return [
            'external_id' => is_string($t['Id'] ?? null) ? $t['Id'] : '',
            'person_id' => is_string($t['WhoId'] ?? null) ? $t['WhoId'] : '',
            'title' => is_string($t['Subject'] ?? null) ? $t['Subject'] : '',
            // ActivityDate is a date (no time): the to-do is due that day.
            'due_at' => is_string($t['ActivityDate'] ?? null) ? $t['ActivityDate'] : null,
            'done' => ($t['IsClosed'] ?? false) === true,
            'updated_at' => is_string($t['SystemModstamp'] ?? null) ? $t['SystemModstamp'] : null,
            'owner_email' => is_string($owner['Email'] ?? null) ? $owner['Email'] : null,
        ];
    }
}
