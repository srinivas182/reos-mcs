<?php

declare(strict_types=1);

namespace App\Connections\Drivers\BoldTrail;

/** kvCORE / BoldTrail's V2 JSON as REOS's normalised stream records (B7.3). Shared by the driver and its simulator. */
final class BoldTrailRecords
{
    /** kvCORE's contact statuses. */
    public const STATUSES = [0 => 'New', 1 => 'Client', 2 => 'Closed', 3 => 'Sphere', 4 => 'Active', 5 => 'Contract', 6 => 'Archived', 7 => 'Prospect'];

    /**
     * @param  array<array-key, mixed>  $c
     * @return array<string, mixed>
     */
    public static function person(array $c): array
    {
        $text = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $status = $c['status'] ?? null;
        $campaign = is_array($c['smart_campaign'] ?? null) ? $c['smart_campaign'] : [];

        return [
            'external_id' => (string) (is_scalar($c['id'] ?? null) ? $c['id'] : ''),
            'first_name' => $text($c['first_name'] ?? null),
            'last_name' => $text($c['last_name'] ?? null),
            'emails' => array_values(array_filter([$text($c['email'] ?? null), $text($c['secondary_email'] ?? null)])),
            'phones' => array_values(array_filter([$text($c['cell_phone_1'] ?? null), $text($c['home_phone'] ?? null), $text($c['work_phone'] ?? null)])),
            'stage' => is_numeric($status) ? (self::STATUSES[(int) $status] ?? null) : $text($status),
            'tags' => array_values(array_filter(is_array($c['hashtags'] ?? null) ? $c['hashtags'] : [], is_string(...))),
            'birthday' => $text($c['birthday'] ?? null),
            'updated_at' => $text($c['date_modified'] ?? null),
            'details' => array_filter(['campaign' => $text($campaign['name'] ?? null), 'campaign_status' => $text($campaign['status'] ?? null)]),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $n
     * @return array<string, mixed>
     */
    public static function note(array $n, string $contactId): array
    {
        return [
            'external_id' => (string) (is_scalar($n['id'] ?? null) ? $n['id'] : ''),
            'person_id' => $contactId,
            'body' => is_string($n['details'] ?? null) ? $n['details'] : '',
            'updated_at' => is_string($n['updated_at'] ?? null) ? $n['updated_at'] : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $t
     * @return array<string, mixed>
     */
    public static function task(array $t): array
    {
        return [
            'external_id' => (string) (is_scalar($t['id'] ?? null) ? $t['id'] : ''),
            'person_id' => (string) (is_scalar($t['contact_id'] ?? null) ? $t['contact_id'] : ''),
            'title' => is_string($t['title'] ?? null) ? $t['title'] : '',
            'due_at' => is_string($t['due_date'] ?? null) ? $t['due_date'] : null,
            'done' => ($t['completed'] ?? 0) == 1,
            'updated_at' => is_string($t['updated_at'] ?? null) ? $t['updated_at'] : null,
        ];
    }

    /** The status number REOS sends for a stage, or null when kvCORE has none by that name. */
    public static function status(string $stage): ?int
    {
        $found = array_search(mb_strtolower($stage), array_map(mb_strtolower(...), self::STATUSES), true);

        return is_int($found) ? $found : null;
    }
}
