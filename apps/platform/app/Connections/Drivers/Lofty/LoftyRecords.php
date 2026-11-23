<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Lofty;

use Carbon\CarbonImmutable;

/** Lofty's Open API JSON as REOS's normalised stream records (B8.1). Shared by the driver and its simulator. */
final class LoftyRecords
{
    /**
     * @param  array<array-key, mixed>  $l  a lead
     * @return array<string, mixed>
     */
    public static function person(array $l): array
    {
        $text = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $list = static fn (mixed $v): array => array_values(array_filter(array_map($text, is_array($v) ? $v : []), is_string(...)));

        return [
            'external_id' => self::id($l['leadId'] ?? null),
            'first_name' => $text($l['firstName'] ?? null),
            'last_name' => $text($l['lastName'] ?? null),
            'emails' => $list($l['emails'] ?? null),
            'phones' => $list($l['phones'] ?? null),
            'stage' => $text($l['stage'] ?? null),
            'tags' => array_values(array_filter(array_map(static fn (mixed $t): ?string => is_array($t) ? $text($t['tagName'] ?? null) : $text($t), is_array($l['tags'] ?? null) ? $l['tags'] : []), is_string(...))),
            'birthday' => $text($l['birthday'] ?? null),
            'updated_at' => self::time($l['lastUpdateTime'] ?? null),
            'details' => array_filter(['assigned_to' => $text($l['assignedUser'] ?? null), 'source' => $text($l['source'] ?? null)]),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $n  a note
     * @return array<string, mixed>
     */
    public static function note(array $n): array
    {
        return [
            'external_id' => self::id($n['noteId'] ?? null),
            'person_id' => self::id($n['leadId'] ?? null),
            'body' => is_string($n['content'] ?? null) ? $n['content'] : '',
            'updated_at' => self::time($n['lastUpdate'] ?? $n['createTime'] ?? null),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $t  a task
     * @return array<string, mixed>
     */
    public static function task(array $t): array
    {
        return [
            'external_id' => self::id($t['id'] ?? null),
            'person_id' => self::id($t['leadId'] ?? null),
            'title' => is_string($t['content'] ?? null) ? $t['content'] : '',
            'due_at' => self::time($t['deadline'] ?? null),
            'done' => ($t['finishFlag'] ?? false) === true,
            'updated_at' => self::time($t['lastUpdate'] ?? null),
        ];
    }

    /**
     * Lofty writes times as "yyyy-MM-dd HH:mm:ss" in UTC (webhooks in epoch
     * milliseconds); REOS keeps ISO 8601.
     */
    public static function time(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) >= 12)) {
            return CarbonImmutable::createFromTimestampMs((int) $value, 'UTC')->toIso8601ZuluString();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rescue(static fn (): string => CarbonImmutable::parse($value, 'UTC')->utc()->toIso8601ZuluString(), null, false);
    }

    private static function id(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
