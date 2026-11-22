<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Releases;

use App\Features\Releases;
use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class WhatsNewController extends Controller
{
    /**
     * What's new (A6.2).
     *
     * The latest release note for the person's active role that they haven't
     * dismissed (released in the last 30 days), or null. Shown once on Today.
     *
     * @response array{release: array{id: int, version: string, released_on: string, note: string}|null}
     */
    public function show(#[CurrentUser] User $user, Releases $releases): JsonResponse
    {
        return response()->json(['release' => $releases->card($user)]);
    }

    /**
     * Dismiss what's new (A6.2).
     *
     * It doesn't show again on the phone or the web.
     */
    public function dismiss(Release $release, #[CurrentUser] User $user, Releases $releases): Response
    {
        abort_if($release->published_at === null, 404);
        $releases->dismiss($release, $user);

        return response()->noContent();
    }
}
