<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\StartUploadRequest;
use App\Models\Lesson;
use App\Models\LessonMedia;
use App\Models\User;
use App\Services\Content\LessonFiles;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Lesson file uploads (A5.3): the browser asks for a signed link, uploads
 * the file straight to storage, then says it's done. The page polls for the
 * scan and processing.
 */
final class LessonFileController extends Controller
{
    public function store(StartUploadRequest $request, Lesson $lesson, #[CurrentUser] User $actor, LessonFiles $files): JsonResponse
    {
        $started = $files->begin($lesson, $request->kind(), $request->string('name')->toString(), $request->integer('size'), $actor);

        return response()->json(['id' => $started['media']->id, 'upload' => $started['upload']], 201);
    }

    public function complete(LessonMedia $media, #[CurrentUser] User $actor, LessonFiles $files): JsonResponse
    {
        $media = $files->complete($media, $actor);

        return response()->json(['id' => $media->id, 'status' => $media->status->value, 'failure' => $media->failure]);
    }

    public function destroy(LessonMedia $media, #[CurrentUser] User $actor, LessonFiles $files): RedirectResponse
    {
        $files->remove($media, $actor);

        return back();
    }
}
