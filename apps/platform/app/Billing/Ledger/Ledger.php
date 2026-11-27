<?php

declare(strict_types=1);

namespace App\Billing\Ledger;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The ledger (B5.3, ADR 0021): every movement of money, AI credits, seats
 * and points as a transaction whose entries add up to zero for each unit.
 * Entries are never edited; a correction is a new transaction. Each
 * account keeps its running balance, updated under a row lock with the
 * entry, so balances always equal the sum of their entries.
 */
final readonly class Ledger
{
    /** Platform-wide accounts, by type. */
    public const PLATFORM = [
        'revenue' => Unit::Usd,
        'sales_tax' => Unit::Usd,
        'sponsor_discounts' => Unit::Usd,
        'refunds' => Unit::Usd,
        'ai_pool' => Unit::Credit,
        'ai_used' => Unit::Credit,
        'credits_sold' => Unit::Credit,
        'seats_issued' => Unit::Seat,
        'points_issued' => Unit::Point,
        'points_redeemed' => Unit::Point,
    ];

    public function platform(string $type): LedgerAccount
    {
        $unit = self::PLATFORM[$type] ?? throw new InvalidArgumentException("Unknown platform account {$type}.");

        return $this->account("platform:{$type}", $type, $unit);
    }

    /** Money held at a gateway until it's paid out (one per gateway and environment). */
    public function clearing(string $gateway, string $environment): LedgerAccount
    {
        return $this->account("clearing:{$gateway}:{$environment}", 'gateway_clearing', Unit::Usd);
    }

    /** A person's accounts: `monthly` and `topup` credits, `points`, and `usd` (what they've earned). */
    public function person(User $user, string $kind): LedgerAccount
    {
        [$type, $unit] = match ($kind) {
            'monthly' => ['person_credits_monthly', Unit::Credit],
            'topup' => ['person_credits_topup', Unit::Credit],
            'points' => ['person_points', Unit::Point],
            'usd' => ['person_earnings', Unit::Usd],
            default => throw new InvalidArgumentException("Unknown person account {$kind}."),
        };

        return $this->account("person:{$user->id}:{$kind}", $type, $unit, 'user', $user->id);
    }

    /** An organisation's accounts: sponsor `credits` and `seats` pools, `usd` (what a school has earned) and `points` (bonus points a brokerage funded). */
    public function organisation(Organisation $organisation, string $kind): LedgerAccount
    {
        [$type, $unit] = match ($kind) {
            'credits' => ['sponsor_credit_pool', Unit::Credit],
            'seats' => ['sponsor_seat_pool', Unit::Seat],
            'usd' => ['tenant_earnings', Unit::Usd],
            // Bonus points a brokerage funds for its agents (A13.3a); it goes negative by what was given.
            'points' => ['brokerage_points_funded', Unit::Point],
            default => throw new InvalidArgumentException("Unknown organisation account {$kind}."),
        };

        return $this->account("organisation:{$organisation->id}:{$kind}", $type, $unit, 'organisation', $organisation->id);
    }

    /** The account, if it has ever been used. */
    public function find(string $key): ?LedgerAccount
    {
        return LedgerAccount::query()->where('key', $key)->first();
    }

    /** Whether a transaction for this reason and reference was already posted (so events are counted once). */
    public function posted(string $reason, string $referenceType, string $referenceId): bool
    {
        return LedgerEntry::query()->where('reason', $reason)->where('reference_type', $referenceType)->where('reference_id', $referenceId)->exists();
    }

    /**
     * Posts one transaction. Each line is [account, amount]; amounts of the
     * same unit must add up to zero. Lines of zero are left out, unless
     * `$keepZero` (a marker that something happened, such as a month
     * granted with no credits). Returns the transaction id.
     *
     * @param  list<array{0: LedgerAccount, 1: int}>  $lines
     */
    public function post(string $reason, array $lines, ?string $referenceType = null, ?string $referenceId = null, ?string $memo = null, bool $keepZero = false): string
    {
        $lines = $keepZero ? $lines : array_values(array_filter($lines, static fn (array $line): bool => $line[1] !== 0));
        $sums = [];

        foreach ($lines as [$account, $amount]) {
            $sums[$account->unit] = ($sums[$account->unit] ?? 0) + $amount;
        }

        foreach ($sums as $unit => $sum) {
            if ($sum !== 0) {
                throw new InvalidArgumentException("Ledger transaction {$reason} doesn't balance in {$unit} ({$sum}).");
            }
        }

        $transaction = (string) Str::uuid();

        if ($lines === []) {
            return $transaction;
        }

        DB::transaction(function () use ($lines, $transaction, $reason, $referenceType, $referenceId, $memo): void {
            $ids = array_values(array_unique(array_map(static fn (array $line): int => $line[0]->id, $lines)));
            sort($ids);
            // Lock in id order, so two transactions can't deadlock.
            $locked = LedgerAccount::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as [$account, $amount]) {
                $row = $locked->get($account->id) ?? throw new InvalidArgumentException('Unknown ledger account.');
                $row->balance += $amount;
                $row->save();

                LedgerEntry::query()->create([
                    'transaction' => $transaction,
                    'ledger_account_id' => $row->id,
                    'unit' => $row->unit,
                    'amount' => $amount,
                    'balance_after' => $row->balance,
                    'reason' => $reason,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'memo' => $memo === null ? null : mb_substr($memo, 0, 200),
                ]);
                $account->balance = $row->balance;
            }
        });

        return $transaction;
    }

    /** The balance as it stands in the database (not a cached copy). */
    public function balance(LedgerAccount $account): int
    {
        $balance = LedgerAccount::query()->whereKey($account->id)->value('balance');

        return is_int($balance) ? $balance : 0;
    }

    private function account(string $key, string $type, Unit $unit, ?string $ownerType = null, ?int $ownerId = null): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(['key' => $key], ['type' => $type, 'unit' => $unit->value, 'owner_type' => $ownerType, 'owner_id' => $ownerId]);
    }
}
