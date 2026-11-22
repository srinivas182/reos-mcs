<?php

declare(strict_types=1);

namespace App\Billing\Ledger;

use App\Billing\Gateways\Gateways;
use App\Billing\OrderStatus;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payout;
use App\Models\ReconciliationRun;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The daily reconciliation (B5.3, addition 7; `reos:ledger:reconcile`):
 * yesterday's money in the ledger against the gateway. For Stripe, each
 * paid order, refund and payout is matched to Stripe's balance
 * transactions by payment intent or transfer id, amount for amount; for
 * PayPal, Authorize.net and the Simulator, each order's ledger entry is
 * matched to the order the gateway confirmed. Every run also checks that
 * account balances equal the sum of their entries. Differences show on
 * screen 66.
 */
final readonly class Reconciliation
{
    public function __construct(private CredentialVault $vault, private Http $http) {}

    /** @return list<ReconciliationRun> */
    public function run(CarbonImmutable $day): array
    {
        $runs = [];

        foreach (Gateways::CHECKOUT as $gateway) {
            $environments = Order::query()->where('gateway', $gateway)->whereBetween('paid_at', [$day->startOfDay(), $day->endOfDay()])->distinct()->pluck('environment')->all();

            if ($gateway === Gateways::STRIPE) {
                $environments = [...$environments, ...Payout::query()->whereBetween('created_at', [$day->startOfDay(), $day->endOfDay()])->distinct()->pluck('environment')->all()];
                $environments = array_values(array_unique(array_filter($environments, is_string(...))));
            }

            foreach ($environments as $environment) {
                if (is_string($environment)) {
                    $runs[] = $this->reconcile($gateway, $environment, $day);
                }
            }
        }

        return $runs;
    }

    /**
     * Accounts whose balance isn't the sum of their entries (should never happen).
     *
     * @return list<array{kind: string, reference: string, ledger: int|null, gateway: int|null}>
     */
    public static function integrity(): array
    {
        $sums = LedgerEntry::query()->select('ledger_account_id', DB::raw('SUM(amount) as total'))->groupBy('ledger_account_id')->pluck('total', 'ledger_account_id');
        $differences = [];

        foreach (LedgerAccount::query()->get(['id', 'key', 'balance']) as $account) {
            $sum = $sums->get($account->id);
            $total = is_numeric($sum) ? (int) $sum : 0;

            if ($total !== $account->balance) {
                $differences[] = ['kind' => 'balance', 'reference' => $account->key, 'ledger' => $account->balance, 'gateway' => $total];
            }
        }

        return $differences;
    }

    private function reconcile(string $gateway, string $environment, CarbonImmutable $day): ReconciliationRun
    {
        $from = $day->startOfDay();
        $to = $day->endOfDay();
        $ledger = $this->ledgerAmounts($gateway, $environment, $from, $to);
        $status = 'matched';

        try {
            $remote = $gateway === Gateways::STRIPE && $environment !== 'simulator'
                ? $this->stripe($environment, $from, $to)
                : $this->confirmedOrders($gateway, $environment, $from, $to);
        } catch (Throwable $e) {
            report($e);
            $remote = null;
            $status = 'failed';
        }

        $differences = self::integrity();
        $matched = 0;

        if ($remote !== null) {
            foreach (array_unique([...array_keys($ledger), ...array_keys($remote)]) as $key) {
                $mine = $ledger[$key] ?? null;
                $theirs = $remote[$key] ?? null;

                if ($mine !== null && $mine === $theirs) {
                    $matched++;

                    continue;
                }

                [$kind, $reference] = explode(':', $key, 2);
                $differences[] = ['kind' => $kind, 'reference' => $reference, 'ledger' => $mine, 'gateway' => $theirs];
            }
        }

        if ($status !== 'failed' && $differences !== []) {
            $status = 'differences';
        }

        return ReconciliationRun::query()->updateOrCreate(
            ['day' => $day->toDateString(), 'gateway' => $gateway, 'environment' => $environment],
            ['matched' => $matched, 'differences' => array_slice($differences, 0, 200), 'status' => $status],
        );
    }

    /**
     * Money in and out of this gateway's clearing that day, by `kind:reference`.
     *
     * @return array<string, int>
     */
    private function ledgerAmounts(string $gateway, string $environment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $clearing = LedgerAccount::query()->where('key', "clearing:{$gateway}:{$environment}")->first();
        $amounts = [];

        if ($clearing === null) {
            return $amounts;
        }

        $orders = Order::query()->where('gateway', $gateway)->where('environment', $environment)->whereBetween('paid_at', [$from, $to])->get(['id', 'uuid', 'gateway_payment_id']);
        $entries = LedgerEntry::query()->where('ledger_account_id', $clearing->id)->whereIn('reference_id', $orders->pluck('uuid'))->where('reason', 'order_paid')->get()->keyBy('reference_id');

        foreach ($orders as $order) {
            $entry = $entries->get($order->uuid);
            $amounts['order:'.($order->gateway_payment_id ?? $order->uuid)] = $entry instanceof LedgerEntry ? $entry->amount : 0;
        }

        foreach ($this->refunds($gateway, $environment, $from, $to) as $refund) {
            $amounts['refund:'.$refund->gateway_refund_id] = -$refund->amount_cents;
        }

        if ($gateway === 'stripe') {
            foreach (Payout::query()->where('environment', $environment)->where('status', Payout::SENT)->whereBetween('created_at', [$from, $to])->get() as $payout) {
                if ($payout->transfer_id !== null) {
                    $amounts['transfer:'.$payout->transfer_id] = -$payout->amount_cents;
                }
            }
        }

        return $amounts;
    }

    /**
     * Without a balance report (PayPal, Authorize.net, the Simulator): what
     * each paid order says the gateway confirmed, and REOS's own refunds and payouts.
     *
     * @return array<string, int>
     */
    private function confirmedOrders(string $gateway, string $environment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $amounts = [];

        foreach (Order::query()->where('gateway', $gateway)->where('environment', $environment)->whereBetween('paid_at', [$from, $to])->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded])->get() as $order) {
            $amounts['order:'.($order->gateway_payment_id ?? $order->uuid)] = $order->total_cents ?? 0;
        }

        foreach ($this->refunds($gateway, $environment, $from, $to) as $refund) {
            $amounts['refund:'.$refund->gateway_refund_id] = -$refund->amount_cents;
        }

        if ($gateway === 'stripe') {
            foreach (Payout::query()->where('environment', $environment)->where('status', Payout::SENT)->whereBetween('created_at', [$from, $to])->get() as $payout) {
                if ($payout->transfer_id !== null) {
                    $amounts['transfer:'.$payout->transfer_id] = -$payout->amount_cents;
                }
            }
        }

        return $amounts;
    }

    /**
     * REOS's refunds at this gateway made that day.
     *
     * @return list<Refund>
     */
    private function refunds(string $gateway, string $environment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(Refund::query()->where('status', 'done')->whereNotNull('gateway_refund_id')
            ->whereBetween('updated_at', [$from, $to])
            ->whereIn('order_id', Order::query()->select('id')->where('gateway', $gateway)->where('environment', $environment))
            ->get()->all());
    }

    /**
     * Stripe's balance transactions for the day: charges by payment intent,
     * refunds by refund id, transfers by transfer id.
     *
     * @return array<string, int>
     */
    private function stripe(string $environment, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $secret = $this->vault->get('stripe', CredentialEnvironment::from($environment))['secret_key'] ?? null;

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('No Stripe key to reconcile with.');
        }

        $amounts = [];
        $after = null;

        do {
            $response = $this->http->withToken($secret)->acceptJson()->timeout(30)->get('https://api.stripe.com/v1/balance_transactions', array_filter([
                'created' => ['gte' => $from->getTimestamp(), 'lte' => $to->getTimestamp()],
                'limit' => 100,
                'starting_after' => $after,
                'expand' => ['data.source'],
            ]));

            if (! $response->successful()) {
                throw new RuntimeException('Stripe balance transactions failed (HTTP '.$response->status().').');
            }

            $data = $response->json('data');

            foreach (is_array($data) ? $data : [] as $transaction) {
                if (! is_array($transaction)) {
                    continue;
                }

                $source = is_array($transaction['source'] ?? null) ? $transaction['source'] : [];
                $amount = is_int($transaction['amount'] ?? null) ? $transaction['amount'] : 0;
                $key = match ($transaction['type'] ?? null) {
                    'charge', 'payment' => is_string($source['payment_intent'] ?? null) ? 'order:'.$source['payment_intent'] : null,
                    'refund', 'payment_refund' => is_string($source['id'] ?? null) ? 'refund:'.$source['id'] : null,
                    'transfer' => is_string($source['id'] ?? null) ? 'transfer:'.$source['id'] : null,
                    default => null,
                };

                if ($key !== null) {
                    $amounts[$key] = ($amounts[$key] ?? 0) + $amount;
                }

                $after = is_string($transaction['id'] ?? null) ? $transaction['id'] : $after;
            }
        } while ($response->json('has_more') === true);

        return $amounts;
    }
}
