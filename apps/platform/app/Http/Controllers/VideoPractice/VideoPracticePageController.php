<?php

declare(strict_types=1);

namespace App\Http\Controllers\VideoPractice;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoPractice\ReviewVideoRequest;
use App\Models\User;
use App\VideoPractice\VideoPractices;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Video practice on the web (A14.1b): record in the browser, then the review. */
final class VideoPracticePageController extends Controller
{
    public function index(#[CurrentUser] User $user, VideoPractices $practices): Response
    {
        return Inertia::render('video-practice/index', $practices->overview($user));
    }

    public function show(string $videoPractice, #[CurrentUser] User $user, VideoPractices $practices): Response
    {
        return Inertia::render('video-practice/show', ['review' => $practices->show($user, $videoPractice)]);
    }

    public function review(ReviewVideoRequest $request, #[CurrentUser] User $user, VideoPractices $practices): RedirectResponse
    {
        $review = $practices->review($user, $request->kind(), $request->string('transcript')->toString(), $request->integer('duration_seconds'));

        return to_route('video-practice.show', $review['id']);
    }
}
