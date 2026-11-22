<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Billing\GatewaySettings;
use App\Billing\ProductKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\GatewayRequest;
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
    public function index(GatewaySettings $settings): Response
    {
        return Inertia::render('admin/gateways/index', [
            'gateways' => $settings->overview(),
            'fees' => [
                'platform_fee_percent' => config()->integer('reos.billing.platform_fee_percent', 10),
                'refund_days' => config()->integer('reos.billing.refund_days', 7),
            ],
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

    public function resume(string $gateway, #[CurrentUser] User $actor, GatewaySettings $settings): RedirectResponse
    {
        $settings->resume($gateway, $actor);

        return back();
    }
}
