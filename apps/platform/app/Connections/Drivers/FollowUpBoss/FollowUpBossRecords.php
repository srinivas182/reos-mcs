<?php

declare(strict_types=1);

namespace App\Connections\Drivers\FollowUpBoss;

/** Follow Up Boss's JSON as REOS's normalised stream records (B7.2). Shared by the driver and its simulator. */
final class FollowUpBossRecords
{
    /**
     * @param  array<array-key, mixed>  $p
     * @return array<string, mixed>
     */
    public static function person(array $p): array
    {
        $values = static fn (mixed $list): array => array_values(array_filter(array_map(static fn (mixed $v): ?string => is_array($v) && is_string($v['value'] ?? null) ? $v['value'] : null, is_array($list) ? $list : [])));

        return [
            'external_id' => (string) (is_scalar($p['id'] ?? null) ? $p['id'] : ''),
            'first_name' => is_string($p['firstName'] ?? null) ? $p['firstName'] : null,
            'last_name' => is_string($p['lastName'] ?? null) ? $p['lastName'] : null,
            'emails' => $values($p['emails'] ?? null),
            'phones' => $values($p['phones'] ?? null),
            'stage' => is_string($p['stage'] ?? null) ? $p['stage'] : null,
            'tags' => array_values(array_filter(is_array($p['tags'] ?? null) ? $p['tags'] : [], is_string(...))),
            'birthday' => is_string($p['birthday'] ?? null) ? $p['birthday'] : null,
            'updated_at' => is_string($p['updated'] ?? null) ? $p['updated'] : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $n
     * @return array<string, mixed>
     */
    public static function note(array $n): array
    {
        $subject = is_string($n['subject'] ?? null) && $n['subject'] !== '' ? $n['subject']."\n" : '';

        return [
            'external_id' => (string) (is_scalar($n['id'] ?? null) ? $n['id'] : ''),
            'person_id' => (string) (is_scalar($n['personId'] ?? null) ? $n['personId'] : ''),
            'body' => $subject.(is_string($n['body'] ?? null) ? $n['body'] : ''),
            'updated_at' => is_string($n['updated'] ?? null) ? $n['updated'] : null,
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
            'person_id' => (string) (is_scalar($t['personId'] ?? null) ? $t['personId'] : ''),
            'title' => is_string($t['name'] ?? null) ? $t['name'] : '',
            'due_at' => is_string($t['dueDateTime'] ?? null) ? $t['dueDateTime'] : (is_string($t['dueDate'] ?? null) ? $t['dueDate'] : null),
            'done' => ($t['isCompleted'] ?? false) === true || ($t['isCompleted'] ?? 0) === 1,
            'updated_at' => is_string($t['updated'] ?? null) ? $t['updated'] : null,
        ];
    }
}
