<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\Checkout;
use App\Billing\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('reos:billing:reconcile')]
#[Description('Ask the gateway about checkouts still waiting (a missed webhook or a closed tab), and expire old ones')]
final class BillingReconcileCommand extends Command
{
    public function handle(Checkout $checkout): int
    {
        $confirmed = 0;

        Order::query()->where('status', OrderStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where('created_at', '>', now()->subDay())
            ->lazyById()
            ->each(function (Order $order) use ($checkout, &$confirmed): void {
                try {
                    $confirmed += $checkout->confirm($order)->status === OrderStatus::Paid ? 1 : 0;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $expired = Order::query()->where('status', OrderStatus::Pending)->where('created_at', '<=', now()->subDay())->update(['status' => OrderStatus::Expired->value]);

        $this->components->info("{$confirmed} paid orders found; {$expired} expired.");

        return self::SUCCESS;
    }
}
