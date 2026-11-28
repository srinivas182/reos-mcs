<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\BusinessDetailsRequest;
use App\Http\Requests\Providers\PlanRequest;
use App\Http\Requests\Providers\RulesRequest;
use App\Http\Requests\Providers\ServiceAreaRequest;
use App\Models\User;
use App\ProviderNetwork\ProviderOnboarding;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Joining the provider network (B15.2, provider screens 1–6, ADR 0074):
 * the steps, business details and a photo ID, service areas, the plan and
 * the network rules. The category (B15.1) and the licence (B10.3) have
 * their own endpoints.
 */
final class ProviderOnboardingController extends Controller
{
    /**
     * Where I am in joining.
     *
     * Each step and whether it's done, my status (joining, waiting, live or hidden), and what each step needs.
     *
     * @response array{data: array{status: string, hidden_reason: string|null, steps: list<array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}>, done: int, joining: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}, licence: array{kind: string, licences: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}>, plans: list<array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}}
     */
    public function show(#[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        return response()->json(['data' => $onboarding->status($user)]);
    }

    /**
     * I've read the welcome.
     *
     * @response array{data: array{status: string, hidden_reason: string|null, steps: list<array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}>, done: int, joining: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}, licence: array{kind: string, licences: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}>, plans: list<array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}}
     */
    public function welcome(#[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        return response()->json(['data' => $onboarding->welcome($user)]);
    }

    /**
     * My business details and photo ID.
     *
     * For categories without a licence. A REOS reviewer checks the ID.
     *
     * @response array{data: array{status: string, hidden_reason: string|null, steps: list<array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}>, done: int, joining: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}, licence: array{kind: string, licences: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}>, plans: list<array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}}
     */
    public function business(BusinessDetailsRequest $request, #[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        return response()->json(['data' => $onboarding->business($user, $request->string('name')->toString(), $request->string('address')->toString(), $request->idPhoto())]);
    }

    /**
     * Add a service area.
     *
     * A city, a ZIP code, or a radius in miles around a ZIP code; up to 10. Each shows the active agents there and the providers in my category who serve it.
     *
     * @response array{data: array{status: string, hidden_reason: string|null, steps: list<array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}>, done: int, joining: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}, licence: array{kind: string, licences: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}>, plans: list<array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}}
     */
    public function addArea(ServiceAreaRequest $request, #[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        $radius = $request->input('radius_miles');
        $city = $request->input('city');
        $zip = $request->input('postal_code');

        return response()->json(['data' => $onboarding->addArea($user, $request->kind(), is_string($city) ? $city : null, is_string($zip) ? $zip : null, is_numeric($radius) ? (int) $radius : null)]);
    }

    /**
     * Remove a service area.
     *
     * @response array{data: array{status: string, hidden_reason: string|null, steps: list<array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}>, done: int, joining: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}, licence: array{kind: string, licences: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}>, plans: list<array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}}
     */
    public function removeArea(int $area, #[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        return response()->json(['data' => $onboarding->removeArea($user, $area)]);
    }

    /**
     * Buy a plan on reos.com.
     *
     * Listed, Pro or Partner at the category's flat monthly price in my state. Returns the checkout page to open in the browser.
     *
     * @response array{data: array{checkout_url: string}}
     */
    public function plan(PlanRequest $request, #[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        return response()->json(['data' => $onboarding->checkout($user, $request->string('tier')->toString())]);
    }

    /**
     * Accept the network rules.
     *
     * The five rules at their current version; asked again when they change.
     *
     * @response array{data: array{status: string, hidden_reason: string|null, steps: list<array{key: 'welcome'|'category'|'licence'|'areas'|'plan'|'rules', done: bool}>, done: int, joining: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}, licence: array{kind: string, licences: list<array{id: int, kind: string, number: string, status: string, name_on_record: string|null, licence_type: string|null, expires_on: string|null, checked_via: string|null, review_note: string|null, consumer_url: string|null}>, business: array{name: string|null, address: string|null}|null, verified: bool}, areas: list<array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}>, plans: list<array{tier: 'basic'|'pro'|'premium', monthly: int, areas: int}>, tier: string|null, rules: array{version: string, keys: list<string>, accepted_at: string|null, current: bool}}}
     */
    public function rules(RulesRequest $request, #[CurrentUser] User $user, ProviderOnboarding $onboarding): JsonResponse
    {
        return response()->json(['data' => $onboarding->acceptRules($user, $request->string('version')->toString())]);
    }
}
