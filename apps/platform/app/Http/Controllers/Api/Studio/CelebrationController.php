<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Studio\Celebrations\Celebrations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Celebration moments (B12.3, ADR 0052; agent screen 163). */
final class CelebrationController extends Controller
{
    /**
     * My celebrations.
     *
     * Today's milestone post (at most one a day) and the ones waiting. Each is free, with three captions and three designs, and a draft in My posts.
     *
     * @response array{data: array{today: array{id: string, milestone: 'just_listed'|'under_contract'|'just_sold'|'first_closing'|'ce_complete'|'license_renewed'|'anniversary', label: string|null, post: string|null, generation: array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}|null, shown_at: string|null, created_at: string|null}|null, waiting: list<array{id: string, milestone: 'just_listed'|'under_contract'|'just_sold'|'first_closing'|'ce_complete'|'license_renewed'|'anniversary', label: string|null, post: string|null, generation: array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}|null, shown_at: string|null, created_at: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, Celebrations $celebrations): JsonResponse
    {
        return response()->json(['data' => $celebrations->list($user)]);
    }

    /**
     * One celebration.
     *
     * @response array{data: array{id: string, milestone: 'just_listed'|'under_contract'|'just_sold'|'first_closing'|'ce_complete'|'license_renewed'|'anniversary', label: string|null, post: string|null, generation: array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}|null, shown_at: string|null, created_at: string|null}}
     */
    public function show(string $celebration, #[CurrentUser] User $user, Celebrations $celebrations): JsonResponse
    {
        return response()->json(['data' => Celebrations::row($celebrations->own($user, $celebration))]);
    }
}
