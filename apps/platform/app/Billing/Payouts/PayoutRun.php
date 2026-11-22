<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use App\Models\ConnectedAccount;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The weekly payout (B5.2b, `reos:payouts:run`, Mondays): for every payee
 * whose Stripe account can take payouts, one transfer for all their pending
 * lines. A payee still setting up keeps earning; their lines wait for the
 * next run. Each transfer goes out once (its idempotency key is the
 * payout's id), and a failure leaves the lines pending for next week.
 *
 * @phpstan-type RunResult array{payouts: int, amount: int, waiting: int}
 */
final readonly class PayoutRun
{
    public function __construct(private PayoutProviders $providers, private ConnectedAccounts $accounts, private AuditLogger $audit) {}

    /** @return RunResult */
    public function run(): array
    {
        $provider = $this->providers->current();
        $result = ['payouts' => 0, 'amount' => 0, 'waiting' => 0];

        if ($provider === null) {
            return $result;
        }

        $payees = PayoutLine::query()->where('status', PayoutLineStatus::Pending)
            ->select('payee_type', 'payee_id')->distinct()->get();

        foreach ($payees as $payee) {
            $account = ConnectedAccount::query()->where('owner_type', $payee->payee_type)->where('owner_id', $payee->payee_id)
                ->where('environment', $provider->environment())->first();

            if ($account !== null && ! $account->payouts_enabled) {
                $account = $this->accounts->refresh($account);
            }

            if ($account === null || ! $account->payouts_enabled) {
                $result['waiting']++;

                continue;
            }

            $payout = $this->pay($provider, $account, $payee->payee_type, $payee->payee_id);

            if ($payout !== null) {
                $result['payouts']++;
                $result['amount'] += $payout->amount_cents;
            }
        }

        return $result;
    }

    private function pay(PayoutProvider $provider, ConnectedAccount $account, string $type, int $id): ?Payout
    {
        // Claim the lines first, so two runs at once can't pay them twice.
        $payout = DB::transaction(function () use ($account, $type, $id): ?Payout {
            $lines = PayoutLine::query()->where('payee_type', $type)->where('payee_id', $id)
                ->where('status', PayoutLineStatus::Pending)->whereNull('payout_id')->lockForUpdate()->get();
            $amount = array_sum(array_map(static fn (PayoutLine $l): int => $l->amount_cents, $lines->all()));

            if ($amount <= 0) {
                return null;
            }

            $payout = Payout::query()->create([
                'uuid' => (string) Str::uuid(), 'connected_account_id' => $account->id, 'environment' => $account->environment,
                'amount_cents' => $amount, 'currency' => 'usd', 'status' => 'sending',
            ]);
            PayoutLine::query()->whereKey($lines->modelKeys())->update(['payout_id' => $payout->id]);

            return $payout;
        });

        if ($payout === null) {
            return null;
        }

        try {
            $transfer = $provider->transfer($account->stripe_account_id, $payout->amount_cents, $payout->currency, $payout->uuid, __('payouts.transferDescription'));
            DB::transaction(function () use ($payout, $transfer): void {
                $payout->forceFill(['status' => Payout::SENT, 'transfer_id' => $transfer])->save();
                PayoutLine::query()->where('payout_id', $payout->id)->update(['status' => PayoutLineStatus::Paid]);
            });
            $this->audit->record('payouts.sent', 'payout', $payout->uuid, ['amount' => $payout->amount_cents, 'account' => $account->id]);

            return $payout;
        } catch (Throwable $e) {
            report($e);
            DB::transaction(function () use ($payout, $e): void {
                $payout->forceFill(['status' => Payout::FAILED, 'failure' => mb_substr($e->getMessage(), 0, 200)])->save();
                PayoutLine::query()->where('payout_id', $payout->id)->update(['payout_id' => null]);
            });

            return null;
        }
    }
}
