<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\MakeContentRequest;
use App\Models\User;
use App\Studio\Studio;
use App\Studio\StudioGenerator;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The content studio's maker (B12.1, ADR 0050; agent screens 165, 166 and 107, web screen 17). */
final class StudioController extends Controller
{
    /**
     * The studio.
     *
     * What a making and a video cost and my credits left, what I can start from (my listings that may be posted, my closed deals), what I made lately, the music for videos, and my videos.
     *
     * @response array{data: array{cost: int, credits_left: int, listings: list<array{id: string, address: string, status: string, photo: string|null}>, sales: list<array{id: string, property: string}>, generations: list<array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}>, video_cost: int, tracks: list<'calm'|'bright'|'none'>, videos: list<array{id: string, source: array{type: 'listing'|'own', id: string|null, label: string|null}, listing: string|null, title: string, scenes: list<array{media: string, url: string, kind: 'image'|'video', start: float, seconds: float, length: float|null, caption: string}>, music: 'calm'|'bright'|'none', brand: bool, status: 'rendering'|'ready'|'failed', seconds: float|null, video: array{path: string, url: string}|null, poster: array{path: string, url: string}|null, error: string|null, issues: list<array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}>, credits: int, engine: string, created_at: string|null, rendered_at: string|null}>}}
     */
    public function home(#[CurrentUser] User $user, Studio $studio): JsonResponse
    {
        return response()->json(['data' => $studio->home($user)]);
    }

    /**
     * Make content.
     *
     * From a listing, a closed deal, a market update or my own words: three captions and three designs (post), carousel slides, and ad copy. Every version passes the fair housing, claims and brokerage checks before it's shown. Costs the studio's credits (shown first).
     *
     * @status 201
     *
     * @response array{data: array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}}
     */
    public function make(MakeContentRequest $request, #[CurrentUser] User $user, StudioGenerator $generator): JsonResponse
    {
        /** @var array{type: 'listing'|'sale'|'market'|'text', id?: string|null, text?: string|null, city?: string|null} $source */
        $source = $request->validated('source');
        $outputs = array_values(array_map(static fn (mixed $o): string => is_string($o) ? $o : '', (array) $request->validated('outputs')));
        $made = $generator->make($user, ['type' => $source['type'], 'id' => $source['id'] ?? null, 'text' => $source['text'] ?? null, 'city' => $source['city'] ?? null], $outputs);

        return response()->json(['data' => Studio::row($made)], 201);
    }

    /**
     * One making.
     *
     * @response array{data: array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}}
     */
    public function show(string $generation, #[CurrentUser] User $user, Studio $studio): JsonResponse
    {
        return response()->json(['data' => Studio::row($studio->own($user, $generation))]);
    }

    /**
     * A caption in another tone.
     *
     * Shorter, more professional or in Spanish (free).
     *
     * @response array{data: array{id: string, source: array{type: 'listing'|'sale'|'market'|'text'|'celebration', id: string|null, label: string|null}, listing: string|null, outputs: list<'post'|'carousel'|'ad'>, captions: list<array{tone: 'professional'|'warm'|'short', text: string}>, designs: list<array{layout: 'band'|'top'|'split', path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}}
     */
    public function retone(Request $request, string $generation, #[CurrentUser] User $user, Studio $studio, StudioGenerator $generator): JsonResponse
    {
        $request->validate(['index' => ['required', 'integer', 'min:0', 'max:2'], 'tone' => ['required', 'in:shorter,professional,spanish']]);

        return response()->json(['data' => Studio::row($generator->retone($studio->own($user, $generation), $request->integer('index'), $request->string('tone')->toString()))]);
    }
}
