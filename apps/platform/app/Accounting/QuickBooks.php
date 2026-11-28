<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Models\AccountingConnection;

/** QuickBooks Online, or its simulator (B17.3). */
interface QuickBooks
{
    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * Swaps the code from Intuit's redirect for tokens and the company's name.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, company: string}
     */
    public function exchange(string $code, string $realmId, string $redirectUri): array;

    /**
     * The company's chart of accounts.
     *
     * @return list<array{id: string, name: string, type: string}>
     */
    public function accounts(AccountingConnection $connection): array;

    /** Posts the entry; returns its id in QuickBooks. */
    public function post(AccountingConnection $connection, JournalEntry $entry): string;
}
