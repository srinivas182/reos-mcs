<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Billing\GatewaySettings;
use App\Billing\OrderStatus;
use App\Billing\Payouts\PayoutStatements;
use App\Billing\ProductKind;
use App\Billing\Refunds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\GatewayRequest;
use App\Models\Order;
use App\Models\ReconciliationRun;
use App\Models\State;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payment gateways (web journey screen 66, B5.2a): every gateway with its
 * status, states and currency, what it's used for, payouts, tenant-owned
 * accounts and health; fees and tax; the rules; the kill switch. Keys are
 * added in Settings › Integrations ("Add gateway").
 */
final class GatewayController extends Controller
{
    public function index(Request $request, GatewaySettings $settings, PayoutStatements $payouts, Refunds $refunds): Response
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? null;
        $search = is_string($search) ? trim($search) : '';

        return Inertia::render('admin/gateways/index', [
            'gateways' => $settings->overview(),
            'fees' => [
                'platform_fee_percent' => config()->integer('reos.billing.platform_fee_percent', 10),
                'refund_days' => config()->integer('reos.billing.refund_days', 7),
            ],
            // Stripe Connect payouts (B5.2b).
            'payouts' => $payouts->platform(),
            // Orders and refunds, and the ledger's daily reconciliation (B5.3).
            'search' => $search,
            'orders' => Order::query()->with(['product:id,name', 'user:id,name,email'])
                ->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded])
                ->when($search !== '', static fn ($q) => $q->where(static fn ($w) => $w->where('uuid', $search)
                    ->orWhereHas('user', static fn ($u) => $u->where('email', 'like', '%'.addcslashes(mb_strtolower($search), '%_\\').'%'))))
                ->latest('paid_at')->limit(20)->get()
                ->map(static fn (Order $o): array => [
                    'uuid' => $o->uuid,
                    'product' => $o->product->name,
                    'buyer' => $o->user === null ? null : ['name' => $o->user->name, 'email' => $o->user->email],
                    'gateway' => $o->gateway,
                    'environment' => $o->environment,
                    'total' => $o->total_cents,
                    'status' => $o->status->value,
                    'paid_at' => $o->paid_at?->toIso8601String(),
                    'policy' => $refunds->policy($o),
                ])->values()->all(),
            'reconciliation' => ReconciliationRun::query()->latest('day')->latest('id')->limit(6)->get()
                ->map(static fn (ReconciliationRun $r): array => [
                    'day' => $r->day->toDateString(),
                    'gateway' => $r->gateway,
                    'environment' => $r->environment,
                    'status' => $r->status,
                    'matched' => $r->matched,
                    'differences' => array_slice($r->differences ?? [], 0, 5),
                    'difference_count' => count($r->differences ?? []),
                ])->values()->all(),
            'options' => [
                'kinds' => ProductKind::values(),
                'states' => State::query()->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->values()->all(),
            ],
        ]);
    }

    public function update(GatewayRequest $request, string $gateway, #[CurrentUser] User $actor, GatewaySettings $settings): RedirectResponse
    {
        $settings->save($gateway, $request->settings(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('gateways.saved')]);

        return back();
    }

    /** The kill switch, with the reason everyone sees in the audit log. */
    public function pause(Request $request, string $gateway, #[CurrentUser] User $actor, GatewaySettings $settings): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']])['reason'];
        $settings->pause($gateway, is_string($reason) ? $reason : '', $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('gateways.pausedToast')]);

        return back();
    }

    /** A full or partial refund (B5.3), with the reason kept in the audit log. */
    public function refund(Request $request, Order $order, #[CurrentUser] User $actor, Refunds $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ]);
        $amount = isset($data['amount']) && is_numeric($data['amount']) ? (int) round((float) $data['amount'] * 100) : null;
        $refund = $refunds->refund($order, $amount, is_string($data['reason']) ? $data['reason'] : '', $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $refund->full ? __('billing.refunds.doneFull') : __('billing.refunds.donePartial')]);

        return back();
    }

    public function resume(string $gateway, #[CurrentUser] User $actor, GatewaySettings $settings): RedirectResponse
    {
        $settings->resume($gateway, $actor);

        return back();
    }
}
