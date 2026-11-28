<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Providers\CategoryRequestRequest;
use App\Http\Requests\Providers\ChooseCategoryRequest;
use App\Http\Requests\Providers\ProfileFieldsRequest;
use App\Models\User;
use App\ProviderNetwork\ProviderJoining;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Joining as a provider (B15.1, ADR 0073): the category grid, requesting a
 * new category, and the category's profile fields, all from the super
 * admin's settings.
 */
final class JoiningController extends Controller
{
    /**
     * Provider categories.
     *
     * The live categories with what each checks and asks for, and the flat monthly plan prices (cents) in a state.
     *
     * @queryParam state string Two-letter state code. Defaults to TX.
     *
     * @response array{data: list<array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}>}
     */
    public function categories(Request $request, ProviderJoining $joining): JsonResponse
    {
        $state = $request->query('state');

        return response()->json(['data' => $joining->categories(is_string($state) && preg_match('/^[A-Za-z]{2}$/', $state) === 1 ? $state : 'TX')]);
    }

    /**
     * Request a category.
     *
     * For work that isn't listed: it goes to REOS as a requested category, counted once per person.
     *
     * @response array{data: array{key: string, name: string, status: string, requests: int}}
     */
    public function request(CategoryRequestRequest $request, #[CurrentUser] User $user, ProviderJoining $joining): JsonResponse
    {
        $note = $request->input('note');

        return response()->json(['data' => $joining->request($user, $request->string('name')->toString(), is_string($note) ? $note : null)]);
    }

    /**
     * My provider profile so far.
     *
     * The category chosen, its fields and my answers, and which fields are still empty.
     *
     * @response array{data: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}}
     */
    public function show(#[CurrentUser] User $user, ProviderJoining $joining): JsonResponse
    {
        return response()->json(['data' => $joining->profile($user)]);
    }

    /**
     * Choose my category.
     *
     * Answers to fields the new category doesn't have are dropped.
     *
     * @response array{data: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}}
     */
    public function choose(ChooseCategoryRequest $request, #[CurrentUser] User $user, ProviderJoining $joining): JsonResponse
    {
        return response()->json(['data' => $joining->choose($user, $request->string('category')->toString())]);
    }

    /**
     * Answer my profile fields.
     *
     * Text, a list of values (from the category's options when it has them) or true/false, per field key.
     *
     * @response array{data: array{category: array{key: string, name: string, description: string|null, licence: array{kind: 'nmls'|'trec_inspector'|'tdi'|'business', label: string|null}, fields: list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>, areas_on_pro: int, prices: array{basic: int|null, pro: int|null, premium: int|null}}|null, fields: array<string, string|bool|list<string>>, missing: list<string>}}
     */
    public function fields(ProfileFieldsRequest $request, #[CurrentUser] User $user, ProviderJoining $joining): JsonResponse
    {
        return response()->json(['data' => $joining->answer($user, $request->answers())]);
    }
}
