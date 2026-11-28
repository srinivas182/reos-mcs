<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\OpenHouseRequest;
use App\Http\Requests\Leads\SyncVisitsRequest;
use App\Leads\OpenHouses;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Open house sign-in (B14.3a, ADR 0066): visitors land in the agent's CRM. */
final class OpenHouseController extends Controller
{
    /**
     * My open houses.
     *
     * Newest first, with the sign-in link and QR code, whether sign-in is open (an hour before to six hours after), and how many signed in.
     *
     * @response array{data: list<array{id: string, address: string, city: string|null, starts_at: string, ends_at: string, open: bool, url: string, qr_url: string, visits: int}>}
     */
    public function index(#[CurrentUser] User $user, OpenHouses $houses): JsonResponse
    {
        return response()->json(['data' => $houses->list($user)]);
    }

    /**
     * Start an open house.
     *
     * @response array{data: array{id: string, address: string, city: string|null, starts_at: string, ends_at: string, open: bool, url: string, qr_url: string, visits: int}}
     */
    public function store(OpenHouseRequest $request, #[CurrentUser] User $user, OpenHouses $houses): JsonResponse
    {
        $city = $request->filled('city') ? $request->string('city')->toString() : null;

        return response()->json(['data' => $houses->start($user, $request->string('address')->toString(), $city, CarbonImmutable::parse($request->string('starts_at')->toString()), CarbonImmutable::parse($request->string('ends_at')->toString()))]);
    }

    /**
     * Who signed in.
     *
     * @response array{data: list<array{id: string, name: string, email: string|null, phone: string|null, has_agent: bool, pre_approved: bool, timeline: 'now'|'3_months'|'6_months'|'later'|'just_looking'|null, source: 'qr'|'kiosk', signed_in_at: string, contact: string|null}>}
     */
    public function visits(string $openHouse, #[CurrentUser] User $user, OpenHouses $houses): JsonResponse
    {
        return response()->json(['data' => $houses->visits($user, $openHouse)]);
    }

    /**
     * Send kiosk sign-ins.
     *
     * Sign-ins made on my phone or tablet in kiosk mode, possibly offline. Each lands once, by its `client_id`, in my CRM with a follow-up.
     *
     * @response array{data: array{added: int, duplicates: int}}
     */
    public function sync(SyncVisitsRequest $request, string $openHouse, #[CurrentUser] User $user, OpenHouses $houses): JsonResponse
    {
        return response()->json(['data' => $houses->sync($user, $openHouse, $request->visits())]);
    }
}
