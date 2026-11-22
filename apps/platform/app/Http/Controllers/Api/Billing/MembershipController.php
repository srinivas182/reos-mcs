<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Billing\Membership;
use App\Billing\SponsorCodes;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone's Membership screen (B5.1b; candidate screen 6): no prices and
 * no purchases in the app. It shows what a plan includes, offers "Email me
 * the link" (or a link to reos.com when the super admin allows it, iPhone
 * only), and unlocks a sponsored seat with a code.
 */
final class MembershipController extends Controller
{
    /**
     * My membership.
     *
     * Whether I have access, what a plan includes (never its price) and how
     * the iPhone app points to buying (`email`: "Email me the link";
     * `link`: open `buy_url`).
     *
     * @response array{active: bool, features: list<string>, includes: list<string>, ios_buy_link: 'email'|'link', buy_url: string|null}
     */
    public function show(#[CurrentUser] User $user, Membership $membership): JsonResponse
    {
        return response()->json($membership->summary($user));
    }

    /**
     * Email me the link.
     *
     * Sends the plans page on reos.com to my email.
     *
     * @response array{sent: bool}
     */
    public function emailLink(#[CurrentUser] User $user, Membership $membership): JsonResponse
    {
        return response()->json(['sent' => $membership->emailLink($user)]);
    }

    /**
     * Enter a code.
     *
     * A school, brokerage or association's code: unlocks what it sponsors
     * at once. 422 when the code is unknown, expired, full or already used.
     *
     * @response array{sponsor: string, product: string, features: list<string>}
     */
    public function redeem(Request $request, #[CurrentUser] User $user, SponsorCodes $codes): JsonResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'min:4', 'max:40']])['code'];
        $code = $codes->redeem($user, is_string($input) ? $input : '');

        return response()->json([
            'sponsor' => $code->sponsor->name,
            'product' => $code->product->name,
            'features' => $code->product->features ?? [],
        ]);
    }
}
