<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Models\AccountingConnection;

/**
 * The QuickBooks simulator (B17.3): connects at once and posts entries
 * with a predictable id, so the flow runs before the Intuit app is approved.
 */
final class SimulatedQuickBooks implements QuickBooks
{
    public const REALM = '9130350000000001';

    /** The simulator's chart of accounts: what a brokerage usually has. */
    public const ACCOUNTS = [
        ['id' => '80', 'name' => 'Commission Income', 'type' => 'Income'],
        ['id' => '81', 'name' => 'Company Dollar', 'type' => 'Income'],
        ['id' => '33', 'name' => 'Team Splits Payable', 'type' => 'Other Current Liability'],
        ['id' => '34', 'name' => 'Agent Commissions Payable', 'type' => 'Other Current Liability'],
        ['id' => '35', 'name' => 'Commission Clearing', 'type' => 'Bank'],
        ['id' => '60', 'name' => 'Mentor and Instructor Payouts', 'type' => 'Expense'],
        ['id' => '36', 'name' => 'Stripe Clearing', 'type' => 'Bank'],
    ];

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $redirectUri.'?'.http_build_query(['code' => 'simulated-'.substr(hash('sha256', $state), 0, 12), 'realmId' => self::REALM, 'state' => $state]);
    }

    public function exchange(string $code, string $realmId, string $redirectUri): array
    {
        return ['access_token' => 'sim-access-'.$code, 'refresh_token' => 'sim-refresh-'.$code, 'expires_in' => 3600, 'company' => 'Sandbox Company (simulated)'];
    }

    public function accounts(AccountingConnection $connection): array
    {
        return self::ACCOUNTS;
    }

    public function post(AccountingConnection $connection, JournalEntry $entry): string
    {
        return 'JE-'.strtoupper(substr(hash('sha256', $connection->realm_id.json_encode($entry->toQuickBooks())), 0, 8));
    }
}
