<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\MakeVideoRequest;
use App\Http\Requests\Studio\UpdateVideoRequest;
use App\Models\User;
use App\Studio\Video\StudioVideos;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Studio videos (B12.2, ADR 0051; agent screens 166 and 168, web screen 17). */
final class VideoController extends Controller
{
    /**
     * Make a video.
     *
     * About 30 seconds, vertical, from the listing's photos and my own clips and photos (uploaded first with `POST /social/media`): a hook, a caption on each scene, the brand frame with my broker, and music. Rendered in the background (`status` is `rendering`, then `ready` or `failed`). Costs the video credits (shown first).
     *
     * @status 201
     *
     * @response array{data: array{id: string, source: array{type: 'listing'|'own', id: string|null, label: string|null}, listing: string|null, title: string, scenes: list<array{media: string, url: string, kind: 'image'|'video', start: float, seconds: float, length: float|null, caption: string}>, music: 'calm'|'bright'|'none', brand: bool, status: 'rendering'|'ready'|'failed', seconds: float|null, video: array{path: string, url: string}|null, poster: array{path: string, url: string}|null, error: string|null, issues: list<array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}>, credits: int, engine: string, created_at: string|null, rendered_at: string|null}}
     */
    public function store(MakeVideoRequest $request, #[CurrentUser] User $user, StudioVideos $videos): JsonResponse
    {
        /** @var array{type: 'listing'|'own', id?: string|null, text?: string|null} $source */
        $source = $request->validated('source');
        $clips = array_values(array_map(static fn (mixed $c): string => is_string($c) ? $c : '', (array) ($request->validated('clips') ?? [])));
        $video = $videos->make($user, ['type' => $source['type'], 'id' => $source['id'] ?? null, 'text' => $source['text'] ?? null], $clips);

        return response()->json(['data' => $videos->row($video)], 201);
    }

    /**
     * One video.
     *
     * @response array{data: array{id: string, source: array{type: 'listing'|'own', id: string|null, label: string|null}, listing: string|null, title: string, scenes: list<array{media: string, url: string, kind: 'image'|'video', start: float, seconds: float, length: float|null, caption: string}>, music: 'calm'|'bright'|'none', brand: bool, status: 'rendering'|'ready'|'failed', seconds: float|null, video: array{path: string, url: string}|null, poster: array{path: string, url: string}|null, error: string|null, issues: list<array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}>, credits: int, engine: string, created_at: string|null, rendered_at: string|null}}
     */
    public function show(string $video, #[CurrentUser] User $user, StudioVideos $videos): JsonResponse
    {
        return response()->json(['data' => $videos->row($videos->own($user, $video))]);
    }

    /**
     * Edit a video.
     *
     * The hook, the scenes (order, trim, length, caption; my own uploads can be added), the music and the brand colour. The words are checked again and the result is in `issues`; `fix` swaps flagged wording; `render` renders again (free) once the checks pass.
     *
     * @response array{data: array{id: string, source: array{type: 'listing'|'own', id: string|null, label: string|null}, listing: string|null, title: string, scenes: list<array{media: string, url: string, kind: 'image'|'video', start: float, seconds: float, length: float|null, caption: string}>, music: 'calm'|'bright'|'none', brand: bool, status: 'rendering'|'ready'|'failed', seconds: float|null, video: array{path: string, url: string}|null, poster: array{path: string, url: string}|null, error: string|null, issues: list<array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}>, credits: int, engine: string, created_at: string|null, rendered_at: string|null}}
     */
    public function update(UpdateVideoRequest $request, string $video, #[CurrentUser] User $user, StudioVideos $videos): JsonResponse
    {
        /** @var array{title?: string|null, music?: string, brand?: bool, scenes?: list<array{media: string, start?: float|int|string|null, seconds: float|int|string, caption?: string|null}>, fix?: bool, render?: bool} $edits */
        $edits = $request->validated();
        $title = $edits['title'] ?? null;
        unset($edits['title']);

        if ($request->has('title')) {
            $edits['title'] = $title ?? '';
        }

        return response()->json(['data' => $videos->row($videos->update($videos->own($user, $video), $edits))]);
    }

    /**
     * Hook ideas.
     *
     * Three stronger first two seconds for the video (free).
     *
     * @response array{data: list<string>}
     */
    public function hooks(string $video, #[CurrentUser] User $user, StudioVideos $videos): JsonResponse
    {
        return response()->json(['data' => $videos->hooks($videos->own($user, $video))]);
    }
}
