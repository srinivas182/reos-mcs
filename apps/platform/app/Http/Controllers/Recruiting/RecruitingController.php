<?php

declare(strict_types=1);

namespace App\Http\Controllers\Recruiting;

use App\Http\Controllers\Controller;
use App\Licences\Journey\BrokerOffers;
use App\Models\BrokerOffer;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiting (A10.3b, broker web sidebar; candidate screen 17's other side):
 * candidates in the brokerage's states who opted in to offers, as a first
 * name, city and stage; make, change or withdraw an offer; contact details
 * appear once a candidate accepts. Every offer is audited.
 */
final class RecruitingController extends Controller
{
    public function index(#[CurrentUser] User $user, BrokerOffers $offers): Response
    {
        $brokerage = $offers->brokerageOf($user);

        return Inertia::render('recruiting/index', [
            'brokerage' => ['name' => $brokerage->name, 'states' => $brokerage->states ?? []],
            'candidates' => $offers->candidates($brokerage),
        ]);
    }

    public function offer(Request $request, int $candidate, #[CurrentUser] User $user, BrokerOffers $offers): RedirectResponse
    {
        $data = $request->validate([
            'office_city' => ['required', 'string', 'max:120'],
            'split_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'monthly_fee_cents' => ['required', 'integer', 'min:0', 'max:1000000'],
            'fees' => ['nullable', 'string', 'max:300'],
            'training' => ['nullable', 'string', 'max:500'],
            'mentoring' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : null;

        $offers->make($offers->brokerageOf($user), $user, $candidate, [
            'office_city' => (string) $text('office_city'),
            'split_percent' => $request->integer('split_percent'),
            'monthly_fee_cents' => $request->integer('monthly_fee_cents'),
            'fees' => $text('fees'),
            'training' => $text('training'),
            'mentoring' => $request->boolean('mentoring'),
            'message' => $text('message'),
        ]);

        return back()->with('success', __('recruiting.sent'));
    }

    public function withdraw(BrokerOffer $offer, #[CurrentUser] User $user, BrokerOffers $offers): RedirectResponse
    {
        $offers->withdraw($offers->brokerageOf($user), $user, $offer);

        return back();
    }
}
