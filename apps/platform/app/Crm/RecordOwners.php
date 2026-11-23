<?php

declare(strict_types=1);

namespace App\Crm;

use App\Connections\ConnectorRegistry;
use App\Models\Connection;
use App\Models\ContactLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Whose a CRM record is (B8.2, ADR 0033). A person's own CRM: theirs. A
 * brokerage's CRM where each record has an owner (Salesforce): the agent of
 * that brokerage whose email is the record owner's, so each agent sees only
 * the records they own and the broker sees none of them, only counts and
 * sync health. Records owned by nobody at the brokerage are left out and
 * counted.
 */
final class RecordOwners
{
    /** @var array<string, User|null> */
    private array $byEmail = [];

    public static function perRecord(Connection $connection): bool
    {
        return $connection->owner_type === Connection::OWNER_ORGANISATION && ConnectorRegistry::ownsPerRecord($connection->integration_id);
    }

    /**
     * The owner of a person or task record: [owner type, owner id, the person who works it].
     *
     * @param  array<array-key, mixed>  $record
     * @return array{0: string, 1: int, 2: User|null}|null null when no one at the brokerage owns it
     */
    public function of(Connection $connection, array $record): ?array
    {
        if (! self::perRecord($connection)) {
            return [$connection->owner_type, $connection->owner_id, $connection->contact()];
        }

        $email = is_string($record['owner_email'] ?? null) ? mb_strtolower(trim($record['owner_email'])) : '';
        $agent = $email === '' ? null : ($this->byEmail[$connection->owner_id.':'.$email] ??= User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->whereIn('id', DB::table('model_has_roles')->where('organisation_id', $connection->owner_id)->where('model_type', (new User)->getMorphClass())->select('model_id'))
            ->first());

        return $agent === null ? null : [Connection::OWNER_USER, $agent->id, $agent];
    }

    /**
     * The contact a note or task is about: on a brokerage's CRM, the person
     * must already be known (their owner decides who sees it).
     */
    public static function linkFor(Connection $connection, string $personId): ?ContactLink
    {
        return ContactLink::query()->where('connection_id', $connection->id)->where('source', $connection->integration_id)->where('external_id', $personId)->first();
    }
}
