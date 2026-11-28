<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Models\AccountingConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * QuickBooks Online through Intuit's API (B17.3): OAuth 2.0 with the
 * `com.intuit.quickbooks.accounting` scope, refreshing the hour-long access
 * token as needed, the chart of accounts, and journal entries.
 */
final readonly class IntuitQuickBooks implements QuickBooks
{
    public const AUTHORIZE = 'https://appcenter.intuit.com/connect/oauth2';

    public const TOKENS = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

    public function __construct(private string $clientId, private string $clientSecret, private bool $sandbox) {}

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return self::AUTHORIZE.'?'.http_build_query(['client_id' => $this->clientId, 'response_type' => 'code', 'scope' => 'com.intuit.quickbooks.accounting', 'redirect_uri' => $redirectUri, 'state' => $state]);
    }

    public function exchange(string $code, string $realmId, string $redirectUri): array
    {
        $tokens = $this->tokens(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri]);
        $info = $this->api($tokens['access_token'])->get($this->base()."/v3/company/{$realmId}/companyinfo/{$realmId}")->throw()->json('CompanyInfo.CompanyName');

        return [...$tokens, 'company' => is_string($info) ? $info : $realmId];
    }

    public function accounts(AccountingConnection $connection): array
    {
        $rows = $this->api($this->access($connection))->get($this->base()."/v3/company/{$connection->realm_id}/query", ['query' => 'select Id, Name, AccountType from Account where Active = true maxresults 500'])->throw()->json('QueryResponse.Account');
        $out = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && is_string($row['Id'] ?? null) && is_string($row['Name'] ?? null)) {
                $out[] = ['id' => $row['Id'], 'name' => $row['Name'], 'type' => is_string($row['AccountType'] ?? null) ? $row['AccountType'] : ''];
            }
        }

        return $out;
    }

    public function post(AccountingConnection $connection, JournalEntry $entry): string
    {
        $id = $this->api($this->access($connection))->post($this->base()."/v3/company/{$connection->realm_id}/journalentry", $entry->toQuickBooks())->throw()->json('JournalEntry.Id');

        return is_string($id) ? $id : throw new RuntimeException('QuickBooks returned no journal entry id.');
    }

    /** A valid access token, refreshed when it has under a minute left. */
    private function access(AccountingConnection $connection): string
    {
        if ($connection->access_token !== null && $connection->token_expires_at !== null && $connection->token_expires_at->gt(now()->addMinute())) {
            return $connection->access_token;
        }

        $tokens = $this->tokens(['grant_type' => 'refresh_token', 'refresh_token' => (string) $connection->refresh_token]);
        $connection->forceFill(['access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'], 'token_expires_at' => now()->addSeconds($tokens['expires_in'])]);

        if ($connection->exists) {
            $connection->save();
        }

        return $tokens['access_token'];
    }

    /**
     * @param  array<string, string>  $form
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function tokens(array $form): array
    {
        $json = Http::asForm()->acceptJson()->withBasicAuth($this->clientId, $this->clientSecret)->timeout(20)->post(self::TOKENS, $form)->throw()->json();
        $access = is_array($json) ? ($json['access_token'] ?? null) : null;
        $refresh = is_array($json) ? ($json['refresh_token'] ?? null) : null;
        $expires = is_array($json) ? ($json['expires_in'] ?? 3600) : 3600;

        if (! is_string($access) || ! is_string($refresh)) {
            throw new RuntimeException('QuickBooks returned no tokens.');
        }

        return ['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => is_numeric($expires) ? (int) $expires : 3600];
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(20)->withQueryParameters(['minorversion' => '75']);
    }

    private function base(): string
    {
        return $this->sandbox ? 'https://sandbox-quickbooks.api.intuit.com' : 'https://quickbooks.api.intuit.com';
    }
}
