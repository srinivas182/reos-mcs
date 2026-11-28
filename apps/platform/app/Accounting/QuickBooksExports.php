<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Connections\ConnectorRegistry;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\AccountingConnection;
use App\Models\AccountingExport;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Models\SplitSheet;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * QuickBooks Online for brokerages (B17.3, ADR 0087; broker web journey,
 * integrations).
 *
 * - **Connect** the brokerage's company by OAuth (the simulator in
 *   development), and **map the accounts once**.
 * - **Signed split sheets** post as a journal entry: the gross to the
 *   clearing account, and the brokerage's, team's and agent's shares to
 *   their accounts. They post when the agent signs, or from Export now.
 * - **Payouts** (B5.2b) for sales the brokerage made (mentor sessions)
 *   post as an expense against the payouts clearing account.
 * - Each sheet and payout posts **once** (`accounting_exports`); a failure
 *   is kept with its reason and tried again. Every step is audited.
 *
 * @phpstan-type Board array{connection: array{company: string, mode: string, status: string, connected_at: string|null, accounts: array<string, string>, mapped: bool}|null, chart: list<array{id: string, name: string, type: string}>, roles: list<string>, pending: array{split_sheets: list<array{id: string, property: string, agent: string, closing_on: string, gross_cents: int}>, payouts: list<array{id: string, payee: string, sent_on: string, amount_cents: int}>}, exports: list<array{kind: string, subject: string, amount_cents: int, status: string, ref: string|null, error: string|null, at: string}>}
 */
final readonly class QuickBooksExports
{
    public const INTEGRATION = 'quickbooks-online';

    /** The accounts a brokerage maps once. */
    public const ROLES = ['clearing', 'brokerage_income', 'team_payable', 'agent_payable', 'payouts_expense', 'payouts_clearing'];

    public function __construct(private ConnectorRegistry $registry, private CredentialVault $vault, private Tenancy $tenancy, private AuditLogger $audit) {}

    /** @return Board */
    public function board(Organisation $brokerage): array
    {
        $connection = $this->connection($brokerage);
        $chart = [];

        if ($connection !== null && $connection->status === 'connected') {
            try {
                $chart = $this->client($connection->mode)->accounts($connection);
            } catch (Throwable $e) {
                $this->failed($connection, $e);
            }
        }

        $exports = [];

        foreach ($this->platform(static fn () => AccountingExport::query()->where('organisation_id', $brokerage->id)->latest('id')->limit(20)->get()) as $x) {
            $exports[] = ['kind' => $x->kind, 'subject' => $x->subject_id, 'amount_cents' => $x->amount_cents, 'status' => $x->status, 'ref' => $x->ref, 'error' => $x->error, 'at' => ($x->posted_at ?? $x->updated_at)?->toIso8601String() ?? ''];
        }

        return [
            'connection' => $connection === null ? null : [
                'company' => $connection->company_name, 'mode' => $connection->mode, 'status' => $connection->status, 'connected_at' => $connection->created_at?->toIso8601String(),
                'accounts' => $connection->accounts ?? [], 'mapped' => self::mapped($connection),
            ],
            'chart' => $chart, 'roles' => self::ROLES,
            'pending' => ['split_sheets' => $this->pendingSheets($brokerage), 'payouts' => $this->pendingPayouts($brokerage)],
            'exports' => $exports,
        ];
    }

    /** Where the broker signs in to Intuit to connect the company. */
    public function start(Organisation $brokerage, User $broker): string
    {
        $mode = $this->mode();
        $state = Str::random(40);
        Cache::put('qbo:state:'.$state, ['organisation' => $brokerage->id, 'user' => $broker->id], now()->addMinutes(10));
        $this->record('accounting.connect_started', $brokerage, ['mode' => $mode->value], $broker);

        return $this->client($mode->value)->authorizationUrl($state, route('accounting.quickbooks.callback'));
    }

    /** Intuit's redirect back: the company is connected. */
    public function callback(Organisation $brokerage, User $broker, string $state, string $code, string $realmId): AccountingConnection
    {
        $expected = Cache::pull('qbo:state:'.$state);

        if (! is_array($expected) || ($expected['organisation'] ?? null) !== $brokerage->id || ($expected['user'] ?? null) !== $broker->id) {
            throw ValidationException::withMessages(['state' => __('accounting.errors.state')]);
        }

        $mode = $this->mode();
        $tokens = $this->client($mode->value)->exchange($code, $realmId, route('accounting.quickbooks.callback'));
        $connection = $this->platform(fn () => AccountingConnection::query()->updateOrCreate(['organisation_id' => $brokerage->id], [
            'integration_id' => self::INTEGRATION, 'mode' => $mode->value, 'realm_id' => $realmId, 'company_name' => mb_substr($tokens['company'], 0, 160),
            'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'], 'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'connected_by' => $broker->id, 'status' => 'connected', 'last_error' => null,
        ]));
        $this->record('accounting.connected', $brokerage, ['company' => $connection->company_name, 'mode' => $mode->value], $broker);

        return $connection;
    }

    public function disconnect(Organisation $brokerage, User $broker): void
    {
        $this->platform(static fn () => AccountingConnection::query()->where('organisation_id', $brokerage->id)->delete());
        $this->record('accounting.disconnected', $brokerage, [], $broker);
    }

    /** @param array<string, string> $accounts role => QuickBooks account id */
    public function map(Organisation $brokerage, array $accounts, User $broker): AccountingConnection
    {
        $connection = $this->connection($brokerage) ?? throw ValidationException::withMessages(['accounts' => __('accounting.errors.notConnected')]);
        $ids = array_column($this->client($connection->mode)->accounts($connection), 'id');
        $clean = [];

        foreach (self::ROLES as $role) {
            $id = $accounts[$role] ?? null;

            if (! is_string($id) || ! in_array($id, $ids, true)) {
                throw ValidationException::withMessages(["accounts.{$role}" => __('accounting.errors.account')]);
            }

            $clean[$role] = $id;
        }

        $before = $connection->accounts ?? [];
        $this->platform(static fn () => $connection->forceFill(['accounts' => $clean])->save());
        $this->record('accounting.accounts_mapped', $brokerage, ['before' => $before, 'after' => $clean], $broker);

        return $connection;
    }

    /**
     * Export now: every signed split sheet and sent payout not posted yet.
     *
     * @return array{posted: int, failed: int}
     */
    public function exportAll(Organisation $brokerage, ?User $actor): array
    {
        $connection = $this->ready($brokerage);
        $counts = ['posted' => 0, 'failed' => 0];

        foreach ($this->platform(fn () => $this->sheetsQuery($brokerage)->get()) as $sheet) {
            $counts[$this->postSheet($connection, $brokerage, $sheet)->status === 'posted' ? 'posted' : 'failed']++;
        }

        foreach ($this->payoutsOf($brokerage) as [$payout, $cents, $payee]) {
            $counts[$this->postPayout($connection, $brokerage, $payout, $cents, $payee)->status === 'posted' ? 'posted' : 'failed']++;
        }

        $this->record('accounting.exported', $brokerage, $counts, $actor);

        return $counts;
    }

    /** The agent signed: post the sheet now if the brokerage is connected and mapped. Never stops the signing. */
    public function signed(SplitSheet $sheet): void
    {
        try {
            $brokerage = $this->brokerageOfTeam($sheet->organisation_id);
            $connection = $brokerage === null ? null : $this->connection($brokerage);

            if ($brokerage !== null && $connection !== null && $connection->status === 'connected' && self::mapped($connection)) {
                $this->postSheet($connection, $brokerage, $sheet);
            }
        } catch (Throwable $e) {
            Log::warning('QuickBooks export after signing failed', ['sheet' => $sheet->uuid, 'error' => $e->getMessage()]);
        }
    }

    /** Daily: every connected, mapped brokerage exports what's waiting. Returns how many entries posted. */
    public function sweep(): int
    {
        $posted = 0;

        foreach ($this->platform(static fn () => AccountingConnection::query()->where('status', 'connected')->get()) as $connection) {
            $brokerage = $this->platform(static fn () => Organisation::query()->find($connection->organisation_id));

            if ($brokerage instanceof Organisation && self::mapped($connection)) {
                $posted += $this->exportAll($brokerage, null)['posted'];
            }
        }

        return $posted;
    }

    /**
     * The journal entry for a signed split sheet.
     *
     * @param  array<string, string>  $accounts
     */
    public static function sheetEntry(SplitSheet $sheet, string $agent, array $accounts): JournalEntry
    {
        $note = 'REOS split sheet '.$sheet->uuid.': '.$sheet->property.', '.$agent;

        return new JournalEntry(CarbonImmutable::instance($sheet->closing_on), 'REOS-'.substr($sheet->uuid, -12), $note, [
            ['account' => $accounts['clearing'] ?? '', 'posting' => 'Debit', 'cents' => $sheet->gross_cents, 'description' => $sheet->property],
            ['account' => $accounts['brokerage_income'] ?? '', 'posting' => 'Credit', 'cents' => $sheet->brokerage_cents, 'description' => 'Brokerage share'],
            ['account' => $accounts['team_payable'] ?? '', 'posting' => 'Credit', 'cents' => $sheet->team_cents, 'description' => 'Team share'],
            ['account' => $accounts['agent_payable'] ?? '', 'posting' => 'Credit', 'cents' => $sheet->agent_cents, 'description' => 'Agent share: '.$agent],
        ]);
    }

    private function postSheet(AccountingConnection $connection, Organisation $brokerage, SplitSheet $sheet): AccountingExport
    {
        $sheet->loadMissing('agent');

        return $this->post($connection, $brokerage, 'split_sheet', $sheet->uuid, $sheet->gross_cents, fn (): JournalEntry => self::sheetEntry($sheet, (string) $sheet->agent->name, $connection->accounts ?? []), function (string $ref) use ($sheet): void {
            $this->tenancy->run($sheet->organisation_id, static fn () => $sheet->forceFill(['exported_at' => now(), 'export_ref' => $ref])->save());
        });
    }

    private function postPayout(AccountingConnection $connection, Organisation $brokerage, Payout $payout, int $cents, string $payee): AccountingExport
    {
        $accounts = $connection->accounts ?? [];

        return $this->post($connection, $brokerage, 'payout', $payout->uuid, $cents, static fn (): JournalEntry => new JournalEntry(
            CarbonImmutable::instance($payout->created_at ?? now()), 'REOS-P-'.substr($payout->uuid, -10), 'REOS payout '.$payout->uuid.' to '.$payee,
            [
                ['account' => $accounts['payouts_expense'] ?? '', 'posting' => 'Debit', 'cents' => $cents, 'description' => 'Payout: '.$payee],
                ['account' => $accounts['payouts_clearing'] ?? '', 'posting' => 'Credit', 'cents' => $cents, 'description' => 'Paid through Stripe'],
            ],
        ), static fn (string $ref) => null);
    }

    /**
     * Posts once; a failure is saved with its reason and tried again next time.
     *
     * @param  \Closure(): JournalEntry  $entry
     * @param  \Closure(string): void  $done
     */
    private function post(AccountingConnection $connection, Organisation $brokerage, string $kind, string $subject, int $cents, \Closure $entry, \Closure $done): AccountingExport
    {
        /** @var AccountingExport $export */
        $export = $this->platform(static fn () => AccountingExport::query()->firstOrNew(['organisation_id' => $brokerage->id, 'kind' => $kind, 'subject_id' => $subject]));

        if ($export->exists && $export->status === 'posted') {
            return $export;
        }

        try {
            $ref = $this->client($connection->mode)->post($connection, $entry());
            $this->platform(static fn () => $export->forceFill(['amount_cents' => $cents, 'status' => 'posted', 'ref' => $ref, 'error' => null, 'posted_at' => now()])->save());
            $done($ref);
        } catch (Throwable $e) {
            $this->platform(static fn () => $export->forceFill(['amount_cents' => $cents, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save());
        }

        return $export;
    }

    /** @return list<array{id: string, property: string, agent: string, closing_on: string, gross_cents: int}> */
    private function pendingSheets(Organisation $brokerage): array
    {
        $out = [];

        foreach ($this->platform(fn () => $this->sheetsQuery($brokerage)->with('agent')->get()) as $s) {
            $out[] = ['id' => $s->uuid, 'property' => $s->property, 'agent' => (string) $s->agent->name, 'closing_on' => $s->closing_on->toDateString(), 'gross_cents' => $s->gross_cents];
        }

        return $out;
    }

    /** @return list<array{id: string, payee: string, sent_on: string, amount_cents: int}> */
    private function pendingPayouts(Organisation $brokerage): array
    {
        $out = [];

        foreach ($this->payoutsOf($brokerage) as [$payout, $cents, $payee]) {
            $out[] = ['id' => $payout->uuid, 'payee' => $payee, 'sent_on' => $payout->created_at?->toDateString() ?? '', 'amount_cents' => $cents];
        }

        return $out;
    }

    /** @return Builder<SplitSheet> */
    private function sheetsQuery(Organisation $brokerage): Builder
    {
        $posted = AccountingExport::query()->where('organisation_id', $brokerage->id)->where('kind', 'split_sheet')->where('status', 'posted')->pluck('subject_id')->all();
        $teams = Organisation::query()->where('parent_id', $brokerage->id)->pluck('id')->push($brokerage->id);

        return SplitSheet::query()->whereIn('organisation_id', $teams)->where('status', 'signed')->whereNotIn('uuid', $posted)->orderBy('closing_on');
    }

    /**
     * Sent payouts holding lines for sales the brokerage made, not posted yet.
     *
     * @return list<array{0: Payout, 1: int, 2: string}>
     */
    private function payoutsOf(Organisation $brokerage): array
    {
        return $this->platform(static function () use ($brokerage): array {
            $posted = AccountingExport::query()->where('organisation_id', $brokerage->id)->where('kind', 'payout')->where('status', 'posted')->pluck('subject_id')->all();
            $lines = PayoutLine::query()->whereNotNull('payout_id')->whereHas('order.product', static fn ($q) => $q->where('seller_organisation_id', $brokerage->id))->get()->groupBy('payout_id');
            $out = [];

            foreach (Payout::query()->whereIn('id', $lines->keys())->where('status', 'sent')->with('account')->orderBy('id')->get() as $payout) {
                if (in_array($payout->uuid, $posted, true)) {
                    continue;
                }

                $owner = $payout->account->owner_type === 'user' ? User::query()->find($payout->account->owner_id) : null;
                $sum = $lines->get($payout->id)?->sum('amount_cents');
                $out[] = [$payout, is_numeric($sum) ? (int) $sum : 0, $owner === null ? '—' : (string) $owner->name];
            }

            return $out;
        });
    }

    private function ready(Organisation $brokerage): AccountingConnection
    {
        $connection = $this->connection($brokerage);

        if ($connection === null || $connection->status !== 'connected') {
            throw ValidationException::withMessages(['accounting' => __('accounting.errors.notConnected')]);
        }

        if (! self::mapped($connection)) {
            throw ValidationException::withMessages(['accounting' => __('accounting.errors.notMapped')]);
        }

        return $connection;
    }

    private static function mapped(AccountingConnection $connection): bool
    {
        return array_diff(self::ROLES, array_keys($connection->accounts ?? [])) === [];
    }

    private function connection(Organisation $brokerage): ?AccountingConnection
    {
        return $this->platform(static fn () => AccountingConnection::query()->where('organisation_id', $brokerage->id)->first());
    }

    private function brokerageOfTeam(int $organisationId): ?Organisation
    {
        $org = $this->platform(static fn () => Organisation::query()->find($organisationId));

        return $org instanceof Organisation && $org->parent_id !== null ? $this->platform(static fn () => Organisation::query()->find($org->parent_id)) : $org;
    }

    private function mode(): IntegrationMode
    {
        $mode = $this->registry->mode(self::INTEGRATION);

        if ($mode === IntegrationMode::Off) {
            throw ValidationException::withMessages(['accounting' => __('accounting.errors.off')]);
        }

        return $mode;
    }

    private function client(string $mode): QuickBooks
    {
        if ($mode === IntegrationMode::Simulator->value) {
            return new SimulatedQuickBooks;
        }

        $environment = $mode === IntegrationMode::Live->value ? CredentialEnvironment::Live : CredentialEnvironment::Sandbox;
        $keys = $this->vault->get(self::INTEGRATION, $environment);

        return new IntuitQuickBooks(is_string($keys['client_id'] ?? null) ? $keys['client_id'] : '', is_string($keys['client_secret'] ?? null) ? $keys['client_secret'] : '', $environment === CredentialEnvironment::Sandbox);
    }

    private function failed(AccountingConnection $connection, Throwable $e): void
    {
        $this->platform(static fn () => $connection->forceFill(['status' => 'needs_reconnect', 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save());
    }

    /**
     * Accounting rows are the brokerage's, but exports run for teams and in jobs: read and write at platform level.
     *
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    private function platform(\Closure $work): mixed
    {
        return $this->tenancy->asPlatform($work);
    }

    /** @param array<string, mixed> $properties */
    private function record(string $action, Organisation $brokerage, array $properties, ?User $actor): void
    {
        $this->platform(fn () => $this->audit->record($action, 'organisation', (string) $brokerage->id, $properties, $actor, $brokerage->id));
    }
}
