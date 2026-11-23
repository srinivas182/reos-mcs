<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Billing\Packages;
use App\Billing\SponsorCodes;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public site (web journey screen 1): the reos.com home page and a landing
 * page for each audience. Server-rendered for search and AI answer engines.
 */
final class PublicSiteController extends Controller
{
    /** Audiences in the top navigation, in journey order. */
    public const AUDIENCES = ['candidates', 'agents', 'brokerages', 'schools', 'associations', 'service-providers'];

    public function home(): Response
    {
        return Inertia::render('welcome');
    }

    public function audience(string $audience): Response
    {
        return Inertia::render('public/audience', ['audience' => $audience]);
    }

    /**
     * Packages and prices for candidates (A8.3b): live plans in a state with
     * what's included, sponsor discounts and, when signed in, the person's own
     * price; checkout as in B5.1. Texas unless another state is chosen.
     */
    public function packages(Request $request, Packages $packages, ?string $code = null): Response
    {
        $states = $packages->states();
        $state = $code;
        $code = strtoupper($state ?? 'TX');
        abort_if($state !== null && ! in_array($code, array_column($states, 'code'), true), 404);
        $user = $request->user();

        return Inertia::render('public/packages', [
            'state' => ['code' => $code, 'name' => collect($states)->firstWhere('code', $code)['name'] ?? $code],
            'states' => $states,
            'packages' => $packages->in($code, $user instanceof User ? $user : null),
            'signedIn' => $user instanceof User,
        ]);
    }

    /** "Have a code?": what a sponsor's code covers, before signing in to use it. */
    public function code(Request $request, SponsorCodes $codes): JsonResponse
    {
        return response()->json(['data' => $codes->preview((string) $request->validate(['code' => ['required', 'string', 'max:40']])['code'])]);
    }
}
