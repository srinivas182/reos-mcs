<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Licence;

use App\Http\Controllers\Controller;
use App\Licences\Journey\BrokerOffers;
use App\Licences\Journey\PassAnnouncements;
use App\Models\BrokerOffer;
use App\Models\PassAnnouncement;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * After the exam (A10.3b, ADR 0041): share the news (f05), passes and
 * congrats (f06), and broker offers (17). The candidate's own, never scores;
 * contact details reach a brokerage only on Accept.
 */
final class PassesAndOffersController extends Controller
{
    /**
     * Share my news.
     *
     * Who hears: my study group and school (told now) and candidates in my state (in Passes). First name, city and an optional tip; never scores.
     *
     * @response array{data: array{shared: bool}}
     */
    public function share(Request $request, #[CurrentUser] User $user, PassAnnouncements $news): JsonResponse
    {
        $request->validate([
            'to_group' => ['required', 'boolean'],
            'to_school' => ['required', 'boolean'],
            'to_state' => ['required', 'boolean'],
            'city' => ['nullable', 'string', 'max:120'],
            'tip' => ['nullable', 'string', 'max:280'],
        ]);
        $news->share($user, [
            'to_group' => $request->boolean('to_group'),
            'to_school' => $request->boolean('to_school'),
            'to_state' => $request->boolean('to_state'),
            'city' => $request->filled('city') ? $request->string('city')->trim()->toString() : null,
            'tip' => $request->filled('tip') ? $request->string('tip')->trim()->toString() : null,
        ]);

        return response()->json(['data' => ['shared' => true]]);
    }

    /**
     * Not now.
     *
     * Nothing is shared.
     *
     * @response array{data: array{shared: bool}}
     */
    public function skip(#[CurrentUser] User $user, PassAnnouncements $news): JsonResponse
    {
        $news->skip($user);

        return response()->json(['data' => ['shared' => false]]);
    }

    /**
     * Passes.
     *
     * Candidates in my state and my study group who shared their pass, this week's count of passes in my state, and my readiness.
     *
     * @response array{data: array{week: int, state: string, readiness: int|null, passes: list<array{id: int, first_name: string, city: string|null, state: string, tip: string|null, on: string, congrats: int, sent: bool, mine: bool}>}}
     */
    public function passes(#[CurrentUser] User $user, PassAnnouncements $news): JsonResponse
    {
        return response()->json(['data' => $news->feed($user)]);
    }

    /**
     * Send congrats.
     *
     * Once per pass; the person is told.
     *
     * @response array{data: array{week: int, state: string, readiness: int|null, passes: list<array{id: int, first_name: string, city: string|null, state: string, tip: string|null, on: string, congrats: int, sent: bool, mine: bool}>}}
     */
    public function congrats(Request $request, PassAnnouncement $pass, #[CurrentUser] User $user, PassAnnouncements $news): JsonResponse
    {
        $request->validate(['message' => ['required', 'string', 'min:2', 'max:140']]);
        $news->congratulate($user, $pass, $request->string('message')->trim()->toString());

        return response()->json(['data' => $news->feed($user)]);
    }

    /**
     * My broker offers.
     *
     * Best first: an office in my city, the brokerage's mentors' rating, then the terms.
     *
     * @response array{data: array{opted_in: bool, city: string|null, offers: list<array{id: string, brokerage: string, office_city: string, split_percent: int, monthly_fee_cents: int, fees: string|null, training: string|null, mentoring: bool, ai_help: bool, ai_credits: int|null, message: string|null, status: 'open'|'accepted'|'declined'|'withdrawn', rating: float|null, ratings: int, fit: bool, contact: array{name: string, email: string, phone: string|null}|null}>}}
     */
    public function offers(#[CurrentUser] User $user, BrokerOffers $offers): JsonResponse
    {
        return response()->json(['data' => $offers->forCandidate($user)]);
    }

    /**
     * Get broker offers, or stop.
     *
     * Brokerages in my state see my first name, city and stage, never contact details or scores.
     *
     * @response array{data: array{opted_in: bool, city: string|null, offers: list<array{id: string, brokerage: string, office_city: string, split_percent: int, monthly_fee_cents: int, fees: string|null, training: string|null, mentoring: bool, ai_help: bool, ai_credits: int|null, message: string|null, status: 'open'|'accepted'|'declined'|'withdrawn', rating: float|null, ratings: int, fit: bool, contact: array{name: string, email: string, phone: string|null}|null}>}}
     */
    public function optIn(Request $request, #[CurrentUser] User $user, BrokerOffers $offers): JsonResponse
    {
        $request->validate(['on' => ['required', 'boolean'], 'city' => ['nullable', 'string', 'max:120']]);
        $offers->optIn($user, $request->boolean('on'), $request->filled('city') ? $request->string('city')->toString() : null);

        return response()->json(['data' => $offers->forCandidate($user)]);
    }

    /**
     * Accept an offer.
     *
     * The brokerage gets my name, email and phone.
     *
     * @response array{data: array{opted_in: bool, city: string|null, offers: list<array{id: string, brokerage: string, office_city: string, split_percent: int, monthly_fee_cents: int, fees: string|null, training: string|null, mentoring: bool, ai_help: bool, ai_credits: int|null, message: string|null, status: 'open'|'accepted'|'declined'|'withdrawn', rating: float|null, ratings: int, fit: bool, contact: array{name: string, email: string, phone: string|null}|null}>}}
     */
    public function accept(BrokerOffer $offer, #[CurrentUser] User $user, BrokerOffers $offers): JsonResponse
    {
        $offers->accept($user, $offer);

        return response()->json(['data' => $offers->forCandidate($user)]);
    }

    /**
     * Decline an offer.
     *
     * It's hidden.
     *
     * @response array{data: array{opted_in: bool, city: string|null, offers: list<array{id: string, brokerage: string, office_city: string, split_percent: int, monthly_fee_cents: int, fees: string|null, training: string|null, mentoring: bool, ai_help: bool, ai_credits: int|null, message: string|null, status: 'open'|'accepted'|'declined'|'withdrawn', rating: float|null, ratings: int, fit: bool, contact: array{name: string, email: string, phone: string|null}|null}>}}
     */
    public function decline(BrokerOffer $offer, #[CurrentUser] User $user, BrokerOffers $offers): JsonResponse
    {
        $offers->decline($user, $offer);

        return response()->json(['data' => $offers->forCandidate($user)]);
    }
}
