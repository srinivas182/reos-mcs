<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Crm\Contacts;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\User;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;

/**
 * James Miller's contacts (B7.1, Connections and Sync journey): people from
 * his CRM (demo data, as Follow Up Boss would send them) and his phone,
 * each person once. Maria Lopez and David Chen are in both and merged on
 * email and phone; two Mike Johnsons share only a name, so REOS suggests a
 * merge; Follow Up Boss's newer stage for Maria replaces the older one, and
 * Sync activity says so.
 */
final readonly class DemoContacts
{
    private const CRM = 'follow-up-boss';

    public function __construct(private Contacts $contacts, private Tenancy $tenancy) {}

    public function seed(): void
    {
        $james = User::query()->where('email', Personas::email('james.miller'))->first();

        if ($james === null || Contact::query()->withoutGlobalScopes()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $james->id)->exists()) {
            return;
        }

        $this->tenancy->asPlatform(function () use ($james): void {
            $days = static fn (int $n): string => CarbonImmutable::now()->subDays($n)->toIso8601String();

            $this->add($james->id, self::CRM, ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria.lopez@example.com'], 'phones' => ['(832) 555-0123'], 'stage' => 'Lead', 'tags' => ['Buyer', 'Katy'], 'updated_at' => $days(12)]);
            $this->add($james->id, self::CRM, ['external_id' => '1002', 'first_name' => 'David', 'last_name' => 'Chen', 'emails' => ['dchen@example.com'], 'stage' => 'Active client', 'tags' => ['Seller'], 'updated_at' => $days(9)]);
            $this->add($james->id, self::CRM, ['external_id' => '1003', 'first_name' => 'Priya', 'last_name' => 'Shah', 'emails' => ['priya.shah@example.com'], 'phones' => ['713-555-0188'], 'stage' => 'Nurture', 'updated_at' => $days(20)]);
            $this->add($james->id, self::CRM, ['external_id' => '1004', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'phones' => ['281-555-0101'], 'stage' => 'Lead', 'updated_at' => $days(5)]);

            $this->add($james->id, Contacts::PHONE, ['external_id' => 'ph-1', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'phones' => ['+1 832 555 0123'], 'birthday' => '1988-04-09', 'updated_at' => $days(3)]);
            $this->add($james->id, Contacts::PHONE, ['external_id' => 'ph-2', 'first_name' => 'David', 'last_name' => 'Chen', 'emails' => ['DChen@example.com'], 'phones' => ['346-555-0142'], 'updated_at' => $days(3)]);
            $this->add($james->id, Contacts::PHONE, ['external_id' => 'ph-3', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'phones' => ['979-555-0177'], 'updated_at' => $days(3)]);
            $this->add($james->id, Contacts::PHONE, ['external_id' => 'ph-4', 'first_name' => 'Angela', 'last_name' => 'Brooks', 'emails' => ['angela.brooks@example.com'], 'updated_at' => $days(3)]);

            // Maria toured two homes: Follow Up Boss moves her on.
            $this->add($james->id, self::CRM, ['external_id' => '1001', 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => ['maria.lopez@example.com'], 'phones' => ['(832) 555-0123'], 'stage' => 'Active client', 'tags' => ['Buyer', 'Katy'], 'updated_at' => $days(1)]);
        });
    }

    /** @param  array{external_id: string, first_name?: string, last_name?: string, emails?: list<string>, phones?: list<string>, stage?: string, birthday?: string, tags?: list<string>, updated_at?: string}  $record */
    private function add(int $owner, string $source, array $record): void
    {
        $this->contacts->receive(Connection::OWNER_USER, $owner, $source, null, $record);
    }
}
