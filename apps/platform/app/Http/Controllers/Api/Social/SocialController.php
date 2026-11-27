<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\MediaRequest;
use App\Http\Requests\Social\PostRequest;
use App\Models\Connection;
use App\Models\User;
use App\Social\SocialAccounts;
use App\Social\SocialPosts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Social posting (B11, ADR 0046; agent screens 108, 169 and 170): accounts
 * and where each can post, the posts with their results, checks, publish
 * now or schedule, drafts, Undo and Repost.
 */
final class SocialController extends Controller
{
    /**
     * Social accounts.
     *
     * Every social channel, whether it's connected, and the pages, profiles or channels it can post to.
     *
     * @response array{data: list<array{integration: string, name: string, purpose: string, launch: string, built: bool, available: bool, connection: array{id: int, status: string, label: string|null}|null, targets: list<array{channel: string, id: string, name: string}>, channels: array<string, array{media: 'required'|'optional'|'video', max_text: int}>}>}
     */
    public function accounts(#[CurrentUser] User $user, SocialAccounts $accounts): JsonResponse
    {
        return response()->json(['data' => $accounts->for($user)]);
    }

    /**
     * Read a connection's pages and profiles again.
     *
     * @response array{data: list<array{channel: string, id: string, name: string}>}
     */
    public function refresh(int $connection, #[CurrentUser] User $user, SocialAccounts $accounts): JsonResponse
    {
        $found = Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->findOrFail($connection);

        return response()->json(['data' => $accounts->targets($found, true)]);
    }

    /**
     * My posts and results.
     *
     * @response array{data: array{stats: array{posts_this_month: int, reach: int, engagement: int, clicks: int}, scheduled: list<array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}>, posted: list<array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}>, drafts: list<array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->list($user)]);
    }

    /**
     * One post.
     *
     * @response array{data: array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}}
     */
    public function show(string $post, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->show($user, $post)]);
    }

    /**
     * Check a post.
     *
     * The attribution it will carry and each check; a post that fails can't be scheduled.
     *
     * @response array{data: array{attribution: string, checks: list<array{code: 'broker'|'targets'|'length'|'media'|'video'|'title'|'fair_housing'|'claims'|'banned'|'team_name', ok: bool, detail: string|null, citation?: string|null}>, ok: bool, approval: bool, issues: list<array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}>, review: list<string>}}
     */
    public function check(PostRequest $request, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->check($user, $request->string('body')->toString(), $request->media(), $request->targets(), $request->optional('listing'))]);
    }

    /**
     * Fix automatically.
     *
     * The post's words with flagged fair housing wording, misleading claims and the brokerage's banned phrases swapped for the suggested words or taken out (screen 167).
     *
     * @response array{data: array{body: string, fixed: list<array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}>}}
     */
    public function fix(Request $request, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:5000']]);

        return response()->json(['data' => $posts->fix($user, $request->string('body')->toString())]);
    }

    /**
     * New post: publish now, schedule or save a draft.
     *
     * @status 201
     *
     * @response array{data: array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}}
     */
    public function store(PostRequest $request, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        $row = $posts->create($user, $request->string('body')->toString(), $request->media(), $request->optional('link'), $request->targets(), $request->string('when')->toString(), $request->optional('at'), $request->optional('listing'));

        return response()->json(['data' => $row], 201);
    }

    /**
     * Change a draft or a scheduled post.
     *
     * @response array{data: array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}}
     */
    public function update(PostRequest $request, string $post, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->update($user, $post, $request->string('body')->toString(), $request->media(), $request->optional('link'), $request->targets(), $request->string('when')->toString(), $request->optional('at'))]);
    }

    /** Remove a draft or a scheduled post (Undo restores it). */
    public function destroy(string $post, #[CurrentUser] User $user, SocialPosts $posts): Response
    {
        $posts->delete($user, $post);

        return response()->noContent();
    }

    /**
     * Undo removing a post.
     *
     * @response array{data: array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}}
     */
    public function restore(string $post, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->restore($user, $post)]);
    }

    /**
     * Post it again, as a new draft.
     *
     * @status 201
     *
     * @response array{data: array{id: string, body: string, media: list<array{url: string, kind: string}>, link: string|null, attribution: string, listing: string|null, status: 'draft'|'approval'|'scheduled'|'publishing'|'published'|'partly'|'failed', scheduled_at: string|null, published_at: string|null, created_at: string|null, targets: list<array{id: int, integration: string, channel: string, target_id: string, name: string, status: 'queued'|'publishing'|'published'|'failed', error: string|null, url: string|null, published_at: string|null, insights: array<string, int>}>, totals: array<string, int>, approval_note: string|null}}
     */
    public function repost(string $post, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->repost($user, $post)], 201);
    }

    /**
     * Upload a photo or short video for a post.
     *
     * @status 201
     *
     * @response array{data: array{path: string, url: string, kind: string}}
     */
    public function media(MediaRequest $request, #[CurrentUser] User $user, SocialPosts $posts): JsonResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return response()->json(['data' => $posts->upload($user, $file)], 201);
    }

    /**
     * A post's media for the channels to fetch (signed, for 48 hours). Videos
     * need byte ranges to play on phones (B12.2): S3 serves them from a
     * short-lived link; the local disk answers ranges itself.
     */
    public function file(Request $request, string $path): SymfonyResponse
    {
        abort_unless($request->hasValidSignature() && str_starts_with($path, 'social/') && ! str_contains($path, '..'), 404);
        $disk = Storage::disk(SocialPosts::MEDIA_DISK);
        abort_unless($disk->exists($path), 404);

        if (is_file($disk->path($path))) {
            return response()->file($disk->path($path), ['Cache-Control' => 'private, max-age=3600']);
        }

        return $disk->providesTemporaryUrls()
            ? redirect()->away($disk->temporaryUrl($path, now()->addMinutes(30)))
            : $disk->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
