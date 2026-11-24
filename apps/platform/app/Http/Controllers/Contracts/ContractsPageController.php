<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contracts;

use App\ContractReview\ContractReviews;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Content\LessonFiles;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contract review on the web (A12.2, ADR 0048; agent web screen 15): the
 * reviews and how to start one, and one review's result with the contract
 * beside it. Actions use the same JSON routes as the API.
 */
final class ContractsPageController extends Controller
{
    public function index(#[CurrentUser] User $user, ContractReviews $reviews): Response
    {
        return Inertia::render('contracts/index', ['contracts' => $reviews->overview($user)]);
    }

    public function show(string $review, #[CurrentUser] User $user, ContractReviews $reviews): Response
    {
        return Inertia::render('contracts/show', ['review' => $reviews->row($reviews->own($user, $review))]);
    }

    /** The contract itself, for the agent only. */
    public function file(string $review, #[CurrentUser] User $user, ContractReviews $reviews): StreamedResponse
    {
        $found = $reviews->own($user, $review);

        return LessonFiles::disk()->response($found->path, $found->file_name, ['Content-Type' => $found->mime, 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }
}
